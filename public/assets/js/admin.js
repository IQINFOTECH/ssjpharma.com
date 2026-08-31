/* SSJ Pharmaceuticals — admin JS (vanilla, no dependencies, no CDN).
 * Loaded from 'self' so it satisfies the strict CSP (script-src 'self');
 * inline admin scripts are blocked by that policy, so all admin behaviour
 * lives here. Every block guards on its own elements, so this one file is safe
 * to load on every admin page. */
(function () {
  'use strict';

  // --- Sidebar drawer (mobile) ----------------------------------------------
  (function () {
    var sb = document.getElementById('admin-sidebar');
    var ov = document.querySelector('.js-admin-overlay');
    if (!sb) return;
    function open() { sb.classList.remove('-translate-x-full'); if (ov) ov.classList.remove('hidden'); }
    function close() { sb.classList.add('-translate-x-full'); if (ov) ov.classList.add('hidden'); }
    document.querySelectorAll('.js-admin-toggle').forEach(function (b) { b.addEventListener('click', open); });
    if (ov) ov.addEventListener('click', close);
  })();

  // --- Tabbed forms (product editor, etc.) ----------------------------------
  // Buttons: .js-tab[data-tab="key"]; panels: [data-panel="key"].
  (function () {
    var tabs = document.querySelectorAll('.js-tab');
    var panels = document.querySelectorAll('[data-panel]');
    if (!tabs.length || !panels.length) return;
    function show(key) {
      panels.forEach(function (p) { p.classList.toggle('hidden', p.getAttribute('data-panel') !== key); });
      tabs.forEach(function (t) {
        var on = t.getAttribute('data-tab') === key;
        t.classList.toggle('border-brand-500', on);
        t.classList.toggle('text-brand-700', on);
        t.classList.toggle('border-transparent', !on);
        t.classList.toggle('text-slate-500', !on);
      });
    }
    tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.getAttribute('data-tab')); }); });
    if (location.hash) {
      var k = location.hash.replace('#', '');
      if (document.querySelector('[data-panel="' + k + '"]')) show(k);
    }
  })();

  // --- Clickable table rows: <tr class="js-row-link" data-href="…"> ---------
  // Delegated so it also covers rows added later. Clicks on real controls
  // inside the row (links, buttons, inputs, the row's own forms) are ignored.
  document.addEventListener('click', function (e) {
    var row = e.target.closest('.js-row-link');
    if (!row) return;
    if (e.target.closest('a,button,input,select,textarea,label,form')) return;
    var href = row.getAttribute('data-href');
    if (href) window.location.href = href;
  });

  // --- Confirm before submit: <form class="js-confirm" data-confirm="…"> -----
  // Replaces inline onsubmit="return confirm(…)", which the CSP blocks. Capture
  // phase so it runs before the browser submits.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form && form.classList && form.classList.contains('js-confirm')) {
      var msg = form.getAttribute('data-confirm') || 'Are you sure?';
      if (!window.confirm(msg)) { e.preventDefault(); e.stopPropagation(); }
    }
  }, true);

  // --- Select-all on click: <input class="js-select-on-click"> ---------------
  document.addEventListener('click', function (e) {
    if (e.target.classList && e.target.classList.contains('js-select-on-click')) {
      e.target.select();
    }
  });

  // --- Specification repeater (product editor) ------------------------------
  (function () {
    var add = document.getElementById('spec-add');
    var rows = document.getElementById('spec-rows');
    if (!add || !rows) return;
    add.addEventListener('click', function () {
      var r = rows.querySelector('.js-spec-row');
      if (!r) return;
      var c = r.cloneNode(true);
      c.querySelectorAll('input').forEach(function (i) { i.value = ''; });
      rows.appendChild(c);
    });
    rows.addEventListener('click', function (e) {
      if (!e.target.classList.contains('js-spec-remove')) return;
      var all = rows.querySelectorAll('.js-spec-row');
      var row = e.target.closest('.js-spec-row');
      if (all.length > 1) { row.remove(); }
      else { row.querySelectorAll('input').forEach(function (i) { i.value = ''; }); }
    });
  })();
})();
