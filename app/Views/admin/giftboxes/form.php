<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/** @var \App\Entities\GiftBox|null $box */
$isNew = $box === null;
$id    = $isNew ? 0 : (int) $box->id;
$v = static fn (string $f, $fb = '') => esc((string) (old($f) ?? $fb), 'attr');
$on = static fn (string $f, bool $fb): string => (old($f) !== null ? true : $fb) ? 'checked' : '';

$ruleTypes = [
    'flat_box_price'        => 'Box costs a flat amount',
    'waive_box_price'       => 'Box is free (above a contents subtotal)',
    'percent_markup'        => 'Add a % on top of contents',
    'slot_discount_percent' => 'Take a % off contents',
    'slot_discount_amount'  => 'Take a flat amount off contents',
];
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Gift box',
    'heading'    => $isNew ? 'New gift box' : $box->name,
    'subheading' => $isNew ? null : $reach . ' product' . ($reach === 1 ? '' : 's') . ' currently qualify for this box.',
    'actions'    => '<a href="' . site_url('admin/gift-boxes') . '" class="rs-btn rs-btn--outline rs-btn--sm">All boxes</a>',
]) ?>

<div class="space-y-6 px-5 py-6 lg:px-8">

    <!-- ============================= basics ============================ -->
    <form method="post" enctype="multipart/form-data"
          action="<?= $isNew ? site_url('admin/gift-boxes') : site_url('admin/gift-boxes/' . $id) ?>">
        <?= csrf_field() ?>

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem] lg:items-start">
            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">The box</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="sm:col-span-2">
                        <span class="rs-label">Name <span class="text-bad">*</span></span>
                        <input type="text" name="name" class="rs-input" required maxlength="120"
                               value="<?= $v('name', $box->name ?? '') ?>">
                    </label>
                    <label>
                        <span class="rs-label">URL slug</span>
                        <input type="text" name="slug" class="rs-input" maxlength="160"
                               value="<?= $v('slug', $box->slug ?? '') ?>">
                    </label>
                    <label>
                        <span class="rs-label">Size label</span>
                        <input type="text" name="size_label" class="rs-input" maxlength="60" placeholder="Medium"
                               value="<?= $v('size_label', $box->size_label ?? '') ?>">
                    </label>
                    <label>
                        <span class="rs-label">Theme</span>
                        <input type="text" name="theme" class="rs-input" maxlength="60" placeholder="Festive"
                               value="<?= $v('theme', $box->theme ?? '') ?>">
                    </label>
                    <label>
                        <span class="rs-label">Price tier label</span>
                        <input type="text" name="price_tier" class="rs-input" maxlength="40" placeholder="1500 – 4000"
                               value="<?= $v('price_tier', $box->price_tier ?? '') ?>">
                    </label>
                    <label class="sm:col-span-2">
                        <span class="rs-label">Description</span>
                        <textarea name="description" class="rs-textarea" rows="3"><?= esc(old('description') ?? $box->description ?? '') ?></textarea>
                    </label>
                    <label class="sm:col-span-2">
                        <span class="rs-label">Photograph</span>
                        <input type="file" name="image" class="rs-input" accept="image/jpeg,image/png,image/webp">
                    </label>
                    <label>
                        <span class="rs-label">Meta title</span>
                        <input type="text" name="meta_title" class="rs-input" maxlength="191"
                               value="<?= $v('meta_title', $box->meta_title ?? '') ?>">
                    </label>
                    <label>
                        <span class="rs-label">Meta description</span>
                        <input type="text" name="meta_description" class="rs-input" maxlength="255"
                               value="<?= $v('meta_description', $box->meta_description ?? '') ?>">
                    </label>
                </div>
            </section>

            <aside class="space-y-5">
                <section class="border border-shell-line bg-white p-5">
                    <h2 class="rs-eyebrow rs-eyebrow--plain">Capacity</h2>
                    <label class="mt-4 block">
                        <span class="rs-label">Compartments <span class="text-bad">*</span></span>
                        <input type="number" name="capacity_slots" class="rs-input num" min="1" max="24" required
                               value="<?= $v('capacity_slots', (string) ($box->capacity_slots ?? 6)) ?>">
                        <span class="rs-help">Up to 24. This is what the tray draws.</span>
                    </label>
                    <label class="mt-4 block">
                        <span class="rs-label">Must fill at least</span>
                        <input type="number" name="min_slots" class="rs-input num" min="0" max="24"
                               value="<?= $v('min_slots', (string) ($box->min_slots ?? 1)) ?>">
                        <span class="rs-help">
                            Below this the box cannot be checked out. Clamped to the
                            capacity if you set it higher.
                        </span>
                    </label>
                    <label class="mt-4 block">
                        <span class="rs-label">Box &amp; packing price</span>
                        <input type="number" name="base_price" class="rs-input num" step="0.01" min="0"
                               value="<?= $v('base_price', (string) ($box->base_price ?? 0)) ?>">
                        <span class="rs-help">Charged on top of the contents, unless a rule below overrides it.</span>
                    </label>
                </section>

                <section class="border border-shell-line bg-white p-5">
                    <h2 class="rs-eyebrow rs-eyebrow--plain">Personalisation</h2>
                    <label class="mt-4 flex items-center gap-2.5 text-sm">
                        <input type="checkbox" name="allow_gift_message" value="1" class="accent-mulberry"
                               <?= $on('allow_gift_message', (bool) ($box->allow_gift_message ?? true)) ?>>
                        <span>Offer a gift message</span>
                    </label>
                    <label class="mt-4 block">
                        <span class="rs-label">Message limit</span>
                        <input type="number" name="gift_message_max_chars" class="rs-input num" min="1" max="1000"
                               value="<?= $v('gift_message_max_chars', (string) ($box->gift_message_max_chars ?? 300)) ?>">
                    </label>
                    <label class="mt-4 flex items-center gap-2.5 text-sm">
                        <input type="checkbox" name="allow_special_note" value="1" class="accent-mulberry"
                               <?= $on('allow_special_note', (bool) ($box->allow_special_note ?? true)) ?>>
                        <span>Offer a special request field</span>
                    </label>
                </section>

                <section class="border border-shell-line bg-white p-5">
                    <h2 class="rs-eyebrow rs-eyebrow--plain">How it sells</h2>
                    <label class="mt-4 block">
                        <span class="rs-label">Journey</span>
                        <select name="sale_mode" class="rs-select">
                            <?php foreach ([
                                'inherit' => 'Follow the store setting',
                                'buy_now' => 'Always Buy now',
                                'enquire_now' => 'Always quoted (Enquire)',
                            ] as $k => $label): ?>
                                <option value="<?= $k ?>" <?= (old('sale_mode') ?? $box->sale_mode ?? 'inherit') === $k ? 'selected' : '' ?>>
                                    <?= esc($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="mt-4 flex items-center gap-2.5 text-sm">
                        <input type="checkbox" name="is_active" value="1" class="accent-mulberry"
                               <?= $on('is_active', (bool) ($box->is_active ?? true)) ?>>
                        <span>Live on the storefront</span>
                    </label>
                    <label class="mt-3 flex items-center gap-2.5 text-sm">
                        <input type="checkbox" name="is_featured" value="1" class="accent-mulberry"
                               <?= $on('is_featured', (bool) ($box->is_featured ?? false)) ?>>
                        <span>Featured on the homepage</span>
                    </label>
                    <label class="mt-4 block">
                        <span class="rs-label">Sort order</span>
                        <input type="number" name="sort_order" class="rs-input num"
                               value="<?= $v('sort_order', (string) ($box->sort_order ?? 0)) ?>">
                    </label>
                </section>

                <button type="submit" class="rs-btn rs-btn--primary w-full">
                    <?= $isNew ? 'Create gift box' : 'Save the box' ?>
                </button>
            </aside>
        </div>
    </form>

    <?php if (! $isNew): ?>
        <!-- ========================== contents ========================= -->
        <form method="post" action="<?= site_url('admin/gift-boxes/' . $id . '/contents') ?>" id="contents">
            <?= csrf_field() ?>
            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">What may go in</h2>
                <p class="rs-help mt-2 max-w-2xl">
                    Start from whole categories, then pin or exclude individual products.
                    <strong>Exclusion always wins.</strong> Tick no categories and every
                    gift-box-eligible product qualifies.
                </p>

                <fieldset class="mt-5">
                    <legend class="rs-label">Categories</legend>
                    <ul class="mt-2 grid gap-2 sm:grid-cols-3 lg:grid-cols-4">
                        <?php foreach ($categories as $category): ?>
                            <li>
                                <label class="flex items-center gap-2.5 text-sm">
                                    <input type="checkbox" name="categories[]" value="<?= (int) $category->id ?>"
                                           class="accent-mulberry"
                                           <?= in_array((int) $category->id, $selectedCategories, true) ? 'checked' : '' ?>>
                                    <span><?= esc($category->name) ?></span>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </fieldset>

                <fieldset class="mt-6">
                    <legend class="rs-label">Individual products</legend>
                    <div class="mt-2 max-h-96 overflow-y-auto border border-shell-line">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 border-b border-shell-line bg-shell-deep text-left">
                                <tr class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">
                                    <th class="px-3 py-2">Product</th>
                                    <th class="num px-3 py-2 text-right">Slots</th>
                                    <th class="px-3 py-2">Follow categories</th>
                                    <th class="px-3 py-2">Always allow</th>
                                    <th class="px-3 py-2">Never allow</th>
                                    <th class="num px-3 py-2 text-right">Max per box</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-shell-line">
                                <?php foreach ($products as $product): ?>
                                    <?php $pin = $pins[(int) $product->id] ?? null; ?>
                                    <tr class="hover:bg-shell">
                                        <td class="px-3 py-1.5">
                                            <?= esc(rs_excerpt($product->name, 34)) ?>
                                            <?php if (! $product->is_giftbox_eligible): ?>
                                                <span class="rs-badge rs-badge--out ml-1">Not giftable</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="num px-3 py-1.5 text-right text-ink-muted"><?= (int) $product->giftbox_slots ?></td>
                                        <td class="px-3 py-1.5">
                                            <input type="radio" name="pin[<?= (int) $product->id ?>]" value=""
                                                   class="accent-mulberry" <?= $pin === null ? 'checked' : '' ?>>
                                        </td>
                                        <td class="px-3 py-1.5">
                                            <input type="radio" name="pin[<?= (int) $product->id ?>]" value="allow"
                                                   class="accent-mulberry" <?= ($pin['mode'] ?? '') === 'allow' ? 'checked' : '' ?>>
                                        </td>
                                        <td class="px-3 py-1.5">
                                            <input type="radio" name="pin[<?= (int) $product->id ?>]" value="exclude"
                                                   class="accent-mulberry" <?= ($pin['mode'] ?? '') === 'exclude' ? 'checked' : '' ?>>
                                        </td>
                                        <td class="px-3 py-1.5 text-right">
                                            <input type="number" name="cap[<?= (int) $product->id ?>]"
                                                   class="rs-input num w-16 py-1 text-center" min="1" max="24"
                                                   value="<?= esc((string) ($pin['cap'] ?? ''), 'attr') ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </fieldset>

                <button type="submit" class="rs-btn rs-btn--primary mt-5">Save what may go in</button>
            </section>
        </form>

        <!-- ========================== pricing ========================== -->
        <form method="post" action="<?= site_url('admin/gift-boxes/' . $id . '/rules') ?>" id="pricing">
            <?= csrf_field() ?>
            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Pricing rules</h2>
                <p class="rs-help mt-2 max-w-2xl">
                    Applied in priority order, lowest first. Leave the type blank to
                    delete a row. A rule only fires when the box's filled slots and
                    contents subtotal fall inside its range.
                </p>

                <div class="mt-5 overflow-x-auto">
                    <table class="w-full min-w-3xl text-sm">
                        <thead class="border-b border-shell-line bg-shell-deep text-left">
                            <tr class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">
                                <th class="px-3 py-2">Rule</th>
                                <th class="num px-3 py-2">Value</th>
                                <th class="num px-3 py-2">Slots from</th>
                                <th class="num px-3 py-2">to</th>
                                <th class="num px-3 py-2">Contents over</th>
                                <th class="px-3 py-2">Label shown to customer</th>
                                <th class="num px-3 py-2">Order</th>
                                <th class="px-3 py-2">On</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-shell-line">
                            <?php
                            // Existing rows, plus three blanks to add more.
                            $rows = $allRules;
                            for ($i = 0; $i < 3; $i++) { $rows[] = null; }
                            foreach ($rows as $index => $rule):
                            ?>
                                <tr>
                                    <td class="px-3 py-1.5">
                                        <select name="rules[<?= $index ?>][rule_type]" class="rs-select">
                                            <option value="">— none —</option>
                                            <?php foreach ($ruleTypes as $key => $label): ?>
                                                <option value="<?= $key ?>" <?= ($rule['rule_type'] ?? '') === $key ? 'selected' : '' ?>>
                                                    <?= esc($label) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="px-3 py-1.5">
                                        <input type="number" name="rules[<?= $index ?>][value]" step="0.01" min="0"
                                               class="rs-input num w-24" value="<?= esc((string) ($rule['value'] ?? ''), 'attr') ?>">
                                    </td>
                                    <td class="px-3 py-1.5">
                                        <input type="number" name="rules[<?= $index ?>][min_slots]" min="0" max="24"
                                               class="rs-input num w-16" value="<?= esc((string) ($rule['min_slots'] ?? ''), 'attr') ?>">
                                    </td>
                                    <td class="px-3 py-1.5">
                                        <input type="number" name="rules[<?= $index ?>][max_slots]" min="0" max="24"
                                               class="rs-input num w-16" value="<?= esc((string) ($rule['max_slots'] ?? ''), 'attr') ?>">
                                    </td>
                                    <td class="px-3 py-1.5">
                                        <input type="number" name="rules[<?= $index ?>][min_subtotal]" step="0.01" min="0"
                                               class="rs-input num w-24" value="<?= esc((string) ($rule['min_subtotal'] ?? ''), 'attr') ?>">
                                    </td>
                                    <td class="px-3 py-1.5">
                                        <input type="text" name="rules[<?= $index ?>][label]" maxlength="120"
                                               class="rs-input" value="<?= esc((string) ($rule['label'] ?? ''), 'attr') ?>">
                                    </td>
                                    <td class="px-3 py-1.5">
                                        <input type="number" name="rules[<?= $index ?>][priority]"
                                               class="rs-input num w-16" value="<?= esc((string) ($rule['priority'] ?? $index + 1), 'attr') ?>">
                                    </td>
                                    <td class="px-3 py-1.5">
                                        <input type="checkbox" name="rules[<?= $index ?>][is_active]" value="1"
                                               class="accent-mulberry" <?= $rule === null || (int) $rule['is_active'] === 1 ? 'checked' : '' ?>>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="rs-btn rs-btn--primary mt-5">Save pricing rules</button>
            </section>
        </form>

        <form method="post" action="<?= site_url('admin/gift-boxes/' . $id . '/delete') ?>"
              onsubmit="return confirm('Remove this gift box? Past orders keep their record of it.');">
            <?= csrf_field() ?>
            <button type="submit" class="rs-link text-sm text-ink-muted hover:text-bad">Remove this gift box</button>
        </form>
    <?php else: ?>
        <p class="rs-help">Save the box first — then you can set what goes in it and how it is priced.</p>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
