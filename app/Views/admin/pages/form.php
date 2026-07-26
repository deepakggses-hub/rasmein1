<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
$isNew = $page === null;
$v = static fn (string $f, $fb = '') => esc((string) (old($f) ?? $fb), 'attr');
?>

<?= view('admin/partials/header', [
    'eyebrow' => 'Content',
    'heading' => $isNew ? 'New page' : $page['title'],
    'actions' => '<a href="' . site_url('admin/pages') . '" class="rs-btn rs-btn--outline rs-btn--sm">All pages</a>'
        . ($isNew ? '' : '<a href="' . site_url('page/' . $page['slug']) . '" target="_blank" rel="noopener" class="rs-btn rs-btn--outline rs-btn--sm">View</a>'),
]) ?>

<form method="post" action="<?= $isNew ? site_url('admin/pages') : site_url('admin/pages/' . $page['id']) ?>"
      class="px-5 py-6 lg:px-8">
    <?= csrf_field() ?>

    <div class="grid gap-6 lg:grid-cols-[1fr_18rem] lg:items-start">
        <section class="border border-shell-line bg-white p-5">
            <label class="block">
                <span class="rs-label">Title <span class="text-bad">*</span></span>
                <input type="text" name="title" class="rs-input" required maxlength="191"
                       value="<?= $v('title', $page['title'] ?? '') ?>">
            </label>
            <label class="mt-4 block">
                <span class="rs-label">URL slug</span>
                <input type="text" name="slug" class="rs-input" maxlength="160"
                       value="<?= $v('slug', $page['slug'] ?? '') ?>">
            </label>
            <label class="mt-4 block">
                <span class="rs-label">Excerpt</span>
                <input type="text" name="excerpt" class="rs-input" maxlength="255"
                       value="<?= $v('excerpt', $page['excerpt'] ?? '') ?>">
                <span class="rs-help">One line under the heading.</span>
            </label>
            <label class="mt-4 block">
                <span class="rs-label">Content</span>
                <textarea name="content" class="rs-textarea font-mono text-xs" rows="22"><?= esc(old('content') ?? $page['content'] ?? '') ?></textarea>
                <span class="rs-help">
                    Basic HTML: <code>&lt;p&gt; &lt;h2&gt; &lt;h3&gt; &lt;ul&gt; &lt;ol&gt; &lt;li&gt;
                    &lt;strong&gt; &lt;em&gt; &lt;a&gt; &lt;blockquote&gt; &lt;table&gt;</code>.
                    Anything else — scripts, styles, event handlers, iframes — is stripped
                    on save, and external links get <code>rel="noopener"</code> automatically.
                </span>
            </label>
        </section>

        <aside class="space-y-5">
            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Visibility</h2>
                <label class="mt-4 flex items-center gap-2.5 text-sm">
                    <input type="checkbox" name="is_active" value="1" class="accent-mulberry"
                           <?= ($isNew || $page['is_active']) ? 'checked' : '' ?>>
                    <span>Live on the storefront</span>
                </label>
                <label class="mt-3 flex items-center gap-2.5 text-sm">
                    <input type="checkbox" name="show_in_footer" value="1" class="accent-mulberry"
                           <?= (! $isNew && $page['show_in_footer']) ? 'checked' : '' ?>>
                    <span>Link from the footer</span>
                </label>
                <label class="mt-4 block">
                    <span class="rs-label">Sort order</span>
                    <input type="number" name="sort_order" class="rs-input num"
                           value="<?= $v('sort_order', (string) ($page['sort_order'] ?? 0)) ?>">
                </label>
            </section>

            <section class="border border-shell-line bg-white p-5">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Search listing</h2>
                <label class="mt-4 block">
                    <span class="rs-label">Meta title</span>
                    <input type="text" name="meta_title" class="rs-input" maxlength="191"
                           value="<?= $v('meta_title', $page['meta_title'] ?? '') ?>">
                </label>
                <label class="mt-4 block">
                    <span class="rs-label">Meta description</span>
                    <input type="text" name="meta_description" class="rs-input" maxlength="255"
                           value="<?= $v('meta_description', $page['meta_description'] ?? '') ?>">
                </label>
            </section>

            <button type="submit" class="rs-btn rs-btn--primary w-full">
                <?= $isNew ? 'Create page' : 'Save page' ?>
            </button>
        </aside>
    </div>
</form>

<?php if (! $isNew): ?>
    <div class="px-5 pb-8 lg:px-8">
        <form method="post" action="<?= site_url('admin/pages/' . $page['id'] . '/delete') ?>"
              onsubmit="return confirm('Remove this page?');">
            <?= csrf_field() ?>
            <button type="submit" class="rs-link text-sm text-ink-muted hover:text-bad">Remove this page</button>
        </form>
    </div>
<?php endif; ?>

<?= $this->endSection() ?>
