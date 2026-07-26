<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\CategoryModel;
use App\Models\GiftBoxModel;
use App\Models\GiftBoxPricingRuleModel;
use App\Models\ProductModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Gift-box configuration — the controls behind the builder.
 *
 * Split into three endpoints rather than one giant form: the basics, what may
 * go in, and how it is priced. Each saves independently, so a mistake in the
 * pricing rules cannot lose the description someone just wrote.
 */
class GiftBoxes extends AdminController
{
    public function index()
    {
        if ($denied = $this->deny('giftboxes.view')) {
            return $denied;
        }

        $boxes = model(GiftBoxModel::class)->orderBy('sort_order', 'ASC')->findAll();

        // How many products each box can actually offer. A box configured so
        // tightly that nothing qualifies is a broken box, and the list should
        // say so rather than leaving it to be discovered in the builder.
        $reach = [];

        foreach ($boxes as $box) {
            $reach[(int) $box->id] = count(model(GiftBoxModel::class)->allowedProductIds((int) $box->id));
        }

        return $this->adminPage('admin/giftboxes/index', [
            'boxes'     => $boxes,
            'reach'     => $reach,
            'canManage' => $this->can('giftboxes.manage'),
        ], 'Gift boxes');
    }

    public function create()
    {
        if ($denied = $this->deny('giftboxes.manage')) {
            return $denied;
        }

        return $this->form(null);
    }

    public function edit(int $id)
    {
        if ($denied = $this->deny('giftboxes.manage')) {
            return $denied;
        }

        $box = model(GiftBoxModel::class)->find($id);

        if ($box === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->form($box);
    }

    public function store()
    {
        if ($denied = $this->deny('giftboxes.manage')) {
            return $denied;
        }

        return $this->save(null);
    }

    public function update(int $id)
    {
        if ($denied = $this->deny('giftboxes.manage')) {
            return $denied;
        }

        if (model(GiftBoxModel::class)->find($id) === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->save($id);
    }

    public function delete(int $id)
    {
        if ($denied = $this->deny('giftboxes.manage')) {
            return $denied;
        }

        $model = model(GiftBoxModel::class);
        $box   = $model->find($id);

        if ($box === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model->delete($id);
        service('audit')->log('deleted', 'giftboxes', 'gift_box', $id, $box->name);

        return redirect()->to(site_url('admin/gift-boxes'))
            ->with('success', $box->name . ' removed. Past orders keep their record of it.');
    }

    // =================================================================
    // What may go in the box
    // =================================================================

    public function saveContents(int $id)
    {
        if ($denied = $this->deny('giftboxes.manage')) {
            return $denied;
        }

        $box = model(GiftBoxModel::class)->find($id);

        if ($box === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $db = db_connect();

        // ---- allowed categories ----
        $categoryIds = array_map('intval', (array) $this->request->getPost('categories'));
        $valid       = array_column(
            $db->table('categories')->select('id')->get()->getResultArray(),
            'id'
        );
        $categoryIds = array_values(array_intersect($categoryIds, array_map('intval', $valid)));

        $db->table('gift_box_categories')->where('gift_box_id', $id)->delete();

        foreach ($categoryIds as $categoryId) {
            $db->table('gift_box_categories')->insert([
                'gift_box_id' => $id,
                'category_id' => $categoryId,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        }

        // ---- per-product pins: allowed, excluded, and caps ----
        $pins = (array) $this->request->getPost('pin');
        $caps = (array) $this->request->getPost('cap');

        $db->table('gift_box_products')->where('gift_box_id', $id)->delete();

        $allowed = 0;
        $excluded = 0;

        foreach ($pins as $productId => $mode) {
            $productId = (int) $productId;

            if ($productId <= 0 || ! in_array($mode, ['allow', 'exclude'], true)) {
                continue;
            }

            $cap = isset($caps[$productId]) && $caps[$productId] !== ''
                ? max(1, min(24, (int) $caps[$productId]))
                : null;

            $db->table('gift_box_products')->insert([
                'gift_box_id' => $id,
                'product_id'  => $productId,
                'is_excluded' => $mode === 'exclude' ? 1 : 0,
                'max_quantity' => $mode === 'exclude' ? null : $cap,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);

            $mode === 'exclude' ? $excluded++ : $allowed++;
        }

        service('audit')->log(
            'contents_updated',
            'giftboxes',
            'gift_box',
            $id,
            $box->name . ': ' . count($categoryIds) . ' categories, '
                . $allowed . ' pinned, ' . $excluded . ' excluded'
        );

        // Tell them what the box can actually offer now, rather than making
        // them open the builder to find out.
        $reach = count(model(GiftBoxModel::class)->allowedProductIds($id));

        return redirect()->to(site_url('admin/gift-boxes/' . $id . '/edit') . '#contents')
            ->with(
                $reach === 0 ? 'error' : 'success',
                $reach === 0
                    ? 'Saved, but nothing qualifies for this box now — customers would see an empty builder.'
                    : 'Contents saved. ' . $reach . ' product' . ($reach === 1 ? '' : 's') . ' can go in this box.'
            );
    }

    // =================================================================
    // Pricing rules
    // =================================================================

    public function saveRules(int $id)
    {
        if ($denied = $this->deny('giftboxes.manage')) {
            return $denied;
        }

        $box = model(GiftBoxModel::class)->find($id);

        if ($box === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $model = model(GiftBoxPricingRuleModel::class);
        $rows  = (array) $this->request->getPost('rules');
        $kept  = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            // A blank row is how you delete one, so skip rather than complain.
            if (($row['rule_type'] ?? '') === '' || ! empty($row['_delete'])) {
                continue;
            }

            $payload = [
                'gift_box_id'  => $id,
                'rule_type'    => (string) $row['rule_type'],
                'value'        => (float) ($row['value'] ?? 0),
                'min_slots'    => ($row['min_slots'] ?? '') !== '' ? (int) $row['min_slots'] : null,
                'max_slots'    => ($row['max_slots'] ?? '') !== '' ? (int) $row['max_slots'] : null,
                'min_subtotal' => ($row['min_subtotal'] ?? '') !== '' ? (float) $row['min_subtotal'] : null,
                'label'        => trim((string) ($row['label'] ?? '')) ?: null,
                'priority'     => (int) ($row['priority'] ?? 0),
                'is_active'    => ! empty($row['is_active']) ? 1 : 0,
            ];

            // A band with min above max can never match — catch it here rather
            // than letting it sit in the table doing nothing.
            if ($payload['min_slots'] !== null && $payload['max_slots'] !== null
                && $payload['min_slots'] > $payload['max_slots']) {
                $errors[] = 'Rule ' . ($index + 1) . ': the slot range is back to front.';

                continue;
            }

            if ($payload['min_slots'] !== null && $payload['min_slots'] > (int) $box->capacity_slots) {
                $errors[] = 'Rule ' . ($index + 1) . ': needs more slots than the box has.';

                continue;
            }

            $kept[] = $payload;
        }

        // Replace wholesale: the form is the full picture of this box's rules.
        $model->where('gift_box_id', $id)->delete();

        foreach ($kept as $payload) {
            if ($model->insert($payload) === false) {
                $errors[] = implode(' ', $model->errors());
            }
        }

        service('audit')->log('rules_updated', 'giftboxes', 'gift_box', $id, $box->name . ': ' . count($kept) . ' rule(s)');

        return redirect()->to(site_url('admin/gift-boxes/' . $id . '/edit') . '#pricing')
            ->with(
                $errors === [] ? 'success' : 'error',
                $errors === []
                    ? count($kept) . ' pricing rule' . (count($kept) === 1 ? '' : 's') . ' saved.'
                    : implode(' ', array_unique($errors))
            );
    }

    // ------------------------------------------------------------------

    private function form(?object $box): string
    {
        $id = $box !== null ? (int) $box->id : 0;
        $db = db_connect();

        $selectedCategories = $id > 0
            ? array_map('intval', array_column(
                $db->table('gift_box_categories')->select('category_id')->where('gift_box_id', $id)->get()->getResultArray(),
                'category_id'
            ))
            : [];

        $pins = [];

        if ($id > 0) {
            foreach ($db->table('gift_box_products')->where('gift_box_id', $id)->get()->getResultArray() as $pin) {
                $pins[(int) $pin['product_id']] = [
                    'mode' => (int) $pin['is_excluded'] === 1 ? 'exclude' : 'allow',
                    'cap'  => $pin['max_quantity'],
                ];
            }
        }

        return $this->adminPage('admin/giftboxes/form', [
            'box'                => $box,
            'categories'         => model(CategoryModel::class)->orderBy('name', 'ASC')->findAll(),
            'selectedCategories' => $selectedCategories,
            'products'           => model(ProductModel::class)
                ->select('products.id, products.name, products.sku, products.giftbox_slots, products.is_giftbox_eligible, products.category_id')
                ->where('products.is_active', 1)
                ->orderBy('products.name', 'ASC')
                ->findAll(),
            'pins'               => $pins,
            'rules'              => $id > 0 ? model(GiftBoxPricingRuleModel::class)->activeForBox($id) : [],
            'allRules'           => $id > 0
                ? model(GiftBoxPricingRuleModel::class)->where('gift_box_id', $id)->orderBy('priority', 'ASC')->findAll()
                : [],
            'reach'              => $id > 0 ? count(model(GiftBoxModel::class)->allowedProductIds($id)) : 0,
            'needsEditor'        => true,
        ], $box === null ? 'New gift box' : 'Edit ' . $box->name);
    }

    private function save(?int $id)
    {
        $model = model(GiftBoxModel::class);
        $name  = trim((string) $this->request->getPost('name'));
        $slug  = trim((string) $this->request->getPost('slug')) ?: $name;

        $payload = [
            'name'                   => $name,
            'slug'                   => strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $slug), '-')),
            'description'            => trim((string) $this->request->getPost('description')) ?: null,
            'base_price'             => (float) $this->request->getPost('base_price'),
            'capacity_slots'         => max(1, (int) $this->request->getPost('capacity_slots')),
            'min_slots'              => max(0, (int) $this->request->getPost('min_slots')),
            'size_label'             => trim((string) $this->request->getPost('size_label')) ?: null,
            'theme'                  => trim((string) $this->request->getPost('theme')) ?: null,
            'price_tier'             => trim((string) $this->request->getPost('price_tier')) ?: null,
            'sale_mode'              => (string) $this->request->getPost('sale_mode'),
            'allow_gift_message'     => $this->request->getPost('allow_gift_message') !== null ? 1 : 0,
            'gift_message_max_chars' => max(1, (int) $this->request->getPost('gift_message_max_chars')),
            'allow_special_note'     => $this->request->getPost('allow_special_note') !== null ? 1 : 0,
            'is_featured'            => $this->request->getPost('is_featured') !== null ? 1 : 0,
            'is_active'              => $this->request->getPost('is_active') !== null ? 1 : 0,
            'sort_order'             => (int) $this->request->getPost('sort_order'),
            'meta_title'             => trim((string) $this->request->getPost('meta_title')) ?: null,
            'meta_description'       => trim((string) $this->request->getPost('meta_description')) ?: null,
        ];

        $image = $this->request->getFile('image');
        $uploadError = null;

        if ($image !== null && $image->getError() !== UPLOAD_ERR_NO_FILE) {
            $result = service('images')->store($image, 'boxes');

            $result['ok'] ? $payload['image'] = $result['path'] : $uploadError = $result['error'];
        }

        // See CLAUDE.md: {id} is filled from the payload, not from update()'s
        // first argument.
        if ($id !== null) {
            $payload['id'] = $id;
        }

        $saved = $id === null ? $model->insert($payload) : $model->update($id, $payload);

        if ($id !== null) {
            unset($payload['id']);
        }

        if ($saved === false) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        $newId = $id ?? (int) $model->getInsertID();
        service('audit')->log($id === null ? 'created' : 'updated', 'giftboxes', 'gift_box', $newId, $payload['name']);

        return redirect()->to(site_url('admin/gift-boxes/' . $newId . '/edit'))
            ->with($uploadError !== null ? 'error' : 'success', $uploadError ?? 'Gift box saved.');
    }
}
