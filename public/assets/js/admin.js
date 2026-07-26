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
