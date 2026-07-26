<?php
/**
 * Rich text editor field.
 *
 * WHY QUILL, AND NOT CKEDITOR OR TINYMCE
 *
 * Licensing, not features. The free tiers of both CKEditor 5 and TinyMCE are
 * GPL-2.0-or-later — a copyleft licence. Shipping GPL code inside Rasmein would
 * oblige Rasmein itself to be GPL-licensed, which is not what a commercial shop
 * wants. Their commercial licences are a recurring cost, and CKEditor's GPL tier
 * also renders a "Powered by CKEditor" mark inside the editor and requires a
 * licence key even to run free.
 *
 * Quill 2 is BSD-3-Clause: permissive, no key, no branding, no usage cap, and
 * about 200 KB against CKEditor's ~500 KB. It is vendored into
 * public/assets/vendor/quill so there is no CDN dependency at runtime.
 *
 * THE TOOLBAR MATCHES THE SANITISER ON PURPOSE
 *
 * HtmlSanitiser strips class and style attributes, so Quill's alignment and
 * indentation — which it expresses as ql-* classes — would be silently thrown
 * away on save. Offering a button whose effect disappears is worse than not
 * offering it, so those controls are absent. Every button here produces markup
 * that survives sanitising.
 *
 * AND THE EDITOR IS NOT A SECURITY CONTROL
 *
 * It runs in the browser, so it can be bypassed entirely. Server-side
 * sanitising on save is what actually protects the output; this only makes
 * writing pleasant.
 *
 * @var string      $name     Form field name
 * @var string      $value    Current HTML
 * @var string|null $label
 * @var string|null $help
 * @var int         $rows     Height, in the same units a textarea would use
 */
$name  = $name  ?? 'content';
$value = $value ?? '';
$rows  = (int) ($rows ?? 14);
$id    = 'editor-' . preg_replace('/[^a-z0-9]+/i', '-', $name);
?>
<div class="rs-editor" data-editor data-editor-target="<?= esc($id, 'attr') ?>">
    <?php if (! empty($label)): ?>
        <span class="rs-label"><?= esc($label) ?></span>
    <?php endif; ?>

    <?php /* The textarea is the real field. Quill writes back into it on every
             change, so the form submits normally and works with JS disabled. */ ?>
    <textarea id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>"
              class="rs-textarea font-mono text-xs" rows="<?= $rows ?>"
              data-editor-input><?= esc($value) ?></textarea>

    <?php if (! empty($help)): ?>
        <span class="rs-help"><?= $help ?></span>
    <?php endif; ?>
</div>
