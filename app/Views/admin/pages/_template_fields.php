<?php
/**
 * @var array $template
 * @var array $data
 */

/*
 * The product picker, as a closure so a list row can call it too.
 *
 * A grouped <select multiple> rather than a checkbox wall: 155 products in
 * checkboxes is a page nobody can scan, and the browser gives search and
 * keyboard selection for free.
 */
$rsProductPicker = static function (string $name, array $chosen): void {
    static $grouped = null;

    if ($grouped === null) {
        $grouped = [];

        foreach (model(\App\Models\ProductModel::class)
            ->select('products.id, products.name, products.sku, categories.name AS cat')
            ->join('categories', 'categories.id = products.category_id', 'left')
            ->where('products.is_active', 1)->where('products.deleted_at', null)
            ->orderBy('categories.sort_order', 'ASC')->orderBy('products.name', 'ASC')
            ->asArray()->findAll() as $row) {
            $grouped[(string) ($row['cat'] ?? 'Uncategorised')][] = $row;
        }
    }
    ?>
    <select name="<?= esc($name, 'attr') ?>[]" class="rs-select" multiple size="8">
        <?php foreach ($grouped as $cat => $rows): ?>
            <optgroup label="<?= esc($cat, 'attr') ?>">
                <?php foreach ($rows as $row): ?>
                    <option value="<?= (int) $row['id'] ?>"
                            <?= in_array((int) $row['id'], $chosen, true) ? 'selected' : '' ?>>
                        <?= esc($row['name']) ?> — <?= esc($row['sku']) ?>
                    </option>
                <?php endforeach; ?>
            </optgroup>
        <?php endforeach; ?>
    </select>
    <span class="rs-help">Ctrl or Cmd to pick several. None picked shows nothing.</span>
    <?php
};
?>
<?php
/**
 * The fields a template needs, rendered from Config\PageTemplates.
 *
 * ONE definition drives the picker, this form and the storefront, so a field
 * cannot end up saveable but never shown — or shown but not editable, which is
 * worse because nothing explains why.
 *
 * Repeatable rows are rendered as a fixed number of blank slots rather than a
 * JavaScript repeater: a shop filling in four contact cards or six questions is
 * better served by four visible boxes than by a button that makes them appear.
 * Empty rows are discarded on save.
 *
 * @var array  $template
 * @var array  $data
 */
?>
<?php foreach ($template['sections'] as $sectionKey => $section): ?>
    <section class="border border-shell-line bg-white p-5">
        <h2 class="rs-eyebrow rs-eyebrow--plain"><?= esc($section['label']) ?></h2>

        <?php if (! empty($section['help'])): ?>
            <p class="rs-help mt-2 max-w-2xl"><?= esc($section['help']) ?></p>
        <?php endif; ?>

        <div class="mt-5 grid gap-4">
            <?php foreach ($section['fields'] as $fieldKey => $field): ?>
                <?php
                $name  = 'data[' . $sectionKey . '][' . $fieldKey . ']';
                $value = $data[$sectionKey][$fieldKey] ?? ($field['default'] ?? '');
                ?>

                <?php if ($field['type'] === 'list'): ?>
                    <div>
                        <span class="rs-label"><?= esc($field['label']) ?></span>
                        <?php if (! empty($field['help'])): ?>
                            <span class="rs-help"><?= esc($field['help']) ?></span>
                        <?php endif; ?>

                        <div class="mt-3 grid gap-3">
                            <?php
                            $rows = is_array($value) ? array_values($value) : [];
                            // Always one empty slot beyond what exists, so there
                            // is somewhere to add the next one without a button.
                            $slots = min((int) ($field['max'] ?? 6), max(count($rows) + 1, 3));
                            ?>
                            <?php for ($i = 0; $i < $slots; $i++): ?>
                                <?php $row = $rows[$i] ?? []; ?>
                                <div class="grid gap-2 border border-shell-line bg-shell-deep/40 p-3 sm:grid-cols-2">
                                    <?php foreach ($field['fields'] as $subKey => $sub): ?>
                                        <label class="<?= in_array($sub['type'], ['textarea', 'products'], true) ? 'sm:col-span-2' : '' ?>">
                                            <span class="rs-label"><?= esc($sub['label']) ?></span>
                                            <?php $subName = $name . '[' . $i . '][' . $subKey . ']'; ?>

                                            <?php if ($sub['type'] === 'products'): ?>
                                                <?php $rsProductPicker($subName, array_map('intval', (array) ($row[$subKey] ?? []))) ?>

                                            <?php elseif ($sub['type'] === 'textarea'): ?>
                                                <textarea name="<?= esc($subName, 'attr') ?>" class="rs-textarea"
                                                          rows="2" maxlength="1000"><?= esc((string) ($row[$subKey] ?? '')) ?></textarea>
                                            <?php else: ?>
                                                <input type="text" name="<?= esc($subName, 'attr') ?>" class="rs-input"
                                                       maxlength="255" value="<?= esc((string) ($row[$subKey] ?? ''), 'attr') ?>">
                                            <?php endif; ?>

                                            <?php if (! empty($sub['help'])): ?>
                                                <span class="rs-help"><?= esc($sub['help']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                <?php elseif ($field['type'] === 'products'): ?>
                    <span class="rs-label"><?= esc($field['label']) ?></span>
                    <?php $rsProductPicker($name, array_map('intval', (array) $value)) ?>

                <?php elseif ($field['type'] === 'lines'): ?>
                    <?php
                    // One phrase per line — the same shape the marquee and the
                    // search placeholders already use.
                    $items = array_values(array_filter(array_map(
                        'trim',
                        preg_split('/\R/u', (string) $value) ?: []
                    )));
                    ?>
                    <label>
                        <span class="rs-label"><?= esc($field['label']) ?></span>
                        <textarea name="<?= esc($name, 'attr') ?>" class="rs-textarea font-mono text-xs"
                                  rows="5" maxlength="2000"><?= esc(implode("\n", $items)) ?></textarea>
                        <span class="rs-help">
                            One per line &mdash; <span class="num"><?= count($items) ?></span> at the moment.
                        </span>
                    </label>

                <?php elseif ($field['type'] === 'occasions'): ?>
                    <?php
                    /*
                     * A tick list of the live occasions.
                     *
                     * Not free-typed rows: an occasion already has a name, a
                     * picture and a URL, and asking someone to retype all three
                     * is three chances to get it wrong and no way to notice
                     * when the occasion is later renamed.
                     */
                    $chosen = array_map('intval', (array) $value);
                    $live   = model(\App\Models\CollectionModel::class)->occasions(false, true);
                    ?>
                    <span class="rs-label"><?= esc($field['label']) ?></span>

                    <?php if ($live === []): ?>
                        <p class="rs-help">
                            No occasions yet &mdash;
                            <a href="<?= site_url('admin/occasions/new') ?>" class="rs-link text-mulberry">add one</a>.
                        </p>
                    <?php else: ?>
                        <ul class="mt-2 flex flex-wrap gap-2">
                            <?php foreach ($live as $row): ?>
                                <?php $on = in_array((int) $row['id'], $chosen, true); ?>
                                <li>
                                    <label class="flex cursor-pointer items-center gap-2 border px-2.5 py-1.5 text-sm
                                                  <?= $on ? 'border-mulberry bg-brass-soft/30' : 'border-shell-line' ?>">
                                        <input type="checkbox" class="accent-mulberry"
                                               name="<?= esc($name, 'attr') ?>[]" value="<?= (int) $row['id'] ?>"
                                               <?= $on ? 'checked' : '' ?>>
                                        <span><?= esc($row['name']) ?></span>
                                        <?php if ((int) $row['is_active'] !== 1): ?>
                                            <span class="rs-help">off</span>
                                        <?php endif; ?>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (! empty($field['help'])): ?>
                        <span class="rs-help"><?= esc($field['help']) ?></span>
                    <?php endif; ?>

                <?php elseif ($field['type'] === 'image'): ?>
                    <label>
                        <span class="rs-label"><?= esc($field['label']) ?></span>
                        <?php if ((string) $value !== ''): ?>
                            <span class="mt-2 mb-2 block max-h-40 overflow-hidden border border-shell-line bg-shell-deep">
                                <img src="<?= rs_image((string) $value, 'content') ?>" alt=""
                                     class="w-full object-cover">
                            </span>
                        <?php endif; ?>
                        <input type="file" name="data_image_<?= esc($sectionKey . '_' . $fieldKey, 'attr') ?>"
                               class="rs-input" accept="image/jpeg,image/png,image/webp">
                        <?php /* The current path rides along, so saving without
                                 choosing a new file keeps the old one. */ ?>
                        <input type="hidden" name="<?= esc($name, 'attr') ?>" value="<?= esc((string) $value, 'attr') ?>">
                        <?php if (! empty($field['help'])): ?>
                            <span class="rs-help"><?= esc($field['help']) ?></span>
                        <?php endif; ?>
                    </label>

                <?php elseif ($field['type'] === 'textarea'): ?>
                    <label>
                        <span class="rs-label"><?= esc($field['label']) ?></span>
                        <textarea name="<?= esc($name, 'attr') ?>" class="rs-textarea" rows="3"
                                  maxlength="2000"><?= esc((string) $value) ?></textarea>
                        <?php if (! empty($field['help'])): ?>
                            <span class="rs-help"><?= esc($field['help']) ?></span>
                        <?php endif; ?>
                    </label>

                <?php else: ?>
                    <label>
                        <span class="rs-label"><?= esc($field['label']) ?></span>
                        <input type="text" name="<?= esc($name, 'attr') ?>" class="rs-input"
                               maxlength="255" value="<?= esc((string) $value, 'attr') ?>">
                        <?php if (! empty($field['help'])): ?>
                            <span class="rs-help"><?= esc($field['help']) ?></span>
                        <?php endif; ?>
                    </label>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>
