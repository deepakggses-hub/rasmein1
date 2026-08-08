/**
 * Admin panel interactions — SweetAlert2 (MIT, vendored).
 *
 * Two jobs:
 *
 *  1. CONFIRMATIONS. Native confirm() is a browser-styled box that says nothing
 *     useful about consequences. These read the intent from data attributes, so
 *     each dialog can say what will actually happen.
 *
 *  2. FLASH MESSAGES AS TOASTS. The server still renders them into the page for
 *     when JS is unavailable; this lifts them into a toast and hides the
 *     original, so nothing is lost either way.
 *
 * Progressive throughout: if SweetAlert fails to load, confirmations fall back
 * to native confirm() rather than submitting silently. A destructive action must
 * never lose its guard because a script did not arrive.
 */
(function () {
  'use strict';

  var hasSwal = typeof window.Swal !== 'undefined';

  /* Brand the dialogs once, rather than repeating the options everywhere. */
  var base = {
    buttonsStyling: false,
    reverseButtons: true,
    customClass: {
      popup: 'rs-swal',
      title: 'rs-swal__title',
      htmlContainer: 'rs-swal__body',
      confirmButton: 'rs-btn rs-btn--primary',
      denyButton: 'rs-btn rs-btn--outline',
      cancelButton: 'rs-btn rs-btn--outline'
    }
  };

  // ---------------------------------------------------------- confirmations
  function wireConfirm(form) {
    form.addEventListener('submit', function (event) {
      if (form.dataset.rsConfirmed === 'yes') return;

      event.preventDefault();

      var title = form.getAttribute('data-confirm') || 'Are you sure?';
      var detail = form.getAttribute('data-confirm-detail') || '';
      var action = form.getAttribute('data-confirm-action') || 'Yes, continue';
      var tone = form.getAttribute('data-confirm-tone') || 'warning';

      if (!hasSwal) {
        // Strip the entities the attribute may carry before showing it raw.
        var plain = document.createElement('textarea');
        plain.innerHTML = title + (detail ? '\n\n' + detail : '');
        if (window.confirm(plain.value)) {
          form.dataset.rsConfirmed = 'yes';
          form.submit();
        }
        return;
      }

      window.Swal.fire(Object.assign({}, base, {
        title: title,
        html: detail,
        icon: tone,
        showCancelButton: true,
        confirmButtonText: action,
        cancelButtonText: 'Keep it',
        focusCancel: true
      })).then(function (result) {
        if (result.isConfirmed) {
          form.dataset.rsConfirmed = 'yes';
          form.submit();
        }
      });
    });
  }

  // ------------------------------------------------------------- typed word
  /** For the journey switch: require the word, but explain why first. */
  function wirePhrase(form) {
    var phrase = form.getAttribute('data-confirm-phrase');

    form.addEventListener('submit', function (event) {
      if (form.dataset.rsConfirmed === 'yes' || !hasSwal) return;

      var field = form.querySelector('[name="confirm"]');
      if (field && field.value.trim().toUpperCase() === phrase.toUpperCase()) return;

      event.preventDefault();

      window.Swal.fire(Object.assign({}, base, {
        title: form.getAttribute('data-confirm') || 'Confirm this change',
        html: form.getAttribute('data-confirm-detail') || '',
        icon: 'warning',
        input: 'text',
        inputPlaceholder: phrase,
        inputAttributes: { autocapitalize: 'characters', autocomplete: 'off' },
        showCancelButton: true,
        confirmButtonText: form.getAttribute('data-confirm-action') || 'Confirm',
        cancelButtonText: 'Cancel',
        customClass: Object.assign({}, base.customClass, { input: 'rs-input' }),
        preConfirm: function (value) {
          if (String(value || '').trim().toUpperCase() !== phrase.toUpperCase()) {
            window.Swal.showValidationMessage('Type ' + phrase + ' exactly to confirm.');
            return false;
          }
          return value;
        }
      })).then(function (result) {
        if (result.isConfirmed) {
          if (field) field.value = phrase;
          form.dataset.rsConfirmed = 'yes';
          form.submit();
        }
      });
    });
  }

  // ------------------------------------------------------------ flash toasts
  function showFlashes() {
    var source = document.querySelector('[data-flash]');
    if (!source || !hasSwal) return;

    var items = source.querySelectorAll('[data-flash-item]');
    if (!items.length) return;

    // Hide the server-rendered version now that a toast will carry it.
    source.setAttribute('hidden', 'hidden');

    var queue = [];
    items.forEach(function (item) {
      queue.push({
        icon: item.getAttribute('data-flash-type') || 'info',
        text: (item.textContent || '').trim()
      });
    });

    (function next(i) {
      if (i >= queue.length) return;

      window.Swal.fire({
        toast: true,
        position: 'top-end',
        icon: queue[i].icon,
        title: queue[i].text,
        showConfirmButton: false,
        // An error stays until dismissed; a success can fade.
        timer: queue[i].icon === 'error' ? 9000 : 4500,
        timerProgressBar: true,
        customClass: { popup: 'rs-toast' },
        didOpen: function (toast) {
          toast.addEventListener('mouseenter', window.Swal.stopTimer);
          toast.addEventListener('mouseleave', window.Swal.resumeTimer);
        }
      }).then(function () { next(i + 1); });
    })(0);
  }

  // --------------------------------------------------- permission picker aid
  function wirePermissionPicker() {
    var boxes = document.querySelectorAll('[data-perm]');
    if (!boxes.length) return;

    var count = document.querySelector('[data-perm-count]');
    var groups = document.querySelector('[data-perm-groups]');
    var wildcard = document.querySelector('[data-perm-wildcard]');

    function tally() {
      if (wildcard && wildcard.checked) {
        if (count) count.textContent = 'All';
        if (groups) groups.classList.add('opacity-40');
        boxes.forEach(function (b) { b.disabled = true; });
        return;
      }

      if (groups) groups.classList.remove('opacity-40');
      var n = 0;
      boxes.forEach(function (b) {
        if (!b.hasAttribute('data-was-disabled')) b.disabled = false;
        if (b.checked) n++;
      });
      if (count) count.textContent = String(n);
    }

    // Remember which were disabled server-side, so re-enabling never grants
    // something this administrator may not hand out.
    boxes.forEach(function (b) {
      if (b.disabled) b.setAttribute('data-was-disabled', 'yes');
      b.addEventListener('change', tally);
    });

    if (wildcard) wildcard.addEventListener('change', tally);

    var all = document.querySelector('[data-perm-all]');
    var none = document.querySelector('[data-perm-none]');

    if (all) all.addEventListener('click', function () {
      boxes.forEach(function (b) { if (!b.hasAttribute('data-was-disabled')) b.checked = true; });
      tally();
    });

    if (none) none.addEventListener('click', function () {
      boxes.forEach(function (b) { b.checked = false; });
      if (wildcard) wildcard.checked = false;
      tally();
    });

    tally();
  }

  function init() {
    document.querySelectorAll('form[data-confirm]:not([data-confirm-phrase])').forEach(wireConfirm);
    document.querySelectorAll('form[data-confirm-phrase]').forEach(wirePhrase);
    wirePermissionPicker();
    showFlashes();
  }

  document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', init)
    : init();
})();

/**
 * Admin shell behaviour: the mobile drawer, the desktop collapse, and the
 * account menu.
 *
 * All of it degrades to nothing harmful without JavaScript — the sidebar is
 * visible at desktop widths by CSS alone, and the account menu's contents are
 * reachable from the pages they link to.
 */
(function () {
  'use strict';

  var shell = document.querySelector('[data-admin-shell]');
  if (!shell) return;

  var nav = document.querySelector('[data-nav]');
  var scrim = document.querySelector('[data-nav-scrim]');
  var openBtn = document.querySelector('[data-nav-open]');
  var closeBtn = document.querySelector('[data-nav-close]');
  var collapseBtn = document.querySelector('[data-nav-collapse]');

  // ------------------------------------------------------ mobile drawer
  function setDrawer(open) {
    if (!nav) return;
    nav.classList.toggle('is-open', open);
    if (scrim) scrim.hidden = !open;
    if (openBtn) openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    // Stop the page behind scrolling while the drawer covers it.
    document.body.style.overflow = open ? 'hidden' : '';
  }

  if (openBtn) openBtn.addEventListener('click', function () { setDrawer(true); });
  if (closeBtn) closeBtn.addEventListener('click', function () { setDrawer(false); });
  if (scrim) scrim.addEventListener('click', function () { setDrawer(false); });

  // ---------------------------------------------------- desktop collapse
  var COLLAPSE_KEY = 'rsAdminCollapsed';

  function setCollapsed(on) {
    shell.classList.toggle('is-collapsed', on);
    if (collapseBtn) {
      collapseBtn.setAttribute('aria-label', on ? 'Expand the menu' : 'Collapse the menu');
    }
    try {
      window.localStorage.setItem(COLLAPSE_KEY, on ? '1' : '0');
    } catch (e) {
      /* Private browsing, or storage disabled. The preference simply is not
         remembered; nothing else breaks. */
    }
  }

  try {
    if (window.localStorage.getItem(COLLAPSE_KEY) === '1') setCollapsed(true);
  } catch (e) { /* as above */ }

  if (collapseBtn) {
    collapseBtn.addEventListener('click', function () {
      setCollapsed(!shell.classList.contains('is-collapsed'));
    });
  }

  // -------------------------------------------------------- account menu
  document.querySelectorAll('[data-menu]').forEach(function (menu) {
    var trigger = menu.querySelector('[data-menu-trigger]');
    var panel = menu.querySelector('[data-menu-panel]');
    if (!trigger || !panel) return;

    function close() {
      panel.hidden = true;
      trigger.setAttribute('aria-expanded', 'false');
    }

    trigger.addEventListener('click', function (event) {
      event.stopPropagation();
      var open = panel.hidden;
      panel.hidden = !open;
      trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.addEventListener('click', function (event) {
      if (!menu.contains(event.target)) close();
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') { close(); setDrawer(false); }
    });
  });
})();

/**
 * Occasion tagging: live filter over a long product list, and a running count.
 *
 * The list is rendered in full server-side, so with JavaScript unavailable it is
 * still a usable (if long) set of checkboxes. This only makes finding things
 * quicker.
 */
(function () {
  'use strict';

  var list = document.querySelector('[data-tag-list]');
  if (!list) return;

  var rows = list.querySelectorAll('[data-tag-row]');
  var boxes = list.querySelectorAll('[data-tag]');
  var filter = document.querySelector('[data-tag-filter]');
  var counter = document.querySelector('[data-tag-count]');

  function count() {
    if (!counter) return;
    var n = 0;
    boxes.forEach(function (b) { if (b.checked) n++; });
    counter.textContent = String(n);
  }

  boxes.forEach(function (b) { b.addEventListener('change', count); });
  count();

  if (filter) {
    filter.addEventListener('input', function () {
      var term = filter.value.trim().toLowerCase();

      rows.forEach(function (row) {
        // A ticked product always stays visible: hiding something the person
        // has already chosen makes it look as though the choice was lost.
        var box = row.querySelector('[data-tag]');
        var keep = term === '' ||
          (row.getAttribute('data-search') || '').indexOf(term) !== -1 ||
          (box && box.checked);

        row.hidden = !keep;
      });
    });
  }
})();
