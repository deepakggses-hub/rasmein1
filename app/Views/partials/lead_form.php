<?php
/**
 * The enquiry form, shared by the story page and every collection page.
 *
 * One copy, because two would drift: a field added to one and forgotten on the
 * other is how a lead arrives missing its budget.
 *
 * @var string $title
 * @var string $body
 * @var string $formTitle
 * @var string $whatsapp
 * @var string $source
 */
$wa = preg_replace('/\D/', '', (string) ($whatsapp ?? '')) ?? '';
?>
    <section class="rs-shell rs-section">
        <div class="grid items-center gap-x-[clamp(2rem,6vw,5rem)] gap-y-10 lg:grid-cols-2">
            <div class="text-center lg:text-left">
                <h2 class="rs-display rs-display--lg"><?= esc($title) ?></h2>

                <?php if ($body !== ''): ?>
                    <p class="mx-auto mt-5 max-w-sm text-sm leading-relaxed text-ink-muted lg:mx-0">
                        <?= esc($body) ?>
                    </p>
                <?php endif; ?>
            </div>

            <?php /* The same enquiry endpoint the rest of the site uses, so a
                     lead from here lands in the same pipeline. */ ?>
            <form method="post" action="<?= site_url('enquiry/submit') ?>"
                  class="border border-shell-line bg-white p-[clamp(1.25rem,3vw,2rem)]">
                <?= csrf_field() ?>
                <input type="hidden" name="source" value="<?= esc($source, 'attr') ?>">

                <h3 class="font-display text-xl"><?= esc($formTitle) ?></h3>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <label>
                        <span class="rs-label">Name</span>
                        <input type="text" name="name" class="rs-input" required maxlength="120"
                               value="<?= esc(old('name') ?? '', 'attr') ?>">
                    </label>

                    <label>
                        <span class="rs-label">Occasion</span>
                        <input type="text" name="occasion" class="rs-input" maxlength="120"
                               value="<?= esc(old('occasion') ?? '', 'attr') ?>">
                    </label>

                    <label>
                        <span class="rs-label">Contact</span>
                        <input type="tel" name="phone" class="rs-input num" required maxlength="20"
                               value="<?= esc(old('phone') ?? '', 'attr') ?>">
                    </label>

                    <label>
                        <span class="rs-label">Email address</span>
                        <input type="email" name="email" class="rs-input" required maxlength="191"
                               value="<?= esc(old('email') ?? '', 'attr') ?>">
                    </label>

                    <label>
                        <span class="rs-label">Estimated no. of gifts</span>
                        <input type="number" name="quantity" class="rs-input num" min="1" max="100000"
                               value="<?= esc(old('quantity') ?? '', 'attr') ?>">
                    </label>

                    <label>
                        <span class="rs-label">Budget</span>
                        <input type="text" name="budget" class="rs-input" maxlength="60"
                               value="<?= esc(old('budget') ?? '', 'attr') ?>">
                    </label>

                    <label class="sm:col-span-2">
                        <span class="rs-label">Product brief</span>
                        <textarea name="message" class="rs-textarea" rows="3" maxlength="2000"
                                  placeholder="Message&hellip;"><?= esc(old('message') ?? '') ?></textarea>
                    </label>
                </div>

                <div class="mt-5 flex flex-wrap gap-3">
                    <button type="submit" class="rs-btn rs-btn--primary rs-btn--sm">Submit product details</button>

                    <?php if ($wa !== ''): ?>
                        <a href="https://wa.me/<?= esc($wa, 'attr') ?>" target="_blank" rel="noopener noreferrer"
                           class="rs-btn rs-btn--outline rs-btn--sm">
                            <?= rs_icon('whatsapp', 'h-4 w-4') ?>
                            Direct message
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </section>
