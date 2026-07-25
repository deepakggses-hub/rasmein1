/**
 * Rasmein — storefront behaviour.
 *
 * Vanilla JS, no framework, no build step. Progressive: every control it
 * touches is a real link or button that already works without JS.
 *
 * Conventions
 *  - Behaviour is attached via data-* attributes, never by styling class.
 *  - Nothing here decides price, capacity or eligibility. Those are server
 *    concerns; this file only reflects what the server has said.
 */
(function () {
  'use strict';

  var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ----------------------------------------------------- shop dropdown */
  function initDropdowns() {
    document.querySelectorAll('[data-dropdown]').forEach(function (root) {
      var trigger = root.querySelector('[data-dropdown-trigger]');
      var panel = root.querySelector('[data-dropdown-panel]');
      var chevron = root.querySelector('[data-dropdown-chevron]');
      if (!trigger || !panel) return;

      var closeTimer = null;

      function open() {
        window.clearTimeout(closeTimer);
        panel.classList.remove('hidden');
        trigger.setAttribute('aria-expanded', 'true');
        if (chevron) chevron.classList.add('rotate-180');
      }

      function close() {
        panel.classList.add('hidden');
        trigger.setAttribute('aria-expanded', 'false');
        if (chevron) chevron.classList.remove('rotate-180');
      }

      function scheduleClose() {
        closeTimer = window.setTimeout(close, 160);
      }

      // Pointer users get hover; keyboard and touch users get click.
      root.addEventListener('mouseenter', open);
      root.addEventListener('mouseleave', scheduleClose);
      root.addEventListener('focusin', open);

      trigger.addEventListener('click', function (event) {
        event.preventDefault();
        panel.classList.contains('hidden') ? open() : close();
      });

      root.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
          close();
          trigger.focus();
        }
      });

      document.addEventListener('click', function (event) {
        if (!root.contains(event.target)) close();
      });
    });
  }

  /* -------------------------------------------------------- mobile nav */
  function initMobileMenu() {
    var trigger = document.querySelector('[data-menu-trigger]');
    var panel = document.querySelector('[data-menu-panel]');
    if (!trigger || !panel) return;

    trigger.addEventListener('click', function () {
      var isOpen = !panel.classList.contains('hidden');
      panel.classList.toggle('hidden', isOpen);
      trigger.setAttribute('aria-expanded', String(!isOpen));
      trigger.setAttribute('aria-label', isOpen ? 'Open menu' : 'Close menu');
    });

    // A resize into the desktop breakpoint should not leave the panel stuck open.
    window.addEventListener('resize', function () {
      if (window.innerWidth >= 1024) {
        panel.classList.add('hidden');
        trigger.setAttribute('aria-expanded', 'false');
      }
    });
  }

  /* --------------------------------------------------------- the tray
   * Replays the fill animation when a tray scrolls into view. Purely
   * decorative, so it is skipped entirely under reduced-motion.
   */
  function initTrayReveal() {
    if (prefersReducedMotion || !('IntersectionObserver' in window)) return;

    var trays = document.querySelectorAll('.rs-tray--animate');
    if (trays.length === 0) return;

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('rs-tray--in-view');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.25 });

    trays.forEach(function (tray) { observer.observe(tray); });
  }

  function init() {
    initDropdowns();
    initMobileMenu();
    initTrayReveal();
  }

  document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', init)
    : init();
})();
