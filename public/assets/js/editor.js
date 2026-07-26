/**
 * Rich text editing in the admin panel — Quill 2 (BSD-3-Clause).
 *
 * Progressive by design: the real form field is a <textarea> that already
 * works. Quill is layered on top and writes back to it on every change, so a
 * failed script load, a blocked asset or a JS error leaves a usable HTML
 * textarea rather than a broken form.
 *
 * The toolbar is deliberately limited to formatting that survives the
 * server-side sanitiser. Quill expresses alignment and indentation as ql-*
 * classes, and HtmlSanitiser strips class attributes — so those buttons would
 * appear to work and then quietly lose their effect on save. Better absent.
 */
(function () {
  'use strict';

  function build(wrapper) {
    var textarea = wrapper.querySelector('[data-editor-input]');
    if (!textarea || typeof window.Quill === 'undefined') return;

    // Host for the editor, inserted before the textarea.
    var host = document.createElement('div');
    wrapper.insertBefore(host, textarea);

    // The textarea becomes the source view.
    textarea.classList.add('rs-editor__source');

    var quill = new window.Quill(host, {
      theme: 'snow',
      placeholder: textarea.getAttribute('placeholder') || 'Write here…',
      modules: {
        toolbar: [
          [{ header: [2, 3, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['blockquote', 'code-block'],
          ['link'],
          ['clean']
        ]
      },
      formats: [
        // An explicit allowlist, mirroring HtmlSanitiser. Anything pasted in
        // that is not on this list is dropped at paste time rather than being
        // silently removed later on save.
        'header', 'bold', 'italic', 'underline', 'strike',
        'list', 'blockquote', 'code-block', 'link'
      ]
    });

    // Seed from whatever the textarea holds.
    if (textarea.value.trim() !== '') {
      quill.clipboard.dangerouslyPasteHTML(textarea.value, 'silent');
    }

    function sync() {
      var html = quill.getSemanticHTML();
      // Quill emits a non-breaking space for an empty document.
      textarea.value = html === '<p></p>' || html.trim() === '' ? '' : html;
    }

    quill.on('text-change', sync);

    // A form can be submitted without a blur event firing, so sync once more
    // on submit rather than trusting the last keystroke to have landed.
    var form = wrapper.closest('form');
    if (form) form.addEventListener('submit', sync);

    // ---- source toggle -------------------------------------------------
    var bar = document.createElement('div');
    bar.className = 'rs-editor__bar';

    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'rs-link text-xs text-ink-muted';
    toggle.textContent = 'Edit the HTML';

    var note = document.createElement('span');
    note.className = 'rs-help';
    note.textContent = 'Formatting beyond these buttons is removed when you save.';

    bar.appendChild(note);
    bar.appendChild(toggle);
    wrapper.appendChild(bar);

    toggle.addEventListener('click', function () {
      var sourceMode = wrapper.classList.toggle('is-source');

      if (sourceMode) {
        sync();
        toggle.textContent = 'Back to the editor';
        textarea.focus();
      } else {
        // Trust what was typed in the source view over the editor's state.
        quill.setContents([], 'silent');
        if (textarea.value.trim() !== '') {
          quill.clipboard.dangerouslyPasteHTML(textarea.value, 'silent');
        }
        toggle.textContent = 'Edit the HTML';
        quill.focus();
      }
    });
  }

  function init() {
    document.querySelectorAll('[data-editor]').forEach(build);
  }

  document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', init)
    : init();
})();
