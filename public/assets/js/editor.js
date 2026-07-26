/**
 * Rich text editing in the admin panel — Quill 2 (BSD-3-Clause).
 *
 * TWO THINGS MAKE THIS WORK.
 *
 * 1. INLINE STYLES, NOT CLASSES. Out of the box Quill writes alignment,
 *    colour, size and font as ql-* CSS classes, and the server-side sanitiser
 *    strips class attributes — so those buttons would appear to work and then
 *    lose their effect on save. Registering Quill's *style* attributors makes
 *    it emit style="text-align:center" instead, which the sanitiser's CSS
 *    allowlist preserves. Indent has no built-in style attributor, so one is
 *    defined below that maps it to padding-left.
 *
 * 2. THE TOOLBAR MATCHES THE ALLOWLIST. Every control here produces markup
 *    that survives HtmlSanitiser. Nothing is offered that would be silently
 *    discarded.
 *
 * The editor is NOT a security control — it runs in the browser and can be
 * bypassed. Sanitising on save is the actual protection.
 *
 * Progressive: the real field is a <textarea> that already works. Quill layers
 * on top and syncs back on every change and on submit.
 */
(function () {
  'use strict';

  var registered = false;

  /** Swap Quill's class-based formats for style-based ones, once. */
  function registerStyleAttributors(Quill) {
    if (registered) return;
    registered = true;

    ['align', 'direction', 'size', 'color', 'background', 'font'].forEach(function (name) {
      try {
        Quill.register(Quill.import('attributors/style/' + name), true);
      } catch (e) {
        /* Attributor absent in this build — skip rather than break the editor. */
      }
    });

    // Indent ships only as a class attributor. Express it as padding-left,
    // which the sanitiser's CSS allowlist permits.
    try {
      var Parchment = Quill.import('parchment');
      var IndentStyle = new Parchment.ClassAttributor('indent', 'ql-indent', {
        scope: Parchment.Scope.BLOCK,
        whitelist: [1, 2, 3, 4, 5, 6, 7, 8]
      });

      var StyleIndent = new Parchment.StyleAttributor('indent', 'padding-left', {
        scope: Parchment.Scope.BLOCK,
        whitelist: ['3em', '6em', '9em', '12em', '15em', '18em', '21em', '24em']
      });

      // Quill's toolbar sends integers; translate them to the em values above.
      StyleIndent.add = function (node, value) {
        if (value === 0 || value === '0' || !value) {
          this.remove(node);
          return true;
        }
        var n = Math.min(8, Math.max(1, parseInt(value, 10) || 1));
        return Parchment.StyleAttributor.prototype.add.call(this, node, n * 3 + 'em');
      };

      StyleIndent.value = function (node) {
        var raw = Parchment.StyleAttributor.prototype.value.call(this, node);
        return raw ? parseInt(raw, 10) / 3 : 0;
      };

      Quill.register(StyleIndent, true);
      void IndentStyle;
    } catch (e) {
      /* Older Parchment shape — indent falls back to classes and is stripped. */
    }
  }

  function build(wrapper) {
    var textarea = wrapper.querySelector('[data-editor-input]');
    if (!textarea || typeof window.Quill === 'undefined') return;

    var Quill = window.Quill;
    registerStyleAttributors(Quill);

    var host = document.createElement('div');
    wrapper.insertBefore(host, textarea);
    textarea.classList.add('rs-editor__source');

    var toolbar = [
      [{ header: [1, 2, 3, 4, 5, 6, false] }],
      [{ size: ['0.75em', false, '1.25em', '1.5em', '2em'] }],
      ['bold', 'italic', 'underline', 'strike'],
      [{ color: [] }, { background: [] }],
      [{ script: 'sub' }, { script: 'super' }],
      [{ align: [] }],
      [{ indent: '-1' }, { indent: '+1' }],
      [{ list: 'ordered' }, { list: 'bullet' }],
      ['blockquote', 'code-block'],
      [{ direction: 'rtl' }],
      ['link', 'image'],
      ['clean']
    ];

    var quill = new Quill(host, {
      theme: 'snow',
      placeholder: textarea.getAttribute('placeholder') || 'Write here…',
      modules: { toolbar: { container: toolbar } },
      formats: [
        // Mirrors HtmlSanitiser. Pasted formatting outside this list is dropped
        // at paste time rather than surviving to be stripped later on save.
        'header', 'size', 'bold', 'italic', 'underline', 'strike',
        'color', 'background', 'script', 'align', 'indent', 'direction',
        'list', 'blockquote', 'code-block', 'link', 'image'
      ]
    });

    // ---- image upload -------------------------------------------------
    var uploadUrl = wrapper.getAttribute('data-upload-url');
    var csrfName = wrapper.getAttribute('data-csrf-name');
    var csrfValue = wrapper.getAttribute('data-csrf-value');

    /**
     * Read the CSRF token LIVE from the page rather than trusting the value
     * captured at load. CodeIgniter has security.regenerate = true, so the
     * token rotates after every validated POST — a cached copy goes stale the
     * moment anything else on the page submits.
     */
    function currentToken() {
      if (!csrfName) return null;
      var input = document.querySelector('input[name="' + csrfName + '"]');
      return input && input.value ? input.value : csrfValue;
    }

    /**
     * Write a rotated token back into every CSRF input on the page.
     *
     * Without this, uploading an image and then saving the form fails: the
     * upload consumed the token and the server issued a new one, but the form's
     * hidden input still holds the old value.
     */
    function refreshToken(fresh) {
      if (!fresh || !csrfName) return;
      csrfValue = fresh;
      document.querySelectorAll('input[name="' + csrfName + '"]').forEach(function (input) {
        input.value = fresh;
      });
    }

    if (uploadUrl) {
      quill.getModule('toolbar').addHandler('image', function () {
        var input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/jpeg,image/png,image/webp';
        input.onchange = function () {
          var file = input.files && input.files[0];
          if (!file) return;

          var range = quill.getSelection(true);
          var data = new FormData();
          data.append('image', file);

          var token = currentToken();
          if (csrfName && token) data.append(csrfName, token);

          quill.insertText(range.index, 'Uploading…', { italic: true }, 'user');

          var placeholderLength = 'Uploading…'.length;
          var status = 0;

          fetch(uploadUrl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: {
              // Makes $request->isAJAX() true, so the server can answer in JSON
              // rather than redirecting to a page this fetch cannot use.
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            }
          })
            .then(function (r) {
              status = r.status;
              return r.json().catch(function () { return null; });
            })
            .then(function (json) {
              quill.deleteText(range.index, placeholderLength, 'user');

              if (json && json.csrf) refreshToken(json.csrf);

              if (json && json.ok && json.url) {
                quill.insertEmbed(range.index, 'image', json.url, 'user');
                quill.setSelection(range.index + 1, 0, 'silent');
                return;
              }

              // A 403 here is almost always the session token, not permissions.
              // Say so, because "you do not have access" sends people hunting
              // through roles for a problem that is a stale page.
              if (status === 403) {
                window.alert(
                  'The upload was rejected as unverified.\n\n' +
                  'This usually means the page has been open a while and its ' +
                  'security token expired. Reload the page and try again.\n\n' +
                  'If it keeps happening, check that app.baseURL in .env matches ' +
                  'the address you are using.'
                );
                return;
              }

              window.alert((json && json.error) || 'That image could not be uploaded.');
            })
            .catch(function () {
              quill.deleteText(range.index, placeholderLength, 'user');
              window.alert('The upload failed. Check your connection and try again.');
            });
        };
        input.click();
      });
    }

    // ---- seed and sync -------------------------------------------------
    if (textarea.value.trim() !== '') {
      quill.clipboard.dangerouslyPasteHTML(textarea.value, 'silent');
    }

    function sync() {
      var html = quill.getSemanticHTML();
      textarea.value = html === '<p></p>' || html.trim() === '' ? '' : html;
    }

    quill.on('text-change', sync);

    var form = wrapper.closest('form');
    if (form) form.addEventListener('submit', sync);

    // ---- source view ----------------------------------------------------
    var bar = document.createElement('div');
    bar.className = 'rs-editor__bar';

    var note = document.createElement('span');
    note.className = 'rs-help';
    note.textContent = 'Tables and anything the toolbar does not cover: use the HTML view.';

    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'rs-link text-xs text-ink-muted';
    toggle.textContent = 'Edit the HTML';

    bar.appendChild(note);
    bar.appendChild(toggle);
    wrapper.appendChild(bar);

    toggle.addEventListener('click', function () {
      if (wrapper.classList.toggle('is-source')) {
        sync();
        toggle.textContent = 'Back to the editor';
        textarea.focus();
      } else {
        // What was typed in the source view wins over the editor's state.
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
