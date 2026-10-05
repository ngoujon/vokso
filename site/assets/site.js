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
    if (ep.url) return ep.url;
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
  // « Expert · ≈ 12 min » (champs level_label / duration_minutes de l'API ;
  // la durée demandée n'est qu'une estimation).
  function formatLabel(ep) {
    return [ep.level_label, ep.duration_minutes ? '≈ ' + ep.duration_minutes + ' min' : '']
      .filter(Boolean).join(' · ');
  }

  // ---- Comportements communs, rejoués à chaque page affichée -----------------
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

  function initPage() {
    // Année du pied de page, menu mobile
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

    observeReveals();
    initBulletin();
  }

  // ---- Bulletin -------------------------------------------------------------
  function initBulletin() {
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
  }

  // ---- Lecteur unique -------------------------------------------------------
  // Un seul élément audio pour tout le site ; il survit à la navigation douce,
  // tout comme la barre de lecture (#deck), construite ici une fois pour toutes.
  var audio = new Audio();
  audio.preload = 'none';
  var current = null;

  var deck = el('div', 'deck');
  deck.id = 'deck';
  deck.setAttribute('aria-label', 'Lecteur');
  var deckImg = el('img');
  deckImg.alt = '';
  var deckPlay = playButton('Lecture');
  var deckTitle = el('div', 'deck-title');
  var deckBar = el('div', 'deck-bar');
  deckBar.setAttribute('role', 'slider');
  deckBar.setAttribute('aria-label', 'Position de lecture');
  deckBar.setAttribute('aria-valuemin', '0');
  deckBar.setAttribute('aria-valuemax', '100');
  deckBar.setAttribute('aria-valuenow', '0');
  deckBar.tabIndex = 0;
  var deckProgress = el('span');
  deckBar.appendChild(deckProgress);
  var deckInfo = el('div');
  deckInfo.style.minWidth = '0';
  deckInfo.appendChild(deckTitle);
  deckInfo.appendChild(deckBar);
  var deckTime = el('span', 'deck-time', '0:00');
  deck.appendChild(deckImg);
  deck.appendChild(deckPlay);
  deck.appendChild(deckInfo);
  deck.appendChild(deckTime);
  document.body.appendChild(deck);

  function audioSrc(ep) { return ep.src || STATIC + '/audios/' + ep.audio_url; }
  function coverSrc(ep) { return ep.image_src || image(ep); }

  function syncButtons() {
    var playing = !audio.paused;
    document.querySelectorAll('[data-ep]').forEach(function (node) {
      var on = current && node.getAttribute('data-ep') === current.id && playing;
      node.classList.toggle('is-playing', !!on);
      var btn = node.matches('.vk-play') ? node : node.querySelector('.vk-play');
      if (btn) btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    deckPlay.setAttribute('aria-pressed', playing ? 'true' : 'false');
    deckPlay.setAttribute('aria-label', playing ? 'Pause' : 'Lecture');
    deck.classList.toggle('is-playing', playing);
    document.body.classList.toggle('has-deck', !!current);
  }

  // play() rejette sa promesse si une pause l'interrompt : rien à signaler.
  function play() { var p = audio.play(); if (p && p.catch) p.catch(function () {}); }

  function toggle(ep) {
    if (current && current.id === ep.id) {
      if (audio.paused) play(); else audio.pause();
      return;
    }
    current = ep;
    audio.src = audioSrc(ep);
    play();
    deckImg.src = coverSrc(ep);
    deckTitle.textContent = '';
    var a = el('a', null, cleanTitle(ep.title));
    a.href = episodeUrl(ep);
    deckTitle.appendChild(a);
    deck.classList.add('is-visible');
    if ('mediaSession' in navigator && window.MediaMetadata) {
      navigator.mediaSession.metadata = new MediaMetadata({
        title: cleanTitle(ep.title), artist: 'Vokso',
        artwork: [{ src: new URL(coverSrc(ep), location.href).href }]
      });
    }
    syncButtons();
  }

  audio.addEventListener('play', syncButtons);
  audio.addEventListener('pause', syncButtons);
  audio.addEventListener('ended', syncButtons);
  audio.addEventListener('timeupdate', function () {
    var pct = audio.duration ? (audio.currentTime / audio.duration) * 100 : 0;
    deckProgress.style.width = pct + '%';
    deckBar.setAttribute('aria-valuenow', Math.round(pct));
    deckTime.textContent = fmt(audio.currentTime) + ' / ' + (fmt(audio.duration) || '–');
  });
  deckPlay.addEventListener('click', function () { if (current) toggle(current); });
  deckBar.addEventListener('click', function (e) {
    if (!audio.duration) return;
    var r = this.getBoundingClientRect();
    audio.currentTime = Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)) * audio.duration;
  });
  deckBar.addEventListener('keydown', function (e) {
    if (!audio.duration) return;
    if (e.key === 'ArrowRight') audio.currentTime = Math.min(audio.duration, audio.currentTime + 10);
    if (e.key === 'ArrowLeft') audio.currentTime = Math.max(0, audio.currentTime - 10);
  });
  if ('mediaSession' in navigator) {
    try {
      navigator.mediaSession.setActionHandler('play', play);
      navigator.mediaSession.setActionHandler('pause', function () { audio.pause(); });
    } catch (e) { /* action non prise en charge */ }
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
    if (ep.level_label) {
      var lvl = el('span', 'sleeve-level', ep.level_label);
      lvl.title = 'Niveau de profondeur du sujet';
      card.appendChild(lvl);
    }

    // Durée affichée sans télécharger le fichier : métadonnées seules.
    var probe = new Audio();
    probe.preload = 'metadata';
    probe.addEventListener('loadedmetadata', function () { dur.textContent = fmt(probe.duration); });
    probe.src = STATIC + '/audios/' + ep.audio_url;
    return card;
  }

  // ---- Navigation douce -------------------------------------------------------
  var leaveHooks = [];
  function onLeave(fn) { leaveHooks.push(fn); }

  var siteScript = document.querySelector('script[src*="/assets/site.js"]');
  var siteScriptSrc = siteScript ? siteScript.getAttribute('src') : '';
  function pageKey() { return location.pathname + location.search; }
  var renderedKey = pageKey();
  var navId = 0;

  // Les scripts de page réécrivent l'URL (filtre de la discothèque, #creer) :
  // on suit ces changements pour ne pas les prendre pour une navigation.
  var nativeReplace = history.replaceState.bind(history);
  history.replaceState = function (state, title, url) {
    nativeReplace(state, title, url);
    renderedKey = pageKey();
  };

  function swapHead(doc) {
    document.title = doc.title;
    var sel = 'style, script[type="application/ld+json"], meta[name="description"], meta[name="robots"], link[rel="canonical"], meta[property^="og:"], meta[property^="article:"], meta[name^="twitter:"]';
    document.head.querySelectorAll(sel).forEach(function (n) { n.remove(); });
    doc.head.querySelectorAll(sel).forEach(function (n) { document.head.appendChild(document.importNode(n, true)); });
  }

  function swapBody(doc) {
    Array.prototype.slice.call(document.body.childNodes).forEach(function (n) { if (n !== deck) n.remove(); });
    var scripts = [];
    Array.prototype.slice.call(doc.body.childNodes).forEach(function (n) {
      if (n.nodeName === 'SCRIPT') { if (!n.src) scripts.push(n.textContent); return; }
      if (n.id === 'deck') return;
      document.body.insertBefore(document.importNode(n, true), deck);
    });
    document.body.className = doc.body.className;
    initPage();
    scripts.forEach(function (code) {
      var sc = document.createElement('script');
      sc.textContent = code;
      document.body.insertBefore(sc, deck);
    });
    syncButtons();
  }

  function scrollAfter(url, y) {
    var target = url.hash && document.getElementById(decodeURIComponent(url.hash.slice(1)));
    if (target) target.scrollIntoView();
    else window.scrollTo(0, y || 0);
  }

  function navigate(href, push, y) {
    var id = ++navId;
    var fallback = function () { location.assign(href); };
    fetch(href, { credentials: 'same-origin', headers: { Accept: 'text/html' } })
      .then(function (res) {
        if (!/text\/html/.test(res.headers.get('Content-Type') || '')) throw new Error('pas une page');
        return res.text().then(function (html) { return { html: html, url: res.url || href }; });
      })
      .then(function (r) {
        if (id !== navId) return;
        var doc = new DOMParser().parseFromString(r.html, 'text/html');
        var sc = doc.querySelector('script[src*="/assets/site.js"]');
        // Page hors vitrine, ou servie par une autre version du site : rechargement classique.
        if (!doc.body.classList.contains('vk-page') || !sc || sc.getAttribute('src') !== siteScriptSrc) return fallback();
        var url = new URL(href, location.href);
        var finalUrl = new URL(r.url, location.href);
        finalUrl.hash = url.hash;
        if (push) {
          nativeReplace(Object.assign({}, history.state, { scroll: window.scrollY }), '');
          history.pushState({ vk: 1 }, '', finalUrl.href);
        }
        renderedKey = pageKey();
        leaveHooks.splice(0).forEach(function (fn) { try { fn(); } catch (e) { /* page quittée */ } });
        swapHead(doc);
        swapBody(doc);
        scrollAfter(finalUrl, y);
      })
      .catch(function () { if (id === navId) fallback(); });
  }

  document.addEventListener('click', function (e) {
    // Sans épisode en cours, la navigation classique suffit.
    if (!current || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var a = e.target.closest('a[href]');
    if (!a || (a.target && a.target !== '_self') || a.hasAttribute('download')) return;
    var url = new URL(a.href, location.href);
    if (url.origin !== location.origin || !/^https?:$/.test(url.protocol)) return;
    if (/^\/(api|static|assets)\//.test(url.pathname) || /\.(?!html?$)[a-z0-9]+$/i.test(url.pathname)) return;
    if (url.pathname === location.pathname && url.search === location.search && url.hash) return; // ancre interne
    e.preventDefault();
    navigate(url.href, true);
  });

  window.addEventListener('popstate', function (e) {
    if (pageKey() === renderedKey) return; // simple changement d'ancre
    navigate(location.href, false, e.state && e.state.scroll);
  });

  initPage();

  window.Vokso = {
    STATIC: STATIC,
    cleanTitle: cleanTitle,
    episodeUrl: episodeUrl,
    fmt: fmt,
    el: el,
    image: image,
    formatLabel: formatLabel,
    playButton: playButton,
    audio: audio,
    current: function () { return current; },
    toggle: toggle,
    syncButtons: syncButtons,
    onLeave: onLeave,
    isPlaying: function (ep) { return !audio.paused && current && current.id === ep.id; },
    sleeve: sleeve,
    observeReveals: observeReveals
  };
})();
