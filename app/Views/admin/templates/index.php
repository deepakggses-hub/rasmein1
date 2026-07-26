<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Content',
    'heading'    => 'Email templates',
    'subheading' => $total . ' template' . ($total === 1 ? '' : 's') . '. Edit the wording; the code sends by key.',
    'actions'    => '<form method="post" action="' . site_url('admin/email-templates/restore') . '">'
        . csrf_field()
        . '<button type="submit" class="rs-btn rs-btn--outline rs-btn--sm">Install missing templates</button>'
        . '</form>',
]) ?>

<div class="space-y-6 px-5 py-6 lg:px-8">

    <?php if ($total === 0): ?>
        <!-- An empty list is a dead end unless it says what to do about it. -->
        <section class="border-2 border-brass bg-brass-soft/25 p-6">
            <h2 class="font-display text-xl font-semibold">No templates are installed yet.</h2>
            <p class="mt-2 max-w-2xl text-sm text-ink-soft">
                Templates arrive with the seed data. If the database was migrated but not
                seeded, this list is empty and no email can be sent — the code looks each
                one up by key and skips it when it is missing.
            </p>
            <form method="post" action="<?= site_url('admin/email-templates/restore') ?>" class="mt-4">
                <?= csrf_field() ?>
                <button type="submit" class="rs-btn rs-btn--primary">Install the standard templates</button>
            </form>
            <p class="rs-help mt-3">
                Equivalent on the command line:
                <code class="bg-white px-1 font-mono">php spark db:seed EmailTemplateSeeder</code>
            </p>
        </section>
    <?php endif; ?>

    <?php foreach (['customer' => 'Sent to customers', 'admin' => 'Sent to the team'] as $audience => $label): ?>
        <section>
            <h2 class="rs-eyebrow"><?= esc($label) ?></h2>
            <div class="mt-4 overflow-x-auto border border-shell-line bg-white">
                <?php if ($grouped[$audience] === []): ?>
                    <p class="px-4 py-6 text-sm text-ink-muted">None.</p>
                <?php else: ?>
                    <table class="w-full min-w-3xl text-sm">
                        <thead class="border-b border-shell-line bg-shell-deep text-left">
                            <tr class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">
                                <th class="px-4 py-2.5">Template</th>
                                <th class="px-4 py-2.5">Subject</th>
                                <th class="px-4 py-2.5">State</th>
                                <th class="px-4 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-shell-line">
                            <?php foreach ($grouped[$audience] as $template): ?>
                                <tr class="hover:bg-shell">
                                    <td class="px-4 py-2.5">
                                        <a href="<?= site_url('admin/email-templates/' . $template['id'] . '/edit') ?>"
                                           class="rs-link font-medium"><?= esc($template['name']) ?></a>
                                        <span class="block font-mono text-[0.5625rem] text-ink-muted">
                                            <?= esc($template['template_key']) ?>
                                        </span>
                                        <?php if (! empty($template['description'])): ?>
                                            <span class="rs-help"><?= esc($template['description']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2.5 text-ink-muted"><?= esc(rs_excerpt($template['subject'], 44)) ?></td>
                                    <td class="px-4 py-2.5">
                                        <span class="rs-badge <?= $template['is_active'] ? 'rs-badge--enquire' : 'rs-badge--out' ?>">
                                            <?= $template['is_active'] ? 'Sending' : 'Off' ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <a href="<?= site_url('admin/email-templates/' . $template['id'] . '/edit') ?>"
                                           class="rs-btn rs-btn--outline rs-btn--sm">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<?= $this->endSection() ?>
