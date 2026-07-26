<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php $v = static fn (string $f, $fb = '') => esc((string) (old($f) ?? $fb), 'attr'); ?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'Email template',
    'heading'    => $template['name'],
    'subheading' => 'Key: ' . $template['template_key'] . ' · sent to '
        . ($template['audience'] === 'admin' ? 'the team' : 'customers'),
    'actions'    => '<a href="' . site_url('admin/email-templates') . '" class="rs-btn rs-btn--outline rs-btn--sm">All templates</a>',
]) ?>

<div class="grid gap-6 px-5 py-6 lg:grid-cols-[1fr_20rem] lg:items-start lg:px-8">

    <div class="space-y-6">
        <form method="post" action="<?= site_url('admin/email-templates/' . $template['id']) ?>"
              class="border border-shell-line bg-white p-5">
            <?= csrf_field() ?>

            <label class="block">
                <span class="rs-label">Internal name</span>
                <input type="text" name="name" class="rs-input" required maxlength="120"
                       value="<?= $v('name', $template['name']) ?>">
                <span class="rs-help">Only shown here, to help you find it.</span>
            </label>

            <label class="mt-5 block">
                <span class="rs-label">Subject line <span class="text-bad">*</span></span>
                <input type="text" name="subject" class="rs-input" required maxlength="255"
                       value="<?= $v('subject', $template['subject']) ?>">
                <span class="rs-help">Plain text. Placeholders work here too.</span>
            </label>

            <label class="mt-5 block">
                <span class="rs-label">Body</span>
                <textarea name="body_html" class="rs-textarea font-mono text-xs" rows="18"><?= esc(old('body_html') ?? $template['body_html'] ?? '') ?></textarea>
                <span class="rs-help">
                    Basic HTML only — <code>&lt;p&gt; &lt;strong&gt; &lt;em&gt; &lt;a&gt; &lt;ul&gt; &lt;li&gt;
                    &lt;h2&gt; &lt;h3&gt;</code>. Scripts, styles and iframes are stripped on save.
                    The brand header and footer are added automatically.
                </span>
            </label>

            <label class="mt-5 flex items-center gap-2.5 text-sm">
                <input type="checkbox" name="is_active" value="1" class="accent-mulberry"
                       <?= $template['is_active'] ? 'checked' : '' ?>>
                <span>Send this email</span>
            </label>
            <?php if ($template['is_active']): ?>
                <p class="rs-help">Untick to stop it going out without deleting the wording.</p>
            <?php endif; ?>

            <button type="submit" class="rs-btn rs-btn--primary mt-6">Save template</button>
        </form>

        <!-- Preview, rendered with plausible sample values. -->
        <section class="border border-shell-line bg-white">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-shell-line px-5 py-3">
                <h2 class="rs-eyebrow rs-eyebrow--plain">Preview</h2>
                <form method="post" action="<?= site_url('admin/email-templates/' . $template['id'] . '/test') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="rs-btn rs-btn--outline rs-btn--sm">
                        Send a test to me
                    </button>
                </form>
            </div>
            <div class="px-5 py-4">
                <p class="text-sm">
                    <span class="font-mono text-[0.5625rem] tracking-[0.14em] text-ink-muted uppercase">Subject</span><br>
                    <span class="font-semibold"><?= esc($preview['subject']) ?></span>
                </p>
                <div class="mt-4 overflow-hidden border border-shell-line">
                    <?php /* srcdoc + sandbox: the preview cannot run anything or reach the parent page. */ ?>
                    <iframe title="Email preview" sandbox="" class="h-96 w-full bg-white"
                            srcdoc="<?= esc($preview['html'], 'attr') ?>"></iframe>
                </div>
                <details class="mt-4">
                    <summary class="cursor-pointer font-mono text-[0.625rem] tracking-widest text-brass uppercase">
                        Plain-text version
                    </summary>
                    <pre class="mt-2 overflow-x-auto bg-shell-deep p-3 text-xs whitespace-pre-wrap"><?= esc($preview['text']) ?></pre>
                </details>
            </div>
        </section>
    </div>

    <aside class="space-y-5">
        <section class="border border-shell-line bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">Placeholders</h2>
            <p class="rs-help mt-2">
                Type these into the subject or body and they are replaced when the
                email is sent. Anything else in braces renders as nothing.
            </p>
            <dl class="mt-4 space-y-2.5 text-sm">
                <?php foreach ($placeholders as $token => $description): ?>
                    <div>
                        <dt><code class="bg-shell-deep px-1 font-mono text-xs">{{<?= esc($token) ?>}}</code></dt>
                        <dd class="mt-0.5 text-xs text-ink-muted"><?= esc($description) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>

        <section class="border border-shell-line bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">Always available</h2>
            <dl class="mt-4 space-y-2 text-xs">
                <?php foreach ([
                    'brand_name'    => 'The shop name',
                    'support_email' => 'Support email address',
                    'support_phone' => 'Support phone number',
                    'site_url'      => 'The site address',
                    'year'          => 'Current year',
                ] as $token => $description): ?>
                    <div class="flex justify-between gap-3">
                        <dt><code class="bg-shell-deep px-1 font-mono">{{<?= esc($token) ?>}}</code></dt>
                        <dd class="text-ink-muted"><?= esc($description) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>
    </aside>
</div>

<?= $this->endSection() ?>
