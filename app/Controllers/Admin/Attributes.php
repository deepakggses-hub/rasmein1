<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\AttributeModel;
use App\Models\AttributeValueModel;

/**
 * Managing attributes and the values they permit.
 *
 * Values are a controlled list rather than free text on the product, because
 * "Silver", "silver" and "SILVER" typed on three products become three facets
 * — and a filter sidebar that offers the same colour three times is worse than
 * no filter at all.
 */
class Attributes extends AdminController
{
    public function index()
    {
        if ($denied = $this->deny('catalogue.manage')) {
            return $denied;
        }

        return $this->adminPage('admin/attributes/index', [
            'attributes' => model(AttributeModel::class)->withValues(false),
            'usage'      => $this->usage(),
        ], 'Attributes');
    }

    public function save()
    {
        if ($denied = $this->deny('catalogue.manage')) {
            return $denied;
        }

        $model = model(AttributeModel::class);
        $id    = (int) $this->request->getPost('id');

        $payload = [
            'name'          => trim((string) $this->request->getPost('name')),
            'code'          => strtolower(trim((string) $this->request->getPost('code'))),
            'input_type'    => $this->request->getPost('input_type') === 'swatch' ? 'swatch' : 'text',
            'is_selectable' => $this->request->getPost('is_selectable') !== null ? 1 : 0,
            'is_filterable' => $this->request->getPost('is_filterable') !== null ? 1 : 0,
            'sort_order'    => (int) $this->request->getPost('sort_order'),
            'is_active'     => $this->request->getPost('is_active') !== null ? 1 : 0,
        ];

        if ($id > 0) {
            // is_unique[...,id,{id}] needs the id IN the payload to exclude the
            // row being edited; without it every update fails as a duplicate.
            $payload['id'] = $id;

            if (! $model->update($id, $payload)) {
                return redirect()->back()->withInput()->with('errors', $model->errors());
            }
        } else {
            if (! $model->insert($payload)) {
                return redirect()->back()->withInput()->with('errors', $model->errors());
            }

            $id = (int) $model->getInsertID();
        }

        service('audit')->log($id > 0 ? 'updated' : 'created', 'catalogue', 'attribute', $id, $payload['name']);

        return redirect()->to(site_url('admin/attributes'))->with('success', 'Saved.');
    }

    public function saveValue()
    {
        if ($denied = $this->deny('catalogue.manage')) {
            return $denied;
        }

        $model = model(AttributeValueModel::class);
        $hex   = trim((string) $this->request->getPost('swatch_hex'));

        $payload = [
            'attribute_id' => (int) $this->request->getPost('attribute_id'),
            'label'        => trim((string) $this->request->getPost('label')),
            // Empty rather than '' — a blank string is not a colour, and the
            // regex rule would reject it on the next edit.
            'swatch_hex'   => $hex !== '' ? $hex : null,
            'sort_order'   => (int) $this->request->getPost('sort_order'),
        ];

        if ($payload['label'] === '') {
            return redirect()->back()->with('error', 'Give the value a name.');
        }

        if (! $model->insert($payload)) {
            return redirect()->back()->with('errors', $model->errors());
        }

        return redirect()->back()->with('success', 'Value added.');
    }

    public function deleteValue(int $id)
    {
        if ($denied = $this->deny('catalogue.manage')) {
            return $denied;
        }

        $used = db_connect()->table('product_attributes')->where('value_id', $id)->countAllResults();

        if ($used > 0) {
            /*
             * Refused, not cascaded. The foreign key would happily remove it
             * from every product, and a shop deleting "Silver" to tidy up would
             * silently strip it from forty pieces with no way back.
             */
            return redirect()->back()->with(
                'error',
                'That value is on ' . $used . ' product' . ($used === 1 ? '' : 's') . '. Remove it there first.'
            );
        }

        model(AttributeValueModel::class)->delete($id);

        return redirect()->back()->with('success', 'Value removed.');
    }

    /**
     * How many products carry each value, so nothing is deleted blind.
     *
     * @return array<int, int>
     */
    private function usage(): array
    {
        $out = [];

        foreach (db_connect()->table('product_attributes')
            ->select('value_id, COUNT(*) AS n', false)
            ->groupBy('value_id')->get()->getResultArray() as $row) {
            $out[(int) $row['value_id']] = (int) $row['n'];
        }

        return $out;
    }
}
