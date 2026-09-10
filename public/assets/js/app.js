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
        // A <picture> wraps the img in <source> elements, and a browser that
        // matched a source ignores a changed img.src entirely — the picture
        // would appear stuck. Clear the sources and let it fall back to the
        // img, whose srcset is swapped too.
        var picture = main.parentElement;

        if (picture && picture.tagName === 'PICTURE') {
          picture.querySelectorAll('source').forEach(function (s) {
            s.removeAttribute('srcset');
          });
        }

        main.removeAttribute('srcset');
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

  /* ------------------------------------------------------- scroll reveal
   *
   * `.rs-reveal-ready .rs-reveal { opacity: 0 }` hides everything until the
   * observer fades it in. That is fine for the page as delivered, but ANY
   * element inserted later — a filtered product grid, a refreshed cart — arrives
   * hidden and is never observed, so it stays at opacity 0 permanently. The
   * products were there the whole time; they were invisible.
   *
   * A MutationObserver watches for new ones and observes them automatically, so
   * every future swap is covered without each one having to remember to ask.
   */
  if ('IntersectionObserver' in window) {
    // Armed only when the script is running: the CSS hides .rs-reveal solely
    // when this class is present, so a failed script leaves everything visible.
    document.documentElement.classList.add('rs-reveal-ready');

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-in');
        io.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });

    var watch = function (el, i) {
      if (el.classList.contains('is-in') || el.dataset.rsWatched) return;

      el.dataset.rsWatched = '1';

      // A small stagger reads as one movement rather than a popcorn effect.
      el.style.transitionDelay = Math.min(i % 6, 5) * 60 + 'ms';

      /*
       * Anything already in view is shown at once rather than observed.
       * A swapped-in grid usually sits exactly where the reader is looking, and
       * waiting for an intersection that has already happened is how it would
       * stay blank.
       */
      var box = el.getBoundingClientRect();

      if (box.top < window.innerHeight && box.bottom > 0) {
        el.classList.add('is-in');

        return;
      }

      io.observe(el);
    };

    var scan = function (root) {
      var list = (root || document).querySelectorAll('.rs-reveal');

      Array.prototype.forEach.call(list, watch);
    };

    scan();

    if ('MutationObserver' in window) {
      new MutationObserver(function (records) {
        records.forEach(function (record) {
          Array.prototype.forEach.call(record.addedNodes, function (node) {
            if (node.nodeType !== 1) return;

            if (node.classList && node.classList.contains('rs-reveal')) watch(node, 0);

            if (node.querySelectorAll) scan(node);
          });
        });
      }).observe(document.body, { childList: true, subtree: true });
    }
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

    // One source of truth for the timing: the CSS fill reads this, so changing
    // DELAY moves the bar with it rather than leaving the two out of step.
    hero.style.setProperty('--rs-slide-ms', DELAY + 'ms');

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

        /*
         * Restart the fill.
         *
         * Removing and re-adding the class is not enough on its own: the
         * browser coalesces both into one style recalculation and the animation
         * never restarts. Reading offsetWidth in between forces the reflow that
         * makes it take.
         */
        void dots[next].offsetWidth;
      }

      current = next;
    }

    function advance() { show((current + 1) % slides.length); }

    function start() {
      // Someone who has asked for less motion should not get a carousel that
      // moves on its own. The dots still work.
      if (calm.matches || slides.length < 2) return;
      if (timer) return;

      hero.classList.remove('is-paused');
      startedAt = Date.now();

      /*
       * The CSS animation is paused by .is-paused rather than restarted, so it
       * simply continues — no reflow, no jump. The timer has to be told how
       * much of the delay is already spent, which is what the one-shot timeout
       * below does before handing back to the steady interval.
       */
      var remaining = Math.max(400, DELAY - elapsed);

      timer = window.setTimeout(function () {
        advance();
        elapsed = 0;
        startedAt = Date.now();
        timer = window.setInterval(function () {
          advance();
          startedAt = Date.now();
        }, DELAY);
      }, remaining);
    }

    /*
     * Pausing has to remember WHEN it happened.
     *
     * setInterval has no notion of elapsed time, so resuming with a fresh
     * interval restarts the full delay however briefly the pointer rested on
     * the slide — and the CSS fill restarted with it. Recording the elapsed
     * portion lets both carry on from where they stopped.
     */
    var startedAt = 0;
    var elapsed = 0;

    function stop() {
      if (timer) {
        // Either kind — play() uses a timeout first, then an interval.
        window.clearTimeout(timer);
        window.clearInterval(timer);
        timer = null;
        elapsed += Date.now() - startedAt;
      }

      hero.classList.add('is-paused');
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

  // ---------------------------------------------------- filters, in place
  var filters = document.querySelector('[data-filters]');

  if (filters) {
    // The manual Apply button only exists for the no-script case.
    var manual = filters.querySelector('[data-filters-manual]');
    if (manual) manual.hidden = true;

    var pending = null;
    var inflight = null;

    /*
     * Fetch the filtered page and swap in just the parts that changed.
     *
     * A full reload throws away the reader's scroll position and collapses
     * every open facet, which on a long sidebar is most of the work they just
     * did. The URL is still updated, so the back button and a copied link both
     * behave exactly as they would have.
     */
    function apply() {
      var data = new FormData(filters);
      var qs = new URLSearchParams();

      data.forEach(function (v, k) {
        if (String(v) !== '') qs.append(k, v);
      });

      var url = window.location.pathname + (qs.toString() ? '?' + qs.toString() : '');
      var grid = document.querySelector('[data-results]');

      if (grid) grid.setAttribute('aria-busy', 'true');

      // A newer request supersedes an older one; without this a slow first
      // response can land after a fast second and show the wrong results.
      if (inflight) inflight.abort();
      inflight = new AbortController();

      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: inflight.signal })
        .then(function (r) { return r.ok ? r.text() : Promise.reject(r); })
        .then(function (html) {
          var doc = new DOMParser().parseFromString(html, 'text/html');

          /*
           * Swap the whole results REGION, not just the grid.
           *
           * The grid only exists when there are products, so filtering down to
           * nothing left the script with no target — and the "nothing matches"
           * message, which lives beside the grid rather than inside it, never
           * appeared. [data-results] always exists and holds whichever of the
           * two the server rendered.
           */
          ['[data-results]', '[data-result-count]', '[data-chips]'].forEach(function (sel) {
            var next = doc.querySelector(sel);
            var here = document.querySelector(sel);

            if (!here) return;

            // A section the new page does not have at all — the chips row when
            // the last filter is cleared — is emptied rather than left stale.
            here.innerHTML = next ? next.innerHTML : '';
          });

          // Facet counts move as filters narrow, but replacing the whole
          // sidebar would close every accordion and lose focus mid-interaction.
          doc.querySelectorAll('[data-facet-count]').forEach(function (next) {
            var here = document.querySelector('[data-facet-count="' + next.getAttribute('data-facet-count') + '"]');
            if (here) here.textContent = next.textContent;
          });

          window.history.pushState({}, '', url);
        })
        .catch(function (e) {
          if (e && e.name === 'AbortError') return;
          window.location.href = url;   // fall back to a real navigation
        })
        .finally(function () {
          if (grid) grid.removeAttribute('aria-busy');
        });
    }

    filters.addEventListener('change', function (e) {
      if (!e.target.matches('input')) return;

      // A short debounce, so ticking three boxes in a row is one request
      // rather than three — and so a slider drag does not fire per pixel.
      window.clearTimeout(pending);
      pending = window.setTimeout(apply, 350);
    });

    // The back button must undo a filter, not leave the page stale.
    window.addEventListener('popstate', function () { window.location.reload(); });
  }

  // ------------------------------------------------- one facet open at a time
  document.querySelectorAll('.rs-facet').forEach(function (facet) {
    facet.addEventListener('toggle', function () {
      if (!facet.open) return;

      document.querySelectorAll('.rs-facet[open]').forEach(function (other) {
        if (other !== facet) other.open = false;
      });
    });
  });

  // ------------------------------------------------------- price range slider
  document.querySelectorAll('[data-range]').forEach(function (range) {
    var from = range.querySelector('[data-range-from]');
    var to = range.querySelector('[data-range-to]');
    var fill = range.querySelector('[data-range-fill]');
    var lo = range.querySelector('[data-range-lo]');
    var hi = range.querySelector('[data-range-hi]');
    var min = Number(range.getAttribute('data-min'));
    var max = Number(range.getAttribute('data-max'));

    function money(n) {
      return '\u20B9\u00A0' + Number(n).toLocaleString('en-IN');
    }

    function paint() {
      // The handles must not cross. Whichever moved gives way.
      if (Number(from.value) > Number(to.value)) {
        if (document.activeElement === from) from.value = to.value;
        else to.value = from.value;
      }

      var a = ((from.value - min) / (max - min)) * 100;
      var b = ((to.value - min) / (max - min)) * 100;

      fill.style.left = a + '%';
      fill.style.width = (b - a) + '%';
      lo.textContent = money(from.value);
      hi.textContent = money(to.value);
    }

    [from, to].forEach(function (el) { el.addEventListener('input', paint); });
    paint();
  });

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

/**
 * Infinite slider.
 *
 * HOW THE LOOP WORKS
 *
 * The set is cloned until the track is comfortably wider than the viewport,
 * then, whenever the reader passes the end of the first copy, that copy's width
 * is subtracted from scrollLeft. The jump is invisible because the pixels on
 * either side of it are identical — the reader is looking at a clone of what
 * they were just looking at.
 *
 * Built on a native scroller rather than a transform, so touch keeps its
 * momentum, the keyboard reaches every tile, and a browser that never runs this
 * file still gets a usable horizontal scroller.
 *
 * Clones are aria-hidden with their links taken out of the tab order, or a
 * screen reader would announce every occasion two or three times.
 */
(function () {
  'use strict';

  var calm = window.matchMedia('(prefers-reduced-motion: reduce)');

  document.querySelectorAll('[data-loop]').forEach(function (root) {
    var track = root.querySelector('[data-loop-track]');
    if (!track) return;

    var originals = Array.prototype.slice.call(track.children);
    if (!originals.length) return;

    var setWidth = 0;
    var timer = null;
    var dragging = false;
    var prev = root.querySelector('[data-loop-prev]');
    var next = root.querySelector('[data-loop-next]');

    function measure() {
      // The width of ONE original set, including the gap after it.
      var gap = parseFloat(getComputedStyle(track).columnGap) || 0;
      setWidth = originals.reduce(function (sum, el) {
        return sum + el.getBoundingClientRect().width + gap;
      }, 0);
    }

    function clone() {
      /*
       * THREE copies at minimum, whatever the widths.
       *
       * The wrap starts at one set in and jumps back at two sets in, so the
       * track must hold at least two sets plus a viewport. Cloning only until
       * the track was three viewports wide looked equivalent and is not: when a
       * single set is ALREADY wider than three viewports — four tiles on a
       * phone, twelve on an ultrawide — the loop never cloned, scrollLeft never
       * reached the wrap point, and the slider simply ran to the end and
       * stopped. Caught by simulating the arithmetic rather than by looking at
       * it.
       */
      var copies = 1;
      var guard = 0;

      while ((copies < 3 || track.scrollWidth < track.clientWidth * 3) && guard < 12) {
        copies++;
        originals.forEach(function (el) {
          var copy = el.cloneNode(true);
          copy.setAttribute('aria-hidden', 'true');
          copy.querySelectorAll('a, button').forEach(function (control) {
            control.setAttribute('tabindex', '-1');
          });
          track.appendChild(copy);
        });
        guard++;
      }
    }

    function wrap() {
      if (setWidth <= 0) return;

      // Past the first copy: step back one set. Before the start: step forward.
      if (track.scrollLeft >= setWidth * 2) {
        track.scrollLeft -= setWidth;
      } else if (track.scrollLeft < setWidth * 0.5) {
        track.scrollLeft += setWidth;
      }
    }

    function step(direction) {
      var first = track.firstElementChild;
      var gap = parseFloat(getComputedStyle(track).columnGap) || 16;
      var by = first ? first.getBoundingClientRect().width + gap : track.clientWidth * 0.8;

      track.scrollBy({ left: by * direction, behavior: 'smooth' });
    }

    function play() {
      // Autoplay is motion nobody asked for; honour a stated preference.
      if (calm.matches) return;
      stop();
      timer = window.setInterval(function () {
        if (!dragging) step(1);
      }, 3800);
    }

    function stop() {
      if (timer) { window.clearInterval(timer); timer = null; }
    }

    /*
     * Some loops only apply below a breakpoint — the collections row is a boxed
     * grid on desktop and a slider on a phone. Cloning on the desktop side
     * would fill the grid with duplicate tiles, so the whole thing is torn down
     * and rebuilt when the breakpoint is crossed rather than set up once.
     */
    var only = root.getAttribute('data-loop') === 'mobile'
      ? window.matchMedia('(max-width: ' + (root.getAttribute('data-loop-below') || '768') + 'px)')
      : null;

    if (only) {
      var onChange = function () {
        if (only.matches) {
          start();
        } else {
          teardown();
        }
      };

      // addEventListener on a MediaQueryList is not in older Safari; addListener
      // is deprecated but still the only thing that works there.
      only.addEventListener ? only.addEventListener('change', onChange) : only.addListener(onChange);
      onChange();

      return;
    }

    start();

    function teardown() {
      stop();

      // Remove every clone, leaving exactly what the server rendered.
      Array.prototype.slice.call(track.children).forEach(function (el) {
        if (el.getAttribute('aria-hidden') === 'true') el.remove();
      });

      track.scrollLeft = 0;
      root.classList.add('is-static');

      if (prev) prev.hidden = true;
      if (next) next.hidden = true;
    }

    function start() {
      root.classList.remove('is-static');
      setUp();
    }

    function setUp() {
    // ---- set up ----
    measure();

    /*
     * Only loop when there is actually something to loop.
     *
     * The cloning is what makes an infinite row possible, but with one or two
     * items it fills the screen with copies of the same tile — which is what a
     * shop with a single occasion actually saw: thirteen identical Diwali
     * cards. A row that fits should simply BE a row.
     *
     * Measured against the container, not a count, because "enough" depends on
     * the card width and the viewport, not on how many records exist.
     */
    /*
     * A few pixels of tolerance. Three cards sized to exactly a third of the
     * track still measure a fraction wider because of sub-pixel rounding, and
     * without slack a row that visually fits would turn itself into a slider.
     */
    var overflows = track.scrollWidth > track.clientWidth + 24;

    if (!overflows) {
      root.classList.add('is-static');

      // Nothing to scroll: no arrows, no autoplay, no drag.
      if (prev) prev.hidden = true;
      if (next) next.hidden = true;

      return;
    }

    clone();
    measure();

    // Start one set in, so scrolling backwards has somewhere to go immediately.
    track.scrollLeft = setWidth;

    if (prev) { prev.hidden = false; prev.addEventListener('click', function () { step(-1); }); }
    if (next) { next.hidden = false; next.addEventListener('click', function () { step(1); }); }

    track.addEventListener('scroll', wrap, { passive: true });

    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', play);
    root.addEventListener('focusin', stop);
    root.addEventListener('focusout', play);
    document.addEventListener('visibilitychange', function () {
      document.hidden ? stop() : play();
    });

    // Re-measure when the layout changes: the card width is driven by CSS that
    // depends on the viewport, so a stale setWidth makes the loop jump visibly.
    if ('ResizeObserver' in window) {
      var pending = null;

      new ResizeObserver(function () {
        window.clearTimeout(pending);
        pending = window.setTimeout(function () {
          var ratio = setWidth > 0 ? track.scrollLeft / setWidth : 1;
          measure();
          track.scrollLeft = setWidth * ratio;
        }, 150);
      }).observe(track);
    }

    // ---- drag with a mouse ----
    var startX = 0;
    var startScroll = 0;

    track.addEventListener('pointerdown', function (e) {
      // Touch already scrolls natively; hijacking it would break momentum.
      if (e.pointerType === 'touch') return;

      dragging = true;
      startX = e.clientX;
      startScroll = track.scrollLeft;
      track.classList.add('is-dragging');
      track.setPointerCapture(e.pointerId);
    });

    track.addEventListener('pointermove', function (e) {
      if (!dragging) return;
      track.scrollLeft = startScroll - (e.clientX - startX);
    });

    ['pointerup', 'pointercancel'].forEach(function (type) {
      track.addEventListener(type, function (e) {
        if (!dragging) return;
        dragging = false;
        track.classList.remove('is-dragging');

        // A drag that moved is not a click: swallow the link activation that
        // would otherwise fire when the finger lifts over a tile.
        if (Math.abs(e.clientX - startX) > 6) {
          track.addEventListener('click', function guard(ev) {
            ev.preventDefault();
            ev.stopPropagation();
            track.removeEventListener('click', guard, true);
          }, true);
        }
      });
    });

    play();
    }
  });
})();

/**
 * The sign-in / create-account switch.
 *
 * Both panes are in the markup and CSS decides which is visible, so this only
 * flips an attribute. With no JavaScript the links still work — they carry a
 * ?mode= that the server honours — which is why the anchors have real hrefs.
 */
(function () {
  'use strict';

  var auth = document.querySelector('[data-auth]');
  if (!auth) return;

  function show(mode) {
    auth.setAttribute('data-mode', mode);

    // Keep the URL honest, so a reload or a back button lands on the same pane.
    var url = new URL(window.location.href);
    url.searchParams.set('mode', mode);
    url.hash = '';
    window.history.replaceState({}, '', url);

    // Move focus to the pane that just appeared, or a keyboard user is left
    // tabbing through a hidden form.
    var pane = auth.querySelector('[data-auth-pane="' + mode + '"]');
    var first = pane && pane.querySelector('input, button, a');
    if (first) first.focus({ preventScroll: true });
  }

  auth.querySelectorAll('[data-auth-switch]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      show(el.getAttribute('data-auth-switch'));
    });
  });

  // The panel's button follows whichever pane is hidden.
  var cta = auth.querySelector('[data-panel-cta]');

  if (cta) {
    new MutationObserver(function () {
      var register = auth.getAttribute('data-mode') === 'register';
      var next = register ? 'login' : 'register';

      cta.setAttribute('data-auth-switch', next);
      cta.textContent = register ? 'I already have an account' : 'Create an account';

      // It is a real link now, so its href has to follow — otherwise a
      // middle-click or "open in new tab" lands on the wrong pane.
      if (cta.tagName === 'A') {
        var href = new URL(cta.href, window.location.origin);
        href.searchParams.set('mode', next);
        cta.href = href.toString();
      }
    }).observe(auth, { attributes: true, attributeFilter: ['data-mode'] });
  }
})();

/** The code field: digits only, and submit as soon as six are in. */
(function () {
  'use strict';

  var otp = document.querySelector('[data-otp]');
  if (!otp) return;

  otp.addEventListener('input', function () {
    var digits = otp.value.replace(/\D/g, '').slice(0, 6);

    if (digits !== otp.value) otp.value = digits;

    // Six digits is the whole code — asking someone to then find the button is
    // a step for nothing. Only on a real six-digit entry, never on a paste of
    // something longer that was trimmed.
    if (digits.length === 6 && otp.form) otp.form.requestSubmit();
  });
})();

/**
 * The wishlist heart.
 *
 * Upgrades the form each heart already sits in: submit is intercepted, the
 * request goes in the background, and the heart fills in place. With no
 * JavaScript the form still posts and the page still works — which is why the
 * markup is a form and not a bare button.
 */
(function () {
  'use strict';

  var modal = document.querySelector('[data-auth-modal]');
  var lastFocus = null;

  function openModal() {
    if (!modal) return;
    lastFocus = document.activeElement;
    modal.hidden = false;
    document.body.style.overflow = 'hidden';

    var first = modal.querySelector('a, button');
    if (first) first.focus();
  }

  function closeModal() {
    if (!modal || modal.hidden) return;
    modal.hidden = true;
    document.body.style.overflow = '';
    // Focus goes back where it was, or the reader is dropped at the top.
    if (lastFocus) lastFocus.focus();
  }

  if (modal) {
    modal.addEventListener('click', function (e) {
      // The backdrop closes it; the card does not.
      if (e.target === modal || e.target.closest('[data-modal-close]')) closeModal();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeModal();
    });
  }

  function setCount(n) {
    document.querySelectorAll('[data-wish-count]').forEach(function (el) {
      el.textContent = n;
      el.hidden = n < 1;
    });
  }

  document.addEventListener('submit', function (e) {
    var form = e.target.closest('[data-wish]');
    if (!form) return;

    e.preventDefault();

    var button = form.querySelector('button');
    if (!button || button.disabled) return;

    var data = new FormData(form);

    // Answer the tap immediately. The request may take a moment and a control
    // that does nothing for 300ms feels broken.
    button.disabled = true;
    button.classList.add('is-busy');

    fetch(form.action.replace(/\/toggle$/, '/toggle.json'), {
      method: 'POST',
      body: data,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
      .then(function (json) {
        if (!json.ok) return Promise.reject(json);

        button.setAttribute('aria-pressed', json.saved ? 'true' : 'false');
        button.setAttribute('aria-label', (json.saved ? 'Remove ' : 'Save ') + json.name);
        setCount(json.count);

        /*
         * Refresh EVERY token on the page, not just this form's.
         *
         * CI4 rotates the token on each POST, so after one background request
         * every other form still holds a spent one. The second heart then fails
         * CSRF, the error path submits the form for real, and the browser lands
         * on /wishlist/toggle — which is exactly the bug that was reported.
         */
        if (json.csrf) {
          document.querySelectorAll('input[name^="csrf"]').forEach(function (el) {
            el.value = json.csrf;
          });
        }

        if (json.prompt) openModal();

        /*
         * On the wishlist page itself, un-saving removes the card. Leaving an
         * empty outline on a page whose whole purpose is "things you saved"
         * reads as a bug, and the count in the heading would disagree with what
         * is on screen.
         */
        var card = button.closest('[data-wish-card]');

        if (card && !json.saved) {
          card.remove();

          if (document.querySelectorAll('[data-wish-card]').length === 0) {
            window.location.reload();   // show the empty state properly
          }
        }
      })
      .catch(function () {
        // Fall back to the real form rather than leaving the person stuck.
        form.removeAttribute('data-wish');
        form.submit();
      })
      .finally(function () {
        button.disabled = false;
        button.classList.remove('is-busy');
      });
  });
})();

/**
 * Add to cart, in place, with a quantity stepper.
 *
 * The Add button and the stepper are the same form. Adding swaps one for the
 * other; taking the quantity to zero swaps back. With no JavaScript the Add
 * button posts normally and the stepper is never shown.
 */
(function () {
  'use strict';

  function setCartCount(n) {
    document.querySelectorAll('[data-cart-count]').forEach(function (el) {
      el.textContent = n;
      el.hidden = n < 1;
    });
  }

  function send(form, quantity) {
    var add = form.querySelector('[data-cart-add]');
    var step = form.querySelector('[data-cart-step]');
    var value = form.querySelector('[data-qty-value]');

    var data = new FormData(form);
    data.set('quantity', quantity);

    form.querySelectorAll('button').forEach(function (b) { b.disabled = true; });

    return fetch(form.action.replace(/\/add$/, '/add.json'), {
      method: 'POST',
      body: data,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then(function (r) { return r.json().then(function (j) { return r.ok ? j : Promise.reject(j); }); })
      .then(function (json) {
        // Rotated on every POST — refresh every form, or the next tap anywhere
        // on the page is rejected as a forgery.
        if (json.csrf) {
          document.querySelectorAll('input[name^="csrf"]').forEach(function (el) { el.value = json.csrf; });
        }

        form.setAttribute('data-qty', json.quantity);
        if (value) value.textContent = json.quantity;

        // The service can cap a quantity (stock, basket limit), so the stepper
        // shows what actually landed rather than what was asked for.
        if (add) add.hidden = json.quantity > 0;
        if (step) step.hidden = json.quantity < 1;

        setCartCount(json.count);

        /*
         * Show the basket. Adding something and seeing only a number tick over
         * in the corner is easy to miss; the drawer answers "did that work"
         * without taking the reader off the page they were on.
         */
        if (typeof window.rsOpenCart === 'function') window.rsOpenCart();
      })
      .catch(function (json) {
        if (json && json.csrf) {
          document.querySelectorAll('input[name^="csrf"]').forEach(function (el) { el.value = json.csrf; });
        }

        // Say why rather than doing nothing — "out of stock" is information.
        if (json && json.error) window.alert(json.error);
      })
      .finally(function () {
        form.querySelectorAll('button').forEach(function (b) { b.disabled = false; });
      });
  }

  document.addEventListener('submit', function (e) {
    var form = e.target.closest('[data-cart]');
    if (!form) return;

    /*
     * "Buy now" is a different act, and it lives on the submit button.
     *
     * FormData does NOT carry the submitter's name/value — only `e.submitter`
     * has it. Without reading it the checkout flag never left the browser, and
     * Buy now behaved exactly like Add to cart. It must also SKIP the basket,
     * so it is left to submit normally rather than intercepted.
     */
    if (e.submitter && e.submitter.name === 'checkout') return;

    e.preventDefault();

    /*
     * The quantity the person actually set. This was hard-coded to 1, which
     * made the stepper on the product page decorative — setting five and
     * clicking Add gave you one.
     */
    var field = form.querySelector('[name="quantity"]');
    var want = field ? parseInt(field.value, 10) : 1;

    send(form, want > 0 ? want : 1);
  });

  document.addEventListener('click', function (e) {
    var up = e.target.closest('[data-qty-up]');
    var down = e.target.closest('[data-qty-down]');
    if (!up && !down) return;

    var form = (up || down).closest('[data-cart]');
    if (!form) return;

    e.preventDefault();

    // Absolute, never a delta: two quick taps must not become two requests
    // that each add one to a stale number.
    var now = Number(form.getAttribute('data-qty') || 0);
    send(form, up ? now + 1 : Math.max(0, now - 1));
  });
})();

/**
 * PIN code → city and state.
 *
 * Fills the two fields from one number, so nobody types "Rajasthan" into a form
 * on a phone. They stay readonly because a mismatched state and PIN is a
 * delivery failure, and the PIN is the field the courier actually uses.
 *
 * Scoped to the fieldset the PIN lives in, so the billing lookup cannot
 * overwrite the shipping fields.
 */
(function () {
  'use strict';

  document.querySelectorAll('[data-pin]').forEach(function (input) {
    // The nearest block holding this address, whichever markup wraps it.
    var scope = input.closest('[data-bill-fields], fieldset, form') || document;
    var city = scope.querySelector('[data-pin-city]');
    var state = scope.querySelector('[data-pin-state]');
    var note = scope.querySelector('[data-pin-note]');
    var last = '';

    function say(text, bad) {
      if (!note) return;
      note.textContent = text;
      note.classList.toggle('text-bad', !!bad);
    }

    function fill() {
      var pin = (input.value || '').replace(/\D/g, '').slice(0, 6);

      if (pin !== input.value) input.value = pin;
      if (pin.length !== 6 || pin === last) return;

      last = pin;
      say('Checking…', false);

      fetch('/pincode/' + pin, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (!json.ok) {
            // The fields open up so the customer can type it themselves — a
            // lookup that cannot answer must never block an order.
            if (city) { city.readOnly = false; city.value = ''; }
            if (state) { state.readOnly = false; state.value = ''; }
            say(json.error || 'Please type your city and state.', true);

            return;
          }

          if (city) { city.value = json.city; city.readOnly = true; }
          if (state) { state.value = json.state; state.readOnly = true; }
          say(json.city ? json.city + ', ' + json.state : json.state, false);
        })
        .catch(function () {
          if (city) city.readOnly = false;
          if (state) state.readOnly = false;
          say('Could not check that PIN. Type your city and state.', true);
        });
    }

    input.addEventListener('input', fill);
    input.addEventListener('blur', fill);

    // A form redisplayed after a validation error already has a PIN in it.
    if ((input.value || '').length === 6) fill();
  });

  /**
   * Billing address, shown only when it differs.
   *
   * The box is ticked by default because for most orders the two ARE the same.
   * The fields are `hidden` rather than removed, so a value typed and then
   * re-ticked is still there if the customer changes their mind back.
   */
  var same = document.querySelector('[data-bill-same]');
  var fields = document.querySelector('[data-bill-fields]');

  if (same && fields) {
    var sync = function () { fields.hidden = same.checked; };

    same.addEventListener('change', sync);
    sync();
  }
})();

/**
 * The cart page: change a quantity without reloading.
 *
 * The line total, the basket total and the header badge all move together, so
 * the page never shows two numbers that disagree.
 */
(function () {
  'use strict';

  var table = document.querySelector('[data-cart-lines]');
  if (!table) return;

  function refresh() {
    /*
     * The totals are computed server-side — coupons, shipping bands and
     * gift-box pricing all live there. Recomputing them in the browser would be
     * a second implementation that eventually disagrees with the first.
     */
    fetch(window.location.pathname, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');

        ['[data-cart-lines]', '[data-cart-summary]'].forEach(function (sel) {
          var next = doc.querySelector(sel);
          var here = document.querySelector(sel);
          if (next && here) here.innerHTML = next.innerHTML;
        });

        var badge = doc.querySelector('[data-cart-count]');
        document.querySelectorAll('[data-cart-count]').forEach(function (el) {
          if (!badge) return;
          el.textContent = badge.textContent;
          el.hidden = badge.hidden;
        });
      });
  }

  document.addEventListener('change', function (e) {
    var input = e.target.closest('[data-line-qty]');
    if (!input) return;

    var form = input.closest('form');
    if (!form) return;

    var data = new FormData(form);
    form.setAttribute('aria-busy', 'true');

    fetch(form.action, {
      method: 'POST',
      body: data,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then(function () { refresh(); })
      .catch(function () { form.submit(); })
      .finally(function () { form.removeAttribute('aria-busy'); });
  });
})();

/**
 * The search placeholder types itself.
 *
 * Writes a phrase, pauses, wipes it, writes the next. Purely decorative, so it
 * stops entirely under `prefers-reduced-motion` and the moment the box is
 * focused — nobody wants text moving underneath them while they type.
 */
(function () {
  'use strict';

  var calm = window.matchMedia('(prefers-reduced-motion: reduce)');

  document.querySelectorAll('[data-typer]').forEach(function (input) {
    var phrases;

    try {
      phrases = JSON.parse(input.getAttribute('data-phrases') || '[]');
    } catch (e) {
      return;
    }

    // One phrase is a placeholder, not an animation.
    if (!Array.isArray(phrases) || phrases.length < 2) return;

    var i = 0;
    var pos = 0;
    var wiping = false;
    var timer = null;
    var stopped = false;

    function step() {
      if (stopped) return;

      var word = String(phrases[i] || '');

      pos += wiping ? -1 : 1;
      input.placeholder = word.slice(0, pos);

      var wait = wiping ? 28 : 55;

      if (!wiping && pos >= word.length) {
        // Hold the finished phrase long enough to be read.
        wiping = true;
        wait = 1800;
      } else if (wiping && pos <= 0) {
        wiping = false;
        i = (i + 1) % phrases.length;
        wait = 320;
      }

      timer = window.setTimeout(step, wait);
    }

    function stop() {
      stopped = true;
      window.clearTimeout(timer);
      // Leave a complete phrase behind, never half a word.
      input.placeholder = String(phrases[i] || '');
    }

    // Typing under a moving placeholder is distracting, and on a real search
    // the placeholder is irrelevant the moment there is a value.
    input.addEventListener('focus', stop, { once: true });

    if (calm.matches) return;

    timer = window.setTimeout(step, 900);
  });
})();

/**
 * Variant selection.
 *
 * Picking a colour narrows what else is offered: options that no remaining
 * variant carries are disabled rather than hidden, so the buyer can still SEE
 * that a large antique version exists and is simply unavailable. Hiding them
 * makes a shop look like it does not stock something it does.
 *
 * The URL updates as you choose, so a colour can be linked to and the back
 * button walks the selections.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-variants]');
  if (!root) return;

  var data;

  try {
    data = JSON.parse(root.getAttribute('data-matrix') || '{}');
  } catch (e) {
    return;   // the server-rendered page still works
  }

  var variants = data.variants || [];
  if (!variants.length) return;

  var options = Array.prototype.slice.call(root.querySelectorAll('[data-variant-option]'));
  var chosen = {};

  // Open on whatever the server rendered, so the page and the URL agree.
  var opening = variants.filter(function (v) { return v.key === data.chosen; })[0] || variants[0];

  options.forEach(function (el) {
    var id = Number(el.getAttribute('data-value'));
    if (opening.values.indexOf(id) !== -1) chosen[el.getAttribute('data-code')] = id;
  });

  /*
   * The axes in the order they are shown — colour, then size, then finish.
   *
   * Order matters. A shopper picks a colour and then asks what sizes it comes
   * in, not the reverse, so an axis is narrowed by the ones ABOVE it and never
   * by the ones below.
   */
  var axes = [];

  options.forEach(function (el) {
    var code = el.getAttribute('data-code');
    if (axes.indexOf(code) === -1) axes.push(code);
  });

  /**
   * Does this variant match what is chosen in the axes ABOVE `code`?
   *
   * Only the ones above. Testing against those below is what made Gold
   * unreachable: with Silver and 8" chosen, Gold was measured against 8", there
   * is no Gold 8", so Gold greyed out — and the buyer could never reach the
   * Gold 12" that does exist. A dead end with no way back.
   */
  /**
   * The variant that best honours a click on `valueId` in axis `code`.
   *
   * BIDIRECTIONAL, deliberately.
   *
   * An earlier version only let an axis be narrowed by the ones above it, so
   * choosing a size could never move the colour. But the axes are genuinely
   * interrelated: if 2 cm exists only in blue, choosing 2 cm MUST mean blue.
   * Refusing to move the colour would either grey out a size the shop stocks or
   * leave the page on a combination that cannot be bought.
   *
   * Among the variants carrying the clicked value, the winner is whichever
   * keeps the most of the buyer's other choices — so nothing moves that does
   * not have to. Ties break towards stock.
   */
  function bestFor(code, valueId) {
    var pool = variants.filter(function (v) {
      return v.values.indexOf(valueId) !== -1;
    });

    if (!pool.length) return null;

    var best = null;
    var bestScore = -1;

    pool.forEach(function (v) {
      var kept = 0;

      for (var other in chosen) {
        if (other === code) continue;
        if (v.values.indexOf(chosen[other]) !== -1) kept++;
      }

      // Stock is worth less than a kept choice, so it only decides ties.
      var score = (kept * 10) + (v.stock > 0 ? 1 : 0);

      if (score > bestScore) {
        bestScore = score;
        best = v;
      }
    });

    return best;
  }

  /** Adopt every value of a variant as the current selection. */
  function adopt(variant) {
    options.forEach(function (el) {
      var id = Number(el.getAttribute('data-value'));

      if (variant.values.indexOf(id) !== -1) chosen[el.getAttribute('data-code')] = id;
    });
  }

  function currentVariant() {
    var wanted = [];
    for (var code in chosen) wanted.push(chosen[code]);

    return variants.filter(function (v) {
      return wanted.every(function (id) { return v.values.indexOf(id) !== -1; });
    })[0] || null;
  }

  function paint() {
    options.forEach(function (el) {
      var code = el.getAttribute('data-code');
      var id = Number(el.getAttribute('data-value'));
      var input = el.querySelector('input');

      /*
       * Three states, and the distinction matters.
       *
       *  - impossible: no variant carries this value at all. Struck through.
       *  - fits: works with everything currently chosen. Plain.
       *  - would change something: exists, but choosing it moves another axis.
       *    Shown dimmed rather than struck through, and still clickable —
       *    greying it out would hide stock the shop actually has.
       */
      var possible = variants.some(function (v) { return v.values.indexOf(id) !== -1; });

      var fits = variants.some(function (v) {
        if (v.values.indexOf(id) === -1) return false;

        for (var other in chosen) {
          if (other === code) continue;
          if (v.values.indexOf(chosen[other]) === -1) return false;
        }

        return true;
      });

      var stocked = variants.some(function (v) {
        return v.values.indexOf(id) !== -1 && v.stock > 0;
      });

      el.classList.toggle('is-unavailable', !possible);
      el.classList.toggle('is-adjusts', possible && !fits);
      el.classList.toggle('is-soldout', possible && !stocked);

      // Only a genuinely impossible value is disabled. One that merely moves
      // another axis stays clickable — that IS the interrelation working.
      if (input) input.disabled = !possible;

      var on = chosen[code] === id;
      el.classList.toggle('is-chosen', on);
      if (input) input.checked = on;
    });

    // Name the chosen value beside its heading.
    root.querySelectorAll('[data-variant-chosen]').forEach(function (el) {
      var code = el.getAttribute('data-variant-chosen');
      var pick = options.filter(function (o) {
        return o.getAttribute('data-code') === code && Number(o.getAttribute('data-value')) === chosen[code];
      })[0];
      el.textContent = pick ? pick.textContent.trim() : '';
    });

    var variant = currentVariant();
    if (!variant) return;

    var field = document.querySelector('[data-variant-id]');
    if (field) field.value = variant.id || '';

    /*
     * replaceState, not pushState, on every click — a buyer trying three
     * colours should not have to press back three times to leave the page.
     */
    window.history.replaceState({}, '', data.base + '/' + variant.key);

    // Price, SKU and picture follow the selection.
    var priceEl = document.querySelector('[data-variant-price]');
    if (priceEl && variant.price) priceEl.textContent = variant.price;

    var skuEl = document.querySelector('[data-variant-sku]');
    if (skuEl && variant.sku) skuEl.textContent = variant.sku;

    if (variant.image) {
      var main = document.querySelector('[data-gallery-main] img, .rs-gallery__main img');
      if (main) {
        main.src = variant.image;
        // A <picture> source outranks img.src, so it has to be cleared too —
        // otherwise the old photograph stays put on any browser that matched it.
        var pic = main.closest('picture');
        if (pic) pic.querySelectorAll('source').forEach(function (sc) { sc.removeAttribute('srcset'); });
        main.removeAttribute('srcset');
      }
    }

    root.dispatchEvent(new CustomEvent('variant:change', { detail: variant, bubbles: true }));
  }

  root.addEventListener('click', function (e) {
    var el = e.target.closest('[data-variant-option]');
    if (!el) return;

    var input = el.querySelector('input');
    if (input && input.disabled) {
      e.preventDefault();
      return;
    }

    var code = el.getAttribute('data-code');
    var id = Number(el.getAttribute('data-value'));

    // Take the best combination containing what was clicked, and adopt ALL of
    // its values — choosing 2 cm can move the colour, and should.
    var next = bestFor(code, id);

    if (next) {
      adopt(next);
      chosen[code] = id;
    } else {
      chosen[code] = id;
    }

    paint();
  });

  paint();
})();

/**
 * Flash messages as SweetAlert toasts.
 *
 * A toast rather than a modal: a confirmation nobody asked for should not make
 * them dismiss a dialogue before carrying on. Errors stay longer and do not
 * auto-dismiss on hover, because an error unread is an error unfixed.
 */
(function () {
  'use strict';

  var el = document.querySelector('[data-flash]');
  if (!el || typeof window.Swal === 'undefined') return;

  var data;

  try {
    data = JSON.parse(el.getAttribute('data-payload') || '{}');
  } catch (e) {
    return;
  }

  function toast(icon, text, ms) {
    window.Swal.fire({
      toast: true,
      position: 'top-end',
      icon: icon,
      title: text,
      showConfirmButton: false,
      timer: ms,
      timerProgressBar: true,
      customClass: { popup: 'rs-toast' },
      didOpen: function (el2) {
        // Someone reading it should not have it vanish mid-sentence.
        el2.addEventListener('mouseenter', window.Swal.stopTimer);
        el2.addEventListener('mouseleave', window.Swal.resumeTimer);
      },
    });
  }

  if (data.error) {
    // Longer, because a problem needs reading and possibly acting on.
    toast('error', data.error, 6500);
  } else if (data.success) {
    toast('success', data.success, 3500);
  }
})();

/**
 * The cart drawer.
 *
 * Opens after an in-place add, so the shopper sees what happened without
 * leaving the page they were reading. Its contents are FETCHED rather than held
 * in the page: a basket rendered at page load is out of date the moment
 * anything changes, and this way the totals always come from PricingService.
 */
(function () {
  'use strict';

  var drawer = document.querySelector('[data-cart-drawer]');
  if (!drawer) return;

  var body = drawer.querySelector('[data-drawer-body]');
  var lastFocus = null;

  function load() {
    return fetch('/cart/drawer', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then(function (r) { return r.text(); })
      .then(function (html) { body.innerHTML = html; })
      .catch(function () {
        // Never leave a spinner behind: say so and offer the real page.
        body.innerHTML = '<p class="rs-help p-6">Could not load your basket. '
          + '<a class="rs-link" href="/cart">Open it in full</a>.</p>';
      });
  }

  function open() {
    lastFocus = document.activeElement;
    drawer.hidden = false;

    /*
     * Confirm the panel is actually on screen before locking the page.
     *
     * A CSS name collision once left this open but invisible, and the scroll
     * lock turned that into a frozen page with nothing on it — the worst
     * possible failure, because it looks like the site has crashed. If the
     * panel has no width, the drawer is broken: send the reader to the real
     * basket page instead.
     */
    var panel = drawer.querySelector('[class*="__panel"]');

    if (!panel || panel.getBoundingClientRect().width < 40) {
      drawer.hidden = true;
      window.location.href = '/cart';

      return;
    }

    document.body.style.overflow = 'hidden';

    load().then(function () {
      var first = drawer.querySelector('button, a');
      if (first) first.focus();
    });
  }

  function close() {
    drawer.hidden = true;
    document.body.style.overflow = '';
    if (lastFocus) lastFocus.focus();
  }

  drawer.addEventListener('click', function (e) {
    if (e.target.closest('[data-drawer-close]')) close();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !drawer.hidden) close();
  });

  // The basket icon opens it instead of navigating.
  document.querySelectorAll('a[href$="/cart"]').forEach(function (link) {
    if (link.closest('[data-cart-drawer]')) return;   // not the "view full basket" link

    link.addEventListener('click', function (e) {
      e.preventDefault();
      open();
    });
  });

  /*
   * Quantity and remove inside the drawer.
   *
   * The same endpoints the cart page posts to, so one set of rules governs
   * both. The whole drawer is reloaded afterwards rather than patched — the
   * totals change, and a patched line beside a stale total is the bug this
   * avoids.
   */
  drawer.addEventListener('submit', function (e) {
    var form = e.target.closest('[data-drawer-qty]');
    if (!form) return;

    e.preventDefault();

    var data = new FormData(form);

    // A submit button's own name/value is not in FormData unless it was the
    // button clicked, and `submitter` is how that is read.
    if (e.submitter && e.submitter.name) data.set(e.submitter.name, e.submitter.value);

    fetch(form.action, {
      method: 'POST',
      body: data,
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then(function () { return load(); })
      .then(function () {
        // Keep the header badge honest.
        return fetch('/cart', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { return r.text(); })
          .then(function (html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var next = doc.querySelector('[data-cart-count]');

            document.querySelectorAll('[data-cart-count]').forEach(function (el) {
              if (!next) return;
              el.textContent = next.textContent;
              el.hidden = next.hidden;
            });
          });
      });
  });

  // Opened by the add-to-cart handler.
  window.rsOpenCart = open;
})();

/**
 * The bulk enquiry dialogue.
 *
 * Opened from any [data-bulk-enquiry] button, which carries the product it is
 * asking about. Delegated, so buttons swapped in by a filter work too.
 */
(function () {
  'use strict';

  var modal = document.querySelector('[data-bulk-modal]');
  if (!modal) return;

  var nameLine = modal.querySelector('[data-bulk-product]');
  var idField = modal.querySelector('[data-bulk-product-id]');
  var lastFocus = null;

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-bulk-enquiry]');
    if (!btn) return;

    e.preventDefault();
    lastFocus = btn;

    nameLine.textContent = btn.getAttribute('data-product-name') || '';
    idField.value = btn.getAttribute('data-product-id') || '';

    modal.hidden = false;
    document.body.style.overflow = 'hidden';

    var first = modal.querySelector('input[name="name"]');
    if (first) first.focus();
  });

  function close() {
    modal.hidden = true;
    document.body.style.overflow = '';
    if (lastFocus) lastFocus.focus();
  }

  modal.addEventListener('click', function (e) {
    if (e.target.closest('[data-bulk-close]')) close();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.hidden) close();
  });
})();
