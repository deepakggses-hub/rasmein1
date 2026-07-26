<?= $this->extend('admin/layouts/admin') ?>
<?= $this->section('content') ?>
<?php
/**
 * One panel, all three sending methods. Only the fields belonging to the chosen
 * method are shown — a form offering SMTP credentials while set to PHP mail()
 * invites people to fill in settings that will be ignored.
 */
$v = static fn (string $k, string $fb = '') => esc((string) (old($k) ?? $fb), 'attr');
$sel = old('mail_protocol') ?? $protocol;
?>

<?= view('admin/partials/header', [
    'eyebrow'    => 'System',
    'heading'    => 'Mail settings',
    'subheading' => 'How the shop sends order confirmations, enquiry alerts and password resets.',
    'actions'    => '<a href="' . site_url('admin/email-templates') . '" class="rs-btn rs-btn--outline rs-btn--sm">Email templates</a>',
]) ?>

<div class="grid gap-6 px-5 py-6 lg:grid-cols-[1fr_20rem] lg:items-start lg:px-8">

    <form method="post" action="<?= site_url('admin/mail') ?>" class="space-y-5">
        <?= csrf_field() ?>

        <!-- ------------------------------------------------ the method -->
        <section class="border border-shell-line bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">Sending method</h2>
            <p class="rs-help mt-2">One at a time. Only the settings for the chosen method are used.</p>

            <div class="mt-4 space-y-2">
                <?php foreach ($protocols as $key => $label): ?>
                    <label class="flex items-start gap-3 border <?= $sel === $key ? 'border-mulberry bg-shell' : 'border-shell-line' ?> px-4 py-3 text-sm">
                        <input type="radio" name="mail_protocol" value="<?= esc($key, 'attr') ?>"
                               class="mt-0.5 accent-mulberry" data-mail-protocol
                               <?= $sel === $key ? 'checked' : '' ?> <?= $canManage ? '' : 'disabled' ?>>
                        <span>
                            <span class="font-medium"><?= esc($label) ?></span>
                            <?php if ($key === 'mail'): ?>
                                <span class="rs-help">
                                    No authentication, so most inboxes treat it as spam. Use only if
                                    your host offers nothing else.
                                </span>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ------------------------------------------------- identity -->
        <section class="border border-shell-line bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">Who mail comes from</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label>
                    <span class="rs-label">From address</span>
                    <input type="email" name="mail_from_email" class="rs-input" maxlength="191"
                           placeholder="orders@rasmein.com" value="<?= $v('mail_from_email', $values['from_email']) ?>"
                           <?= $canManage ? '' : 'disabled' ?>>
                    <span class="rs-help">Must be on a domain the sending server may send for, or it lands in spam.</span>
                </label>
                <label>
                    <span class="rs-label">From name</span>
                    <input type="text" name="mail_from_name" class="rs-input" maxlength="120"
                           value="<?= $v('mail_from_name', $values['from_name']) ?>" <?= $canManage ? '' : 'disabled' ?>>
                </label>
                <label class="sm:col-span-2">
                    <span class="rs-label">Reply-to <span class="text-ink-muted">(optional)</span></span>
                    <input type="email" name="mail_reply_to" class="rs-input" maxlength="191"
                           value="<?= $v('mail_reply_to', $values['reply_to']) ?>" <?= $canManage ? '' : 'disabled' ?>>
                    <span class="rs-help">Where customer replies should go, if not the from address.</span>
                </label>
            </div>
        </section>

        <!-- ----------------------------------------------------- SMTP -->
        <section class="border border-shell-line bg-white p-5" data-mail-panel="smtp"
                 <?= $sel === 'smtp' ? '' : 'hidden' ?>>
            <h2 class="rs-eyebrow rs-eyebrow--plain">SMTP server</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <label class="sm:col-span-2">
                    <span class="rs-label">Host</span>
                    <input type="text" name="mail_smtp_host" class="rs-input" maxlength="191"
                           placeholder="smtp.zoho.in" value="<?= $v('mail_smtp_host', $values['smtp_host']) ?>"
                           <?= $canManage ? '' : 'disabled' ?>>
                </label>
                <label>
                    <span class="rs-label">Port</span>
                    <input type="number" name="mail_smtp_port" class="rs-input num" min="1" max="65535"
                           value="<?= $v('mail_smtp_port', $values['smtp_port']) ?>" <?= $canManage ? '' : 'disabled' ?>>
                </label>
                <label>
                    <span class="rs-label">Encryption</span>
                    <select name="mail_smtp_crypto" class="rs-select" <?= $canManage ? '' : 'disabled' ?>>
                        <?php foreach (['tls' => 'TLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None'] as $k => $label): ?>
                            <option value="<?= esc($k, 'attr') ?>"
                                <?= (old('mail_smtp_crypto') ?? $values['smtp_crypto']) === $k ? 'selected' : '' ?>>
                                <?= esc($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span class="rs-label">Username</span>
                    <input type="text" name="mail_smtp_user" class="rs-input" maxlength="191"
                           autocomplete="off" value="<?= $v('mail_smtp_user', $values['smtp_user']) ?>"
                           <?= $canManage ? '' : 'disabled' ?>>
                </label>

                <label>
                    <span class="rs-label">Password</span>
                    <?php /* Never rendered with a value — not even masked. A masked
                             value in the HTML is still in the HTML. */ ?>
                    <input type="password" name="mail_smtp_pass" class="rs-input" autocomplete="new-password"
                           placeholder="<?= $hasPassword ? '•••••••• stored' : 'not set' ?>"
                           <?= $canManage && $canEncrypt ? '' : 'disabled' ?>>
                    <?php if (! $canEncrypt): ?>
                        <span class="rs-help text-bad">
                            No encryption key is set, so a password cannot be stored safely.
                            Run <code class="font-mono">php spark key:generate</code> first.
                        </span>
                    <?php elseif ($hasPassword): ?>
                        <span class="rs-help">
                            A password is stored, encrypted. Leave blank to keep it — you can change
                            the host or port without re-typing it.
                        </span>
                    <?php else: ?>
                        <span class="rs-help">Stored encrypted, never shown again.</span>
                    <?php endif; ?>
                </label>

                <label>
                    <span class="rs-label">Timeout (seconds)</span>
                    <input type="number" name="mail_smtp_timeout" class="rs-input num" min="1" max="120"
                           value="<?= $v('mail_smtp_timeout', $values['smtp_timeout']) ?>" <?= $canManage ? '' : 'disabled' ?>>
                </label>
                <label>
                    <span class="rs-label">Authentication</span>
                    <select name="mail_smtp_auth_method" class="rs-select" <?= $canManage ? '' : 'disabled' ?>>
                        <?php foreach (['login' => 'Login', 'plain' => 'Plain', 'cram-md5' => 'CRAM-MD5'] as $k => $label): ?>
                            <option value="<?= $k ?>" <?= (old('mail_smtp_auth_method') ?? $values['smtp_auth_method']) === $k ? 'selected' : '' ?>>
                                <?= esc($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <?php if ($hasPassword && $canManage): ?>
                    <label class="sm:col-span-2 flex items-center gap-2.5 text-sm">
                        <input type="checkbox" name="clear_password" value="1" class="accent-mulberry">
                        <span>Forget the stored password</span>
                    </label>
                <?php endif; ?>
            </div>
        </section>

        <!-- ------------------------------------------------- sendmail -->
        <section class="border border-shell-line bg-white p-5" data-mail-panel="sendmail"
                 <?= $sel === 'sendmail' ? '' : 'hidden' ?>>
            <h2 class="rs-eyebrow rs-eyebrow--plain">Sendmail</h2>
            <label class="mt-4 block max-w-md">
                <span class="rs-label">Path to the binary</span>
                <input type="text" name="mail_sendmail_path" class="rs-input font-mono text-xs" maxlength="191"
                       value="<?= $v('mail_sendmail_path', $values['sendmail_path']) ?>" <?= $canManage ? '' : 'disabled' ?>>
                <span class="rs-help">Usually /usr/sbin/sendmail. Ask your host if unsure.</span>
            </label>
        </section>

        <!-- ----------------------------------------------- PHP mail() -->
        <section class="border border-shell-line bg-white p-5" data-mail-panel="mail"
                 <?= $sel === 'mail' ? '' : 'hidden' ?>>
            <h2 class="rs-eyebrow rs-eyebrow--plain">PHP mail()</h2>
            <p class="mt-2 max-w-xl text-sm text-ink-soft">
                Nothing to configure — PHP hands the message to whatever the server
                provides. It is the least reliable option: without authentication,
                Gmail and Outlook commonly file the mail as spam or reject it. Prefer
                SMTP wherever your host allows it.
            </p>
        </section>

        <section class="border border-shell-line bg-white p-5">
            <label class="flex items-center gap-2.5 text-sm">
                <input type="checkbox" name="mail_word_wrap" value="1" class="accent-mulberry"
                       <?= $values['word_wrap'] ? 'checked' : '' ?> <?= $canManage ? '' : 'disabled' ?>>
                <span>Wrap long lines in the plain-text part</span>
            </label>
        </section>

        <?php if ($canManage): ?>
            <button type="submit" class="rs-btn rs-btn--primary">Save mail settings</button>
        <?php else: ?>
            <p class="rs-help">Your role can view these but not change them.</p>
        <?php endif; ?>
    </form>

    <!-- ------------------------------------------------------ sidebar -->
    <aside class="space-y-5">
        <section class="border-2 <?= $lastTest !== '' && $lastError === '' ? 'border-pista-deep' : 'border-brass' ?> bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">Does it work?</h2>

            <?php if ($lastError !== ''): ?>
                <p class="mt-3 border-l-2 border-bad bg-rose/25 px-3 py-2 text-xs text-ink-soft">
                    <span class="font-semibold">Last failure:</span><br><?= esc($lastError) ?>
                </p>
            <?php elseif ($lastTest !== ''): ?>
                <p class="num mt-3 text-sm text-pista-deep">
                    Last successful test<br>
                    <span class="font-semibold"><?= esc(date('j M Y, H:i', strtotime($lastTest))) ?></span>
                </p>
            <?php else: ?>
                <p class="mt-3 text-sm text-ink-muted">Never tested.</p>
            <?php endif; ?>

            <?php if ($canManage): ?>
                <form method="post" action="<?= site_url('admin/mail/test') ?>" class="mt-4">
                    <?= csrf_field() ?>
                    <button type="submit" class="rs-btn rs-btn--primary w-full">Send a test to me</button>
                </form>
                <p class="rs-help mt-2">
                    Goes to <span class="font-medium"><?= esc($admin['email'] ?? '') ?></span> and nowhere else.
                    Save first — the test uses what is stored.
                </p>
            <?php endif; ?>
        </section>

        <section class="border border-shell-line bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">The queue</h2>
            <dl class="num mt-3 space-y-1.5 text-sm">
                <?php foreach ([
                    'queued' => 'Waiting to send',
                    'sent'   => 'Sent',
                    'failed' => 'Gave up',
                ] as $key => $label): ?>
                    <div class="flex justify-between gap-3">
                        <dt class="text-ink-muted"><?= esc($label) ?></dt>
                        <dd class="font-semibold <?= $key === 'failed' && $queue[$key] > 0 ? 'text-bad' : '' ?>">
                            <?= (int) $queue[$key] ?>
                        </dd>
                    </div>
                <?php endforeach; ?>
            </dl>

            <?php if ($canManage): ?>
                <form method="post" action="<?= site_url('admin/mail/drain') ?>" class="mt-4">
                    <?= csrf_field() ?>
                    <button type="submit" class="rs-btn rs-btn--outline rs-btn--sm w-full">Send the queue now</button>
                </form>
            <?php endif; ?>

            <p class="rs-help mt-3">
                Mail is queued, not sent as pages load, so a slow server never delays a
                checkout. In normal running cron drains it every five minutes —
                see <code class="font-mono">docs/DEPLOYMENT.md</code>.
            </p>
        </section>

        <section class="border border-shell-line bg-white p-5">
            <h2 class="rs-eyebrow rs-eyebrow--plain">If mail is refused</h2>
            <ul class="mt-3 space-y-2 text-xs text-ink-muted">
                <li>Port 587 goes with TLS; 465 goes with SSL. Mixing them is the usual cause of a timeout.</li>
                <li>Gmail and Zoho need an app-specific password, not your normal one.</li>
                <li>The from address must be on a domain the server may send for.</li>
                <li>Many hosts block outbound port 25 entirely.</li>
            </ul>
        </section>
    </aside>
</div>

<?= $this->endSection() ?>
