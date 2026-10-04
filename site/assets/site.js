/*
 * Vokso — comportements communs aux pages de la vitrine : menu mobile,
 * apparitions au défilement, bulletin, lecteur persistant et rendu des
 * pochettes d'épisodes. Exposé sous window.Vokso pour les scripts de page.
 */
(function () {
  'use strict';

  var STATIC = '/static';
  var $ = function (id) { return document.getElementById(id); };

  // ---- Utilitaires (mêmes règles que webserver/src/utils/text.ts) ----------
  function cleanTitle(t) {
    return (t || '').replace(/(\*\*|__)(.*?)\1/g, '$2').replace(/[*_`#]/g, '').replace(/\s+/g, ' ').trim();
  }
  function slugify(t) {
    return (t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
      .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
  }
  // URL lisible /podcast/{slug} fournie par l'API ; repli sur l'ancienne
  // forme /podcast/{id}-{titre} (redirigée en 301) pour un épisode sans slug.
  function episodeUrl(ep) {
    if (ep.slug) return '/podcast/' + ep.slug;
    var slug = slugify(ep.title);
    return '/podcast/' + ep.id + (slug ? '-' + slug : '');
  }
  function fmt(sec) {
    if (!isFinite(sec)) return '';
    var m = Math.floor(sec / 60), s = Math.floor(sec % 60);
    return m + ':' + (s < 10 ? '0' : '') + s;
  }
  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }
  function playButton(label) {
    var b = el('button', 'vk-play');
    b.type = 'button';
    b.setAttribute('aria-pressed', 'false');
    b.setAttribute('aria-label', label);
    b.innerHTML = '<svg class="i-play" viewBox="0 0 16 16" aria-hidden="true"><path d="M4 2.5v11a.5.5 0 0 0 .77.42l8.5-5.5a.5.5 0 0 0 0-.84l-8.5-5.5A.5.5 0 0 0 4 2.5z" fill="currentColor"/></svg>'
      + '<svg class="i-pause" viewBox="0 0 16 16" aria-hidden="true"><rect x="3" y="2" width="3.6" height="12" rx="1" fill="currentColor"/><rect x="9.4" y="2" width="3.6" height="12" rx="1" fill="currentColor"/></svg>';
    return b;
  }
  function meter() {
    var m = el('span', 'vk-meter');
    m.setAttribute('aria-hidden', 'true');
    m.innerHTML = '<i></i><i></i><i></i><i></i>';
    return m;
  }
  function image(ep) { return STATIC + '/images/' + ep.image_url; }

  // ---- Année du pied de page, menu mobile ---------------------------------
  document.querySelectorAll('[data-year]').forEach(function (n) { n.textContent = new Date().getFullYear(); });

  var toggleBtn = document.querySelector('.site-nav-toggle');
  var nav = $('site-nav');
  if (toggleBtn && nav) {
    toggleBtn.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a')) { nav.classList.remove('is-open'); toggleBtn.setAttribute('aria-expanded', 'false'); }
    });
  }

  // ---- Apparitions au défilement ------------------------------------------
  var io = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); }
    });
  }, { rootMargin: '0px 0px -8% 0px' }) : null;
  function observeReveals() {
    document.querySelectorAll('.reveal:not(.in)').forEach(function (n) {
      if (io) io.observe(n); else n.classList.add('in');
    });
  }
  observeReveals();

  // ---- Bulletin -------------------------------------------------------------
  var form = $('bulletin-form');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = $('bulletin-msg');
      var email = $('bulletin-email').value.trim();
      msg.className = 'bulletin-msg';
      if (!email || !$('bulletin-email').checkValidity()) {
        msg.className = 'bulletin-msg err';
        msg.textContent = 'Indiquez une adresse e-mail valide.';
        return;
      }
      msg.textContent = 'Envoi en cours…';
      fetch('/api/newsletter', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email, website: $('bulletin-website').value.trim() })
      }).then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
          if (res.ok) {
            msg.className = 'bulletin-msg ok';
            msg.textContent = data.message || 'Inscription confirmée, vérifiez vos mails.';
            form.reset();
          } else {
            msg.className = 'bulletin-msg err';
            msg.textContent = data.message || data.error || "L'inscription a échoué, réessayez.";
          }
        });
      }).catch(function () {
        msg.className = 'bulletin-msg err';
        msg.textContent = "L'inscription a échoué, réessayez.";
      });
    });
  }

  // ---- Lecteur unique -------------------------------------------------------
  var audio = new Audio();
  audio.preload = 'none';
  var current = null;
  var deck = $('deck');

  function syncButtons() {
    var playing = !audio.paused;
    document.querySelectorAll('[data-ep]').forEach(function (node) {
      var on = current && node.getAttribute('data-ep') === current.id && playing;
      node.classList.toggle('is-playing', !!on);
      var btn = node.matches('.vk-play') ? node : node.querySelector('.vk-play');
      if (btn) btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    if (deck) {
      $('deck-play').setAttribute('aria-pressed', playing ? 'true' : 'false');
      deck.classList.toggle('is-playing', playing);
    }
  }

  function toggle(ep) {
    if (current && current.id === ep.id) {
      if (audio.paused) audio.play(); else audio.pause();
      return;
    }
    current = ep;
    audio.src = STATIC + '/audios/' + ep.audio_url;
    audio.play();
    if (deck) {
      $('deck-img').src = image(ep);
      var title = $('deck-title');
      title.textContent = '';
      var a = el('a', null, cleanTitle(ep.title));
      a.href = episodeUrl(ep);
      title.appendChild(a);
      deck.classList.add('is-visible');
    }
  }

  audio.addEventListener('play', syncButtons);
  audio.addEventListener('pause', syncButtons);
  audio.addEventListener('ended', syncButtons);
  if (deck) {
    audio.addEventListener('timeupdate', function () {
      var pct = audio.duration ? (audio.currentTime / audio.duration) * 100 : 0;
      $('deck-progress').style.width = pct + '%';
      $('deck-bar').setAttribute('aria-valuenow', Math.round(pct));
      $('deck-time').textContent = fmt(audio.currentTime) + ' / ' + (fmt(audio.duration) || '–');
    });
    $('deck-play').addEventListener('click', function () { if (current) toggle(current); });
    $('deck-bar').addEventListener('click', function (e) {
      if (!audio.duration) return;
      var r = this.getBoundingClientRect();
      audio.currentTime = Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)) * audio.duration;
    });
    $('deck-bar').addEventListener('keydown', function (e) {
      if (!audio.duration) return;
      if (e.key === 'ArrowRight') audio.currentTime = Math.min(audio.duration, audio.currentTime + 10);
      if (e.key === 'ArrowLeft') audio.currentTime = Math.max(0, audio.currentTime - 10);
    });
  }

  // ---- Pochette d'un épisode --------------------------------------------------
  function sleeve(ep, label, delay) {
    var card = el('article', 'sleeve reveal');
    card.setAttribute('data-ep', ep.id);
    if (delay) card.style.transitionDelay = delay + 'ms';

    var art = el('div', 'sleeve-art');
    var vinyl = el('div', 'vinyl');
    vinyl.setAttribute('aria-hidden', 'true');
    vinyl.style.setProperty('--cover', 'url("' + image(ep) + '")');
    var cover = el('div', 'sleeve-cover');
    var img = el('img');
    img.src = image(ep);
    img.alt = '';
    img.loading = 'lazy';
    img.width = 400; img.height = 400;
    var btn = playButton('Écouter « ' + cleanTitle(ep.title) + ' »');
    btn.addEventListener('click', function () { toggle(ep); });
    cover.appendChild(img);
    cover.appendChild(btn);
    art.appendChild(vinyl);
    art.appendChild(cover);

    var meta = el('div', 'sleeve-meta');
    var dur = el('span', 'vk-label', '');
    meta.appendChild(el('span', 'vk-label', label));
    var right = el('span');
    right.appendChild(dur);
    right.appendChild(meter());
    meta.appendChild(right);

    var h3 = el('h3');
    var a = el('a', null, cleanTitle(ep.title) || 'Sans titre');
    a.href = episodeUrl(ep);
    h3.appendChild(a);

    card.appendChild(art);
    card.appendChild(meta);
    card.appendChild(h3);

    // Durée affichée sans télécharger le fichier : métadonnées seules.
    var probe = new Audio();
    probe.preload = 'metadata';
    probe.addEventListener('loadedmetadata', function () { dur.textContent = fmt(probe.duration); });
    probe.src = STATIC + '/audios/' + ep.audio_url;
    return card;
  }

  window.Vokso = {
    STATIC: STATIC,
    cleanTitle: cleanTitle,
    episodeUrl: episodeUrl,
    fmt: fmt,
    el: el,
    image: image,
    playButton: playButton,
    toggle: toggle,
    syncButtons: syncButtons,
    isPlaying: function (ep) { return !audio.paused && current && current.id === ep.id; },
    sleeve: sleeve,
    observeReveals: observeReveals
  };
})();
