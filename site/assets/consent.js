/*
 * Vokso — bandeau cookies et chargement de la mesure d'audience, partagés par
 * la vitrine, les pages épisode / catégorie et l'application (/app, /admin).
 *
 * - SEO Monitor (auto-hébergé) est toujours chargé en mode « cookieless » :
 *   aucun identifiant stocké dans le navigateur. Le mode « cookie » (traceur
 *   sm_vid conservé 13 mois) n'est activé qu'après un accord explicite.
 * - « Refuser » est aussi simple qu'« Accepter », au même niveau ; fermer le
 *   bandeau sans choisir ne vaut pas accord.
 * - Le choix est conservé 6 mois dans le navigateur, puis redemandé ; il peut
 *   être modifié à tout moment (lien « Gérer les cookies », [data-consent-open]).
 * - Chaque choix est journalisé côté serveur (POST /api/consent) avec un
 *   identifiant aléatoire et la version du bandeau : preuve du consentement
 *   exportable depuis l'administration (article 7.1 du RGPD).
 *
 * API : window.VoksoConsent.open() / .status().
 */
(function () {
  'use strict';

  var VERSION = '2026-10';
  var KEY = 'vokso_consent';
  var VALIDITY_MS = 182 * 24 * 3600 * 1000;
  var TRACKER = 'https://vps-3962b7dc.vps.ovh.net/seo-monitor/p/s.js';
  var SITE_ID = '6ByocZFJ';

  function read() {
    try {
      var v = JSON.parse(localStorage.getItem(KEY) || 'null');
      if (!v || v.version !== VERSION || (v.choice !== 'accepted' && v.choice !== 'refused')) return null;
      if (Date.now() - new Date(v.date).getTime() > VALIDITY_MS) return null;
      return v;
    } catch (e) {
      return null;
    }
  }

  function uuid() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    var b = new Uint8Array(16);
    (window.crypto || { getRandomValues: function (a) { for (var i = 0; i < a.length; i++) a[i] = Math.random() * 256; return a; } }).getRandomValues(b);
    b[6] = (b[6] & 15) | 64; b[8] = (b[8] & 63) | 128;
    var h = Array.prototype.map.call(b, function (x) { return (x + 256).toString(16).slice(1); }).join('');
    return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) + '-' + h.slice(16, 20) + '-' + h.slice(20);
  }

  function save(choice) {
    var previous = read();
    var record = { choice: choice, date: new Date().toISOString(), version: VERSION, id: (previous && previous.id) || uuid() };
    try {
      localStorage.setItem(KEY, JSON.stringify(record));
      localStorage.removeItem('vokso_cookie_consent');
      localStorage.removeItem('qwai_cookie_consent');
      // Retrait du consentement : l'identifiant de mesure déjà déposé est effacé.
      if (choice === 'refused') localStorage.removeItem('sm_vid');
    } catch (e) { /* stockage indisponible : le bandeau sera redemandé */ }
    try {
      fetch('/api/consent', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ consent_id: record.id, choice: choice, version: VERSION, page: location.pathname }),
        keepalive: true,
        credentials: 'omit'
      })['catch'](function () {});
    } catch (e) { /* journal indisponible */ }
    return record;
  }

  function loadTracker() {
    if (document.querySelector('script[data-site="' + SITE_ID + '"]')) return;
    var s = document.createElement('script');
    s.defer = true;
    s.src = TRACKER;
    s.setAttribute('data-site', SITE_ID);
    var v = read();
    if (v && v.choice === 'accepted') s.setAttribute('data-mode', 'cookie');
    document.head.appendChild(s);
  }

  var STYLE = '#vk-consent{position:fixed;left:16px;right:16px;bottom:16px;z-index:2000;max-width:720px;margin:0 auto;'
    + 'background:var(--vk-card,#fbf8f2);color:var(--vk-ink,#17140f);border:1px solid var(--vk-line,#d8cfbf);'
    + 'border-radius:var(--vk-radius,10px);box-shadow:var(--vk-shadow,0 10px 30px -12px rgba(0,0,0,.35));padding:16px 18px;'
    + 'font:15px/1.5 var(--vk-font,system-ui,sans-serif)}'
    + '#vk-consent p{margin:0 0 12px}#vk-consent a{color:inherit;text-decoration:underline}'
    + '#vk-consent .vkc-title{font-weight:700;margin-bottom:6px}'
    + '#vk-consent .vkc-actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end}'
    + '#vk-consent button{font:inherit;font-weight:700;padding:9px 18px;border-radius:999px;cursor:pointer;min-width:120px;'
    + 'border:2px solid var(--vk-ink,#17140f);background:transparent;color:var(--vk-ink,#17140f)}'
    + '#vk-consent button:focus-visible{outline:3px solid var(--vk-signal,#f5c400);outline-offset:2px}'
    + '@media (max-width:520px){#vk-consent .vkc-actions button{flex:1 1 100%}}';

  function open() {
    if (document.getElementById('vk-consent')) return;
    if (!document.getElementById('vk-consent-style')) {
      var st = document.createElement('style');
      st.id = 'vk-consent-style';
      st.textContent = STYLE;
      document.head.appendChild(st);
    }
    var box = document.createElement('div');
    box.id = 'vk-consent';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'false');
    box.setAttribute('aria-labelledby', 'vkc-title');
    box.innerHTML = '<p class="vkc-title" id="vkc-title">Vos choix sur les cookies</p>'
      + '<p>Vokso n\'utilise aucun cookie publicitaire ni traceur d\'un tiers. Avec votre accord, notre outil de mesure '
      + 'd\'audience auto-hébergé conserve un identifiant anonyme (13 mois au plus) pour reconnaître les visites répétées. '
      + 'Si vous refusez, la fréquentation reste mesurée sans aucun identifiant stocké. Votre choix est conservé 6 mois et '
      + 'modifiable à tout moment via « Gérer les cookies » en bas de page. '
      + '<a href="/app/politique-de-confidentialite">Politique de confidentialité</a></p>'
      + '<div class="vkc-actions"><button type="button" data-choice="refused">Tout refuser</button>'
      + '<button type="button" data-choice="accepted">Tout accepter</button></div>';
    box.addEventListener('click', function (e) {
      var btn = e.target.closest && e.target.closest('button[data-choice]');
      if (!btn) return;
      var previous = read();
      var record = save(btn.getAttribute('data-choice'));
      box.remove();
      // Le mode du traceur est fixé à son chargement : un accord vaut dès la page
      // suivante ; un retrait recharge la page pour couper aussitôt le traceur.
      if (previous && previous.choice === 'accepted' && record.choice === 'refused') location.reload();
    });
    document.body.appendChild(box);
  }

  document.addEventListener('click', function (e) {
    var link = e.target.closest && e.target.closest('[data-consent-open]');
    if (!link) return;
    e.preventDefault();
    open();
  });

  window.VoksoConsent = {
    open: open,
    status: function () { var v = read(); return v ? v.choice : null; }
  };

  loadTracker();
  function boot() { if (!read()) open(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
