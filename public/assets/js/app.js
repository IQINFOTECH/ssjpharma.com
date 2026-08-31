/* SSJ Pharmaceuticals — public site JS (vanilla, no dependencies, no CDN). */
(function () {
  'use strict';

  // --- Analytics (GA4) + conversion helper (CSP-clean: external, no inline) --
  // The strict CSP is script-src 'self'; keeping all JS in this file avoids
  // inline-script/nonce complexity. GA loads only when a valid ID is present.
  window.dataLayer = window.dataLayer || [];
  function gtag() { dataLayer.push(arguments); }
  window.ssjTrack = function (name, params) {
    try { if (typeof window.gtag === 'function') window.gtag('event', name, params || {}); } catch (e) {}
  };
  var gaId = (document.body && document.body.getAttribute('data-ga-id')) || '';
  if (/^[A-Za-z0-9\-]+$/.test(gaId)) {
    window.gtag = gtag;
    var gs = document.createElement('script');
    gs.async = true;
    gs.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(gaId);
    document.head.appendChild(gs);
    gtag('js', new Date());
    gtag('config', gaId);
  }
  // Fire a conversion event from a whitelisted ?c= marker only (NO PII is ever
  // read from the form or sent to analytics).
  (function () {
    var allowed = { contact_form_submit: 1, product_enquiry_submit: 1, distributor_enquiry_submit: 1, partnership_enquiry_submit: 1 };
    var c = new URLSearchParams(window.location.search).get('c');
    if (c && allowed[c]) window.ssjTrack(c);
  })();

  // --- Mobile navigation drawer ---------------------------------------------
  var drawer = document.getElementById('mobile-drawer');
  var toggles = document.querySelectorAll('.js-nav-toggle');
  var closers = document.querySelectorAll('.js-nav-close');

  function openDrawer() {
    if (!drawer) return;
    drawer.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    toggles.forEach(function (t) { t.setAttribute('aria-expanded', 'true'); });
  }
  function closeDrawer() {
    if (!drawer) return;
    drawer.classList.add('hidden');
    document.body.style.overflow = '';
    toggles.forEach(function (t) { t.setAttribute('aria-expanded', 'false'); });
  }
  toggles.forEach(function (t) { t.addEventListener('click', openDrawer); });
  closers.forEach(function (c) { c.addEventListener('click', closeDrawer); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeDrawer();
  });

  // --- Desktop nav dropdowns: keep aria-expanded honest + Escape to close ----
  // The panel reveals on hover (CSS) and on keyboard focus (CSS focus-within);
  // this only syncs the trigger's aria-expanded state and adds Escape.
  document.querySelectorAll('.js-dropdown').forEach(function (group) {
    var trigger = group.querySelector('.js-dropdown-trigger');
    if (!trigger) return;
    function set(open) { trigger.setAttribute('aria-expanded', open ? 'true' : 'false'); }
    group.addEventListener('mouseenter', function () { set(true); });
    group.addEventListener('mouseleave', function () { set(false); });
    group.addEventListener('focusin', function () { set(true); });
    group.addEventListener('focusout', function (e) { if (!group.contains(e.relatedTarget)) set(false); });
    group.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { set(false); if (trigger.blur) trigger.blur(); }
    });
  });

  // --- Contact form: capture landing page + UTM, submit UX ------------------
  var forms = document.querySelectorAll('.js-contact-form');
  forms.forEach(function (form) {
    var landing = form.querySelector('.js-landing');
    if (landing) landing.value = window.location.pathname + window.location.search;

    var params = new URLSearchParams(window.location.search);
    form.querySelectorAll('.js-utm').forEach(function (input) {
      var key = input.getAttribute('data-utm');
      if (key && params.get(key)) input.value = params.get(key).slice(0, 120);
    });

    form.addEventListener('submit', function () {
      var btn = form.querySelector('.js-submit');
      if (!btn) return;
      var label = form.querySelector('.js-submit-label');
      var spin = form.querySelector('.js-submit-spinner');
      // Let the browser run native required-field validation first.
      if (form.checkValidity && !form.checkValidity()) return;
      btn.setAttribute('disabled', 'disabled');
      if (label) label.classList.add('hidden');
      if (spin) spin.classList.remove('hidden');
    });
  });

  // --- WhatsApp CTA click tracking (best-effort; a click is not a lead) ------
  var meta = document.querySelector('meta[name="csrf-token"]');
  var token = meta ? meta.getAttribute('content') : '';
  var params2 = new URLSearchParams(window.location.search);
  document.querySelectorAll('a[href*="wa.me/"]').forEach(function (a) {
    a.addEventListener('click', function () {
      var waContext = a.getAttribute('data-wa-context') || 'general';
      if (window.ssjTrack) window.ssjTrack('whatsapp_click', { context: waContext });
      try {
        fetch('/whatsapp/track', {
          method: 'POST',
          keepalive: true,
          headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': token },
          body: new URLSearchParams({
            context: a.getAttribute('data-wa-context') || 'general',
            product_id: a.getAttribute('data-wa-product') || '',
            page: window.location.pathname,
            utm_source: params2.get('utm_source') || '',
            utm_medium: params2.get('utm_medium') || '',
            utm_campaign: params2.get('utm_campaign') || ''
          }).toString()
        }).catch(function () {});
      } catch (e) { /* never block the WhatsApp link */ }
      // The link proceeds to wa.me normally (no preventDefault).
    });
  });
})();
