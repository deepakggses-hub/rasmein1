/**
 * Admin charts — Chart.js 4 (MIT, vendored).
 *
 * HOW DATA GETS HERE, AND WHY THAT WAY
 *
 * Each chart reads a <script type="application/json"> block sitting beside its
 * canvas. That element type is inert — the browser never executes it — so the
 * page needs no inline JavaScript and stays compatible with the Content
 * Security Policy that is due to be switched on. The server writes the JSON
 * with JSON_HEX_TAG and friends, so a product named "</script>" cannot break
 * out of the block.
 *
 * Charts AUGMENT the tables and lists already on the page; they never replace
 * them. If Chart.js fails to load, the numbers are all still readable — which
 * also means a screen reader user is not left with an empty canvas.
 */
(function () {
  'use strict';

  if (typeof window.Chart === 'undefined') return;

  /* Read the brand palette from CSS rather than restating it here, so a change
     to the design tokens reaches the charts too. */
  function token(name, fallback) {
    var value = getComputedStyle(document.documentElement).getPropertyValue(name);
    return value && value.trim() !== '' ? value.trim() : fallback;
  }

  var ink = token('--color-ink', '#2C2333');
  var muted = token('--color-ink-muted', '#6B6070');
  var line = token('--color-shell-line', '#E3D6CE');
  var mulberry = token('--color-mulberry', '#5E1F3D');
  var brass = token('--color-brass', '#A8814B');

  /* A sequence that stays distinguishable in print and for most kinds of colour
     blindness — it varies lightness as well as hue rather than relying on hue
     alone. */
  var series = [mulberry, brass, '#7D8C5C', '#8C5A72', '#C2A878', '#4A5D6B', '#B08968', '#6B6070'];

  window.Chart.defaults.font.family = token('--font-body', 'Karla, sans-serif');
  window.Chart.defaults.font.size = 11;
  window.Chart.defaults.color = muted;
  window.Chart.defaults.plugins.legend.labels.usePointStyle = true;
  window.Chart.defaults.plugins.legend.labels.boxWidth = 8;
  window.Chart.defaults.plugins.legend.labels.padding = 14;

  var money = new Intl.NumberFormat('en-IN', {
    style: 'currency', currency: 'INR', maximumFractionDigits: 0
  });

  function tooltip(isMoney) {
    return {
      backgroundColor: ink,
      titleFont: { size: 12, weight: '600' },
      bodyFont: { size: 12 },
      padding: 10,
      cornerRadius: 0,
      displayColors: true,
      boxPadding: 4,
      callbacks: {
        label: function (context) {
          var label = context.dataset.label ? context.dataset.label + ': ' : '';
          var value = context.parsed.y !== undefined && context.parsed.y !== null
            ? context.parsed.y
            : context.parsed;
          return label + (isMoney ? money.format(value) : value);
        }
      }
    };
  }

  function payload(canvas) {
    var holder = canvas.parentElement.querySelector('script[type="application/json"]');
    if (!holder) return null;
    try {
      return JSON.parse(holder.textContent || '{}');
    } catch (e) {
      return null;
    }
  }

  var builders = {
    /* Revenue over time. Bars rather than a line: daily takings are discrete
       events, and a line implies a continuity that is not there. */
    revenue: function (data) {
      return {
        type: 'bar',
        data: {
          labels: data.labels,
          datasets: [{
            label: 'Revenue',
            data: data.values,
            backgroundColor: mulberry,
            hoverBackgroundColor: brass,
            borderWidth: 0,
            borderRadius: 0,
            maxBarThickness: 34
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false }, tooltip: tooltip(true) },
          scales: {
            x: { grid: { display: false }, border: { color: line } },
            y: {
              beginAtZero: true,
              border: { display: false },
              grid: { color: line, drawTicks: false },
              ticks: {
                maxTicksLimit: 5,
                callback: function (v) { return v >= 1000 ? (v / 1000) + 'k' : v; }
              }
            }
          }
        }
      };
    },

    /* Composition. A doughnut rather than a pie: the hole gives room for a
       total, and comparing arc lengths is easier than comparing wedge areas. */
    doughnut: function (data) {
      return {
        type: 'doughnut',
        data: {
          labels: data.labels,
          datasets: [{
            data: data.values,
            backgroundColor: series,
            borderColor: '#fff',
            borderWidth: 2,
            hoverOffset: 6
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '58%',
          plugins: {
            legend: { position: 'right', labels: { padding: 10 } },
            tooltip: tooltip(Boolean(data.money))
          }
        }
      };
    },

    /* Ranked comparison, horizontal so long product names stay readable. */
    ranked: function (data) {
      return {
        type: 'bar',
        data: {
          labels: data.labels,
          datasets: [{
            label: data.label || 'Units',
            data: data.values,
            backgroundColor: brass,
            hoverBackgroundColor: mulberry,
            borderWidth: 0,
            maxBarThickness: 20
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false }, tooltip: tooltip(Boolean(data.money)) },
          scales: {
            x: {
              beginAtZero: true,
              border: { display: false },
              grid: { color: line, drawTicks: false },
              ticks: { precision: 0 }
            },
            y: { grid: { display: false }, border: { color: line } }
          }
        }
      };
    }
  };

  document.querySelectorAll('canvas[data-chart]').forEach(function (canvas) {
    var kind = canvas.getAttribute('data-chart');
    var build = builders[kind];
    if (!build) return;

    var data = payload(canvas);
    if (!data || !Array.isArray(data.labels) || data.labels.length === 0) {
      // Nothing to draw. Leave whatever empty-state the server rendered.
      var empty = canvas.parentElement.querySelector('[data-chart-empty]');
      if (empty) empty.removeAttribute('hidden');
      canvas.setAttribute('hidden', 'hidden');
      return;
    }

    try {
      new window.Chart(canvas.getContext('2d'), build(data));
    } catch (e) {
      canvas.setAttribute('hidden', 'hidden');
    }
  });
})();
