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


  /* ------------------------------------------------- product gallery
   * Thumbnails swap the main image. The markup already shows a valid image
   * before this runs, so nothing is broken without JS.
   */
  function initGallery() {
    var main = document.getElementById('product-image');
    var thumbs = document.querySelectorAll('[data-gallery-thumb]');
    if (!main || thumbs.length === 0) return;

    thumbs.forEach(function (thumb) {
      thumb.addEventListener('click', function () {
        var src = thumb.getAttribute('data-src');
        if (!src) return;
        main.src = src;
        main.alt = thumb.getAttribute('data-alt') || '';
        thumbs.forEach(function (t) { t.removeAttribute('aria-current'); });
        thumb.setAttribute('aria-current', 'true');
      });
    });
  }

  /* --------------------------------------------- auto-submitting selects
   * The sort dropdown submits on change. A <noscript> button covers the
   * case where this file never runs.
   */
  function initAutoSubmit() {
    document.querySelectorAll('[data-auto-submit]').forEach(function (control) {
      control.addEventListener('change', function () {
        var form = control.closest('form');
        if (form) form.submit();
      });
    });
  }

  function init() {
    initDropdowns();
    initMobileMenu();
    initTrayReveal();
    initGallery();
    initAutoSubmit();
  }

  document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', init)
    : init();
})();

/**
 * Mail settings: show only the fields belonging to the chosen sending method.
 * Server-side validation does not depend on this — it re-checks whichever
 * method was actually submitted.
 */
(function () {
  'use strict';

  var radios = document.querySelectorAll('[data-mail-protocol]');
  if (!radios.length) return;

  var panels = document.querySelectorAll('[data-mail-panel]');

  function apply() {
    var chosen = document.querySelector('[data-mail-protocol]:checked');
    var value = chosen ? chosen.value : 'smtp';

    panels.forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-mail-panel') !== value;
    });

    radios.forEach(function (radio) {
      var label = radio.closest('label');
      if (!label) return;
      label.classList.toggle('border-mulberry', radio.checked);
      label.classList.toggle('bg-shell', radio.checked);
      label.classList.toggle('border-shell-line', !radio.checked);
    });
  }

  radios.forEach(function (radio) { radio.addEventListener('change', apply); });
  apply();
})();

/**
 * Storefront shell: the mobile drawer, scroll reveals, and the sticky-header
 * offset.
 *
 * Everything degrades. The drawer's markup is in the page and moved by CSS, so
 * a failed script leaves the normal navigation. Reveals start VISIBLE and are
 * only armed once this file runs — the opposite order would hide the whole page
 * if the script never arrived.
 */
(function () {
  'use strict';

  // ------------------------------------------------------------- drawer
  var drawer = document.querySelector('[data-drawer]');
  var scrim = document.querySelector('[data-drawer-scrim]');
  var openers = document.querySelectorAll('[data-drawer-open]');
  var closers = document.querySelectorAll('[data-drawer-close]');
  var lastFocus = null;

  function setDrawer(open) {
    if (!drawer) return;

    if (open) {
      lastFocus = document.activeElement;
      drawer.hidden = false;
      // Next frame, so the transition has a start state to animate from.
      requestAnimationFrame(function () { drawer.classList.add('is-open'); });
    } else {
      drawer.classList.remove('is-open');
      window.setTimeout(function () { drawer.hidden = true; }, 240);
      if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    if (scrim) scrim.hidden = !open;
    document.body.style.overflow = open ? 'hidden' : '';
    openers.forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); });

    if (open) {
      var first = drawer.querySelector('a, button, input');
      if (first) first.focus();
    }
  }

  openers.forEach(function (b) { b.addEventListener('click', function () { setDrawer(true); }); });
  closers.forEach(function (b) { b.addEventListener('click', function () { setDrawer(false); }); });
  if (scrim) scrim.addEventListener('click', function () { setDrawer(false); });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && drawer && !drawer.hidden) setDrawer(false);
  });

  // Close on resize past the breakpoint, or the drawer lingers invisibly and
  // keeps the body scroll-locked.
  var wide = window.matchMedia('(min-width: 1100px)');
  var onWide = function (e) { if (e.matches && drawer && !drawer.hidden) setDrawer(false); };
  wide.addEventListener ? wide.addEventListener('change', onWide) : wide.addListener(onWide);

  // ------------------------------------------------------- scroll reveal
  var reveals = document.querySelectorAll('.rs-reveal');

  if (reveals.length && 'IntersectionObserver' in window) {
    // Arm only now: the CSS hides .rs-reveal solely when this class is present.
    document.documentElement.classList.add('rs-reveal-ready');

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-in');
        io.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });

    reveals.forEach(function (el, i) {
      // A small stagger reads as one movement rather than a popcorn effect.
      el.style.transitionDelay = Math.min(i % 6, 5) * 60 + 'ms';
      io.observe(el);
    });
  }

  // ------------------------------------------------------------ steppers
  document.querySelectorAll('[data-stepper]').forEach(function (stepper) {
    var input = stepper.querySelector('input');
    if (!input) return;

    stepper.querySelectorAll('[data-step]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var by = parseInt(btn.getAttribute('data-step'), 10) || 0;
        var min = parseInt(input.getAttribute('min'), 10);
        var max = parseInt(input.getAttribute('max'), 10);
        var next = (parseInt(input.value, 10) || 0) + by;

        if (!isNaN(min)) next = Math.max(min, next);
        if (!isNaN(max)) next = Math.min(max, next);

        input.value = String(next);
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
  });

  // -------------------------------------------------------- grid density
  // The shop's view toggle. The choice is remembered, because re-picking it on
  // every visit is the kind of small friction people notice.
  var grid = document.querySelector('[data-grid]');

  if (grid) {
    var KEY = 'rsGridDensity';
    var buttons = document.querySelectorAll('[data-density]');

    function apply(mode) {
      grid.classList.remove('rs-grid--dense', 'rs-grid--list');
      if (mode === 'dense') grid.classList.add('rs-grid--dense');
      if (mode === 'list') grid.classList.add('rs-grid--list');

      buttons.forEach(function (b) {
        var on = b.getAttribute('data-density') === mode;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-pressed', on ? 'true' : 'false');
      });

      try { window.localStorage.setItem(KEY, mode); } catch (e) { /* private mode */ }
    }

    buttons.forEach(function (b) {
      b.addEventListener('click', function () { apply(b.getAttribute('data-density')); });
    });

    var saved = 'comfortable';
    try { saved = window.localStorage.getItem(KEY) || saved; } catch (e) { /* as above */ }
    apply(saved);
  }
})();

/**
 * Homepage: the hero slider and the product rail arrows.
 *
 * Both are enhancements over markup that already works. The hero renders every
 * slide in the page with the first marked current, so without this script the
 * first slide simply stays put. The rail is a native scroll-snap container, so
 * without this script it still scrolls by touch, trackpad and keyboard — the
 * arrows are hidden until they are wired, because a button that does nothing is
 * worse than no button.
 */
(function () {
  'use strict';

  // --------------------------------------------------------------- hero
  var hero = document.querySelector('[data-hero]');

  if (hero) {
    var slides = hero.querySelectorAll('[data-hero-slide]');
    var dots = hero.querySelectorAll('[data-hero-dot]');
    var current = 0;
    var timer = null;
    var DELAY = 6500;

    var calm = window.matchMedia('(prefers-reduced-motion: reduce)');

    function show(next) {
      if (next === current || !slides.length) return;

      slides[current].classList.remove('is-current');
      slides[current].setAttribute('aria-hidden', 'true');
      slides[next].classList.add('is-current');
      slides[next].removeAttribute('aria-hidden');

      if (dots.length) {
        dots[current].classList.remove('is-current');
        dots[next].classList.add('is-current');
      }

      current = next;
    }

    function advance() { show((current + 1) % slides.length); }

    function start() {
      // Someone who has asked for less motion should not get a carousel that
      // moves on its own. The dots still work.
      if (calm.matches || slides.length < 2) return;
      stop();
      timer = window.setInterval(advance, DELAY);
    }

    function stop() {
      if (timer) { window.clearInterval(timer); timer = null; }
    }

    dots.forEach(function (dot, index) {
      dot.addEventListener('click', function () { show(index); start(); });
    });

    // Pause on hover and on keyboard focus, and while the tab is hidden —
    // otherwise it races through slides nobody is watching.
    hero.addEventListener('mouseenter', stop);
    hero.addEventListener('mouseleave', start);
    hero.addEventListener('focusin', stop);
    hero.addEventListener('focusout', start);
    document.addEventListener('visibilitychange', function () {
      document.hidden ? stop() : start();
    });

    start();
  }

  // --------------------------------------------------------------- rails
  document.querySelectorAll('[data-rail]').forEach(function (rail) {
    var nav = rail.closest('section');
    var group = nav ? nav.querySelector('[data-rail-nav]') : null;
    if (!group) return;

    var prev = group.querySelector('[data-rail-prev]');
    var next = group.querySelector('[data-rail-next]');

    function step() {
      // Scroll by one card, read from the DOM rather than assumed, so it stays
      // right when the admin changes the card minimum.
      var first = rail.firstElementChild;
      if (!first) return rail.clientWidth * 0.8;
      var gap = parseFloat(getComputedStyle(rail).columnGap) || 16;
      return first.getBoundingClientRect().width + gap;
    }

    function sync() {
      var max = rail.scrollWidth - rail.clientWidth - 2;
      if (prev) prev.disabled = rail.scrollLeft <= 2;
      if (next) next.disabled = rail.scrollLeft >= max;

      // Nothing to scroll: hide rather than show two dead buttons.
      group.hidden = max <= 2;
    }

    if (prev) prev.addEventListener('click', function () {
      rail.scrollBy({ left: -step(), behavior: 'smooth' });
    });
    if (next) next.addEventListener('click', function () {
      rail.scrollBy({ left: step(), behavior: 'smooth' });
    });

    rail.addEventListener('scroll', sync, { passive: true });
    window.addEventListener('resize', sync);

    group.hidden = false;
    sync();
  });
})();

/**
 * Listing page: card photograph cycling, filter auto-submit, and the mobile
 * filter sheet.
 *
 * All three are enhancements. The card shows its first photograph without this;
 * the filter form is a plain GET that submits with its own button; the sidebar
 * is a normal column at desktop widths.
 */
(function () {
  'use strict';

  var calm = window.matchMedia('(prefers-reduced-motion: reduce)');

  // ------------------------------------------------- card photograph cycle
  document.querySelectorAll('[data-shots]').forEach(function (card) {
    var shots = card.querySelectorAll('[data-shot]');
    var dots = card.querySelectorAll('[data-shot-dot]');
    if (shots.length < 2) return;

    var index = 0;
    var timer = null;

    function show(next) {
      if (next === index) return;
      shots[index].classList.remove('is-current');
      shots[next].classList.add('is-current');
      if (dots.length) {
        dots[index].classList.remove('is-current');
        dots[next].classList.add('is-current');
      }
      index = next;
    }

    function play() {
      if (calm.matches) return;
      stop();
      timer = window.setInterval(function () { show((index + 1) % shots.length); }, 1400);
    }

    function stop() {
      if (timer) { window.clearInterval(timer); timer = null; }
    }

    function rest() {
      stop();
      show(0);
    }

    card.addEventListener('mouseenter', play);
    card.addEventListener('mouseleave', rest);
    // Keyboard users get the same thing: focus anywhere in the card starts it.
    card.addEventListener('focusin', play);
    card.addEventListener('focusout', rest);

    dots.forEach(function (dot, i) {
      // A dot is a deliberate choice, so it stops the automatic cycle rather
      // than fighting it.
      dot.addEventListener('click', function (e) {
        e.preventDefault();
        stop();
        show(i);
      });
    });

    // On touch there is no hover, so advance while the card is on screen.
    if (window.matchMedia('(hover: none)').matches && 'IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) { entry.isIntersecting ? play() : rest(); });
      }, { threshold: 0.6 }).observe(card);
    }
  });

  // ---------------------------------------------------- filter auto-submit
  var filters = document.querySelector('[data-filters]');

  if (filters) {
    // The manual Apply button only exists for the no-script case.
    var manual = filters.querySelector('[data-filters-manual]');
    if (manual) manual.hidden = true;

    var pending = null;

    filters.addEventListener('change', function (e) {
      if (!e.target.matches('input[type="checkbox"]')) return;

      // A short debounce, so ticking three boxes in a row is one navigation
      // rather than three.
      window.clearTimeout(pending);
      pending = window.setTimeout(function () {
        filters.setAttribute('aria-busy', 'true');
        filters.submit();
      }, 350);
    });
  }

  // ------------------------------------------------------- filter sheet
  var sidebar = document.querySelector('[data-sidebar]');
  var openBtn = document.querySelector('[data-sidebar-open]');
  var closeBtn = document.querySelector('[data-sidebar-close]');

  if (sidebar && openBtn) {
    function sheet(open) {
      sidebar.classList.toggle('is-open', open);
      document.body.style.overflow = open ? 'hidden' : '';
      openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    openBtn.addEventListener('click', function () { sheet(true); });
    if (closeBtn) closeBtn.addEventListener('click', function () { sheet(false); });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') sheet(false);
    });
  }
})();

/**
 * Product page: gallery thumbnails and the copy-link button.
 *
 * The first photograph renders server-side, so with no script the page still
 * shows the product — the thumbnails simply do nothing. Copy-link falls back to
 * selecting the URL when the clipboard API is unavailable (it needs a secure
 * context, so plain http will take that path).
 */
(function () {
  'use strict';

  // ------------------------------------------------------------- gallery
  var main = document.getElementById('product-image');
  var thumbs = document.querySelectorAll('[data-gallery-thumb]');

  if (main && thumbs.length) {
    thumbs.forEach(function (thumb) {
      thumb.addEventListener('click', function () {
        var src = thumb.getAttribute('data-src');
        if (!src) return;

        main.src = src;
        main.alt = thumb.getAttribute('data-alt') || '';

        thumbs.forEach(function (t) { t.classList.remove('is-current'); });
        thumb.classList.add('is-current');
      });
    });

    // Arrow keys move along the rail, which is what a rail implies.
    thumbs.forEach(function (thumb, i) {
      thumb.addEventListener('keydown', function (e) {
        var next = e.key === 'ArrowDown' || e.key === 'ArrowRight' ? i + 1
          : (e.key === 'ArrowUp' || e.key === 'ArrowLeft' ? i - 1 : null);

        if (next === null || next < 0 || next >= thumbs.length) return;
        e.preventDefault();
        thumbs[next].focus();
        thumbs[next].click();
      });
    });
  }

  // ----------------------------------------------------------- copy link
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    var label = btn.querySelector('[data-copy-label]');
    var original = label ? label.textContent : '';

    btn.addEventListener('click', function () {
      var url = btn.getAttribute('data-copy') || window.location.href;

      function done(ok) {
        if (!label) return;
        label.textContent = ok ? 'Link copied' : 'Press Ctrl+C';
        window.setTimeout(function () { label.textContent = original; }, 2200);
      }

      // navigator.clipboard needs a secure context, so plain http takes the
      // fallback rather than failing silently.
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url).then(function () { done(true); }, function () { done(false); });
        return;
      }

      var field = document.createElement('input');
      field.value = url;
      field.setAttribute('readonly', 'readonly');
      field.style.position = 'fixed';
      field.style.opacity = '0';
      document.body.appendChild(field);
      field.select();

      var ok = false;
      try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
      document.body.removeChild(field);
      done(ok);
    });
  });
})();
