<?php
$e = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
// Un paragraphe par bloc de texte : la justification ne s'applique bien qu'à de vrais paragraphes.
$paragraphs = array_values(array_filter(array_map('trim', preg_split('/\R+/u', $text) ?: []), fn ($p) => $p !== ''));
$shareUrl = rawurlencode($canonical);
$shareText = rawurlencode($title.' — un podcast Vokso');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title) ?> — Vokso</title>
<meta name="description" content="<?= $e($description) ?>">
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?= $e($canonical) ?>">
<meta name="theme-color" content="#f3eee4" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#14120e" media="(prefers-color-scheme: dark)">
<link rel="icon" href="/assets/favicon.ico?v=3" sizes="any">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg?v=3">
<link rel="apple-touch-icon" href="/assets/vokso-icon-180.png?v=3">
<link rel="preload" href="/assets/fonts/archivo-var-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/assets/design-system.css?v=20261004">
<link rel="stylesheet" href="/assets/site.css?v=20261005b">
<meta property="og:type" content="article">
<meta property="og:site_name" content="Vokso">
<meta property="og:title" content="<?= $e($title) ?>">
<meta property="og:description" content="<?= $e($description) ?>">
<meta property="og:url" content="<?= $e($canonical) ?>">
<?php if ($imageUrl): ?><meta property="og:image" content="<?= $e($imageUrl) ?>">
<?php endif; ?>
<meta property="og:locale" content="fr_FR">
<?php if ($audioUrl): ?><meta property="og:audio" content="<?= $e($audioUrl) ?>">
<meta property="og:audio:type" content="audio/mpeg">
<?php endif; ?>
<?php if ($publishedIso !== ''): ?><meta property="article:published_time" content="<?= $e($publishedIso) ?>">
<?php endif; ?>
<?php if ($category !== ''): ?><meta property="article:section" content="<?= $e($category) ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $e($title) ?>">
<meta name="twitter:description" content="<?= $e($description) ?>">
<?php if ($imageUrl): ?><meta name="twitter:image" content="<?= $e($imageUrl) ?>">
<?php endif; ?>
<script type="application/ld+json"><?= $jsonLd ?></script>
<style>
  .episode { width: min(760px, 100% - 2.5rem); margin: 0 auto; padding: 3.5rem 0 2rem; }
  .episode-head { text-align: center; }
  .episode-format { display: inline-block; margin: 0.9rem 0 0; padding: 0.2rem 0.7rem; border-radius: 999px; background: var(--vk-signal); color: var(--vk-on-signal); font-size: 0.85rem; font-weight: 600; }
  .breadcrumb ol { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.35rem; margin: 0 0 1rem; padding: 0; list-style: none; font-size: 0.85rem; color: var(--vk-ink-2); }
  .breadcrumb li + li::before { content: "›"; margin-right: 0.35rem; }
  .breadcrumb a { color: inherit; }
  .breadcrumb [aria-current] { max-width: 28ch; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .related { margin: 3rem 0 0; }
  .related ul { margin: 1rem 0 0; padding: 0; list-style: none; display: grid; gap: 0.6rem; }
  .related li a { font-weight: 600; }
  .related-cat { font-size: 0.8rem; color: var(--vk-ink-2); }
  .episode-cat { display: inline-block; text-decoration: none; padding: 0.3rem 0.7rem; border-radius: var(--vk-radius-sm); background: var(--vk-signal); color: var(--vk-on-signal); }
  .episode-cat:hover { background: var(--vk-signal-hover); }
  .episode h1 { margin: 1.1rem auto 0; max-width: 22ch; font-size: clamp(2.1rem, 5.5vw, 3.6rem); line-height: 0.98; text-wrap: balance; }
  .episode-meta { margin: 1rem 0 0; color: var(--vk-ink-2); font-size: 0.95rem; }
  .episode-cover { width: min(440px, 100%); margin: 2.2rem auto 0; aspect-ratio: 1; border-radius: var(--vk-radius); overflow: hidden; box-shadow: 0 30px 60px -30px rgba(23, 20, 15, 0.6); background: var(--vk-paper-2); }
  .episode-cover img { width: 100%; height: 100%; object-fit: cover; }

  /* Lecteur audio : la balise <audio controls> reste la base sans JavaScript. */
  .player { margin: 2rem auto 0; padding: 1.1rem 1.3rem 1.2rem; border-radius: var(--vk-radius); background: var(--vk-console); color: var(--vk-on-console); box-shadow: var(--vk-shadow); }
  .player audio { width: 100%; }
  .player-ui { display: none; }
  .js .player-ui { display: block; }
  .js .player audio { display: none; }
  .player-row { display: flex; align-items: center; justify-content: center; gap: 1.1rem; }
  .player .vk-play { width: 3.8rem; height: 3.8rem; }
  .player-skip, .player-rate {
    display: inline-grid; place-items: center; min-width: 2.75rem; height: 2.75rem; padding: 0 0.5rem; border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--vk-on-console) 25%, transparent); background: none; color: inherit; cursor: pointer;
    font: 600 0.78rem/1 var(--vk-mono);
  }
  .player-skip:hover, .player-rate:hover { border-color: var(--vk-signal); color: var(--vk-signal); }
  .player-skip svg { width: 1.25rem; height: 1.25rem; }
  .player-skip { position: relative; }
  .player-skip span { position: absolute; font-size: 0.55rem; top: 52%; left: 50%; transform: translate(-50%, -50%); }
  .player-rate { margin-left: auto; }
  .player-row .player-spacer { margin-right: auto; min-width: 2.75rem; }
  .player-seek { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 0.8rem; align-items: center; margin-top: 1rem; font: 500 0.78rem/1 var(--vk-mono); color: color-mix(in srgb, var(--vk-on-console) 70%, transparent); }
  .player-seek input[type=range] {
    -webkit-appearance: none; appearance: none; width: 100%; height: 6px; margin: 0; border-radius: 3px; cursor: pointer;
    background: linear-gradient(90deg, var(--vk-signal) var(--pct, 0%), color-mix(in srgb, var(--vk-on-console) 20%, transparent) var(--pct, 0%));
  }
  .player-seek input[type=range]::-webkit-slider-thumb { -webkit-appearance: none; width: 16px; height: 16px; border-radius: 50%; background: var(--vk-signal); border: 3px solid var(--vk-console); }
  .player-seek input[type=range]::-moz-range-thumb { width: 12px; height: 12px; border-radius: 50%; background: var(--vk-signal); border: 3px solid var(--vk-console); }
  .player-seek input[type=range]:focus-visible { outline: 2px solid var(--vk-signal); outline-offset: 4px; }

  /* Partage */
  .share { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 0.5rem; margin: 1.5rem 0 0; }
  .share-label { width: 100%; text-align: center; margin-bottom: 0.2rem; }
  .share a, .share button {
    display: inline-flex; align-items: center; gap: 0.45rem; height: 2.6rem; padding: 0 0.95rem; border-radius: 999px;
    border: 1.5px solid var(--vk-line); background: var(--vk-card); color: var(--vk-ink); text-decoration: none; cursor: pointer;
    font: 600 0.88rem/1 var(--vk-font);
  }
  .share a:hover, .share button:hover { border-color: var(--vk-ink); }
  .share svg { width: 1.05rem; height: 1.05rem; flex: none; }
  .share .share-copy { background: var(--vk-ink); border-color: var(--vk-ink); color: var(--vk-paper); }
  .share .share-copy.is-done { background: var(--vk-success); border-color: var(--vk-success); color: #fff; }
  .share .share-icon { width: 2.6rem; padding: 0; justify-content: center; }

  /* Transcription : justifiée, avec césure douce pour éviter les grands blancs. */
  .transcript { margin-top: 3.2rem; padding-top: 2.2rem; border-top: 2px solid var(--vk-ink); }
  .transcript h2 { font-size: 1.5rem; text-align: center; margin-bottom: 1.6rem; }
  .transcript p {
    margin: 0 0 1.15em; font-size: 1.1rem; line-height: 1.75; color: var(--vk-ink);
    text-align: justify; text-justify: inter-word;
    -webkit-hyphens: auto; hyphens: auto;
    -webkit-hyphenate-limit-before: 4; -webkit-hyphenate-limit-after: 3;
    hyphenate-limit-chars: 8 4 3; hyphenate-limit-lines: 2;
    text-wrap: pretty;
  }

  .episode-cta { margin: 3rem 0 0; padding: 2.2rem; text-align: center; border: 1.5px solid var(--vk-ink); border-radius: var(--vk-radius); background: var(--vk-card); box-shadow: 6px 6px 0 var(--vk-signal); }
  .episode-cta h2 { font-size: clamp(1.6rem, 4vw, 2.2rem); }
  .episode-cta p { margin: 0.6rem auto 1.4rem; color: var(--vk-ink-2); max-width: 46ch; }
  .episode-cta .vk-btn { font-size: 1.02rem; padding: 0.95rem 1.5rem; }

  @media (max-width: 560px) {
    .episode { padding-top: 2.2rem; }
    .player-row { gap: 0.6rem; }
    .transcript p { font-size: 1.04rem; }
    .share a:not(.share-icon) span, .share button:not(.share-copy) span { display: none; }
  }
</style>
<script>document.documentElement.classList.add('js');</script>
</head>
<body class="vk-page">

<?php include __DIR__.'/../partials/site-header.php'; ?>

<main>
  <article class="episode">
    <header class="episode-head">
      <nav class="breadcrumb" aria-label="Fil d'Ariane">
        <ol>
          <?php foreach ($breadcrumb as $i => [$label, $url]): ?>
          <li><?php if ($i < count($breadcrumb) - 1): ?><a href="<?= $e(parse_url($url, PHP_URL_PATH) ?: '/') ?>"><?= $e($label) ?></a><?php else: ?><span aria-current="page"><?= $e($label) ?></span><?php endif; ?></li>
          <?php endforeach; ?>
        </ol>
      </nav>
      <?php if ($categoryUrl): ?><a class="vk-label episode-cat" href="<?= $e(parse_url($categoryUrl, PHP_URL_PATH)) ?>"><?= $e($category) ?></a><?php endif; ?>
      <h1 class="display"><?= $e($title) ?></h1>
      <?php if ($format): ?><p class="episode-format"><?= $e($format) ?></p><?php endif; ?>
      <p class="episode-meta"><?php if ($publishedHuman !== ''): ?>Publié le <time datetime="<?= $e($publishedIso) ?>"><?= $e($publishedHuman) ?></time> · <?php endif; ?>Podcast généré par IA, hébergé en Europe</p>
      <?php if ($imageUrl): ?>
        <div class="episode-cover"><img src="<?= $e($imageUrl) ?>" alt="Illustration de l'épisode <?= $e($title) ?>" width="440" height="440"></div>
      <?php endif; ?>
    </header>

    <?php if ($audioUrl): ?>
    <div class="player" id="player" data-ep="<?= $e($episodeId) ?>" data-title="<?= $e($title) ?>" data-url="<?= $e(parse_url($canonical, PHP_URL_PATH) ?: '/') ?>" data-image="<?= $e($imageUrl ?? '') ?>">
      <audio id="player-audio" controls preload="metadata" src="<?= $e($audioUrl) ?>"></audio>
      <div class="player-ui" aria-label="Lecteur de l'épisode">
        <div class="player-row">
          <span class="player-spacer" aria-hidden="true"></span>
          <button type="button" class="player-skip" id="player-back" aria-label="Reculer de 15 secondes">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg><span>15</span>
          </button>
          <button type="button" class="vk-play" id="player-play" aria-pressed="false" aria-label="Lecture">
            <svg class="i-play" viewBox="0 0 16 16" aria-hidden="true"><path d="M4 2.5v11a.5.5 0 0 0 .77.42l8.5-5.5a.5.5 0 0 0 0-.84l-8.5-5.5A.5.5 0 0 0 4 2.5z" fill="currentColor"/></svg>
            <svg class="i-pause" viewBox="0 0 16 16" aria-hidden="true"><rect x="3" y="2" width="3.6" height="12" rx="1" fill="currentColor"/><rect x="9.4" y="2" width="3.6" height="12" rx="1" fill="currentColor"/></svg>
          </button>
          <button type="button" class="player-skip" id="player-fwd" aria-label="Avancer de 15 secondes">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/></svg><span>15</span>
          </button>
          <button type="button" class="player-rate" id="player-rate" aria-label="Vitesse de lecture">1×</button>
        </div>
        <div class="player-seek">
          <span id="player-cur">0:00</span>
          <input type="range" id="player-seek" min="0" max="1000" value="0" step="1" aria-label="Position de lecture">
          <span id="player-dur">–:––</span>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="share" aria-label="Partager l'épisode">
      <span class="vk-label share-label">Partager l'épisode</span>
      <button type="button" class="share-copy" id="share-copy" data-url="<?= $e($canonical) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>
        <span id="share-copy-label">Copier le lien</span>
      </button>
      <button type="button" id="share-native" hidden>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v13"/><path d="m7 8 5-5 5 5"/><path d="M5 14v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5"/></svg>
        <span>Partager…</span>
      </button>
      <a class="share-icon" href="https://wa.me/?text=<?= $shareText ?>%20<?= $shareUrl ?>" target="_blank" rel="noopener" aria-label="Partager sur WhatsApp">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.8-1.4.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.1 5.1 0 0 0 1.1 2.7 11.7 11.7 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.1-1.2l-.5-.3Z"/></svg>
      </a>
      <a class="share-icon" href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl ?>" target="_blank" rel="noopener" aria-label="Partager sur Facebook">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-7.5H16l.4-3h-2.9V8.6c0-.9.3-1.5 1.5-1.5h1.6V4.4a21 21 0 0 0-2.3-.1c-2.3 0-3.8 1.4-3.8 3.9v2.3H8v3h2.5V21h3Z"/></svg>
      </a>
      <a class="share-icon" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $shareUrl ?>" target="_blank" rel="noopener" aria-label="Partager sur LinkedIn">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6.9 8.8H3.6V20h3.3V8.8ZM5.2 3.5a1.9 1.9 0 1 0 0 3.8 1.9 1.9 0 0 0 0-3.8ZM20.4 13.6c0-3-1.6-5-4.3-5a3.7 3.7 0 0 0-3.3 1.8V8.8H9.6V20h3.3v-5.6c0-1.5.3-2.9 2.1-2.9 1.8 0 1.9 1.7 1.9 3V20h3.4v-6.4Z"/></svg>
      </a>
      <a class="share-icon" href="https://x.com/intent/post?url=<?= $shareUrl ?>&amp;text=<?= $shareText ?>" target="_blank" rel="noopener" aria-label="Partager sur X">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.8 3h3.1l-6.8 7.7L22 21h-6.2l-4.9-6.4L5.3 21H2.2l7.2-8.3L1.8 3h6.4l4.4 5.8L17.8 3Zm-1.1 16.2h1.7L7.4 4.7H5.6l11.1 14.5Z"/></svg>
      </a>
      <a class="share-icon" href="mailto:?subject=<?= $shareText ?>&amp;body=<?= $shareUrl ?>" aria-label="Partager par e-mail">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
      </a>
    </div>

    <section class="transcript" aria-labelledby="transcript-title">
      <h2 class="display" id="transcript-title">Transcription</h2>
      <?php foreach ($paragraphs as $paragraph): ?>
      <p><?= $e($paragraph) ?></p>
      <?php endforeach; ?>
    </section>

    <?php if ($related): ?>
    <section class="related" aria-labelledby="related-title">
      <h2 class="display" id="related-title">À écouter aussi</h2>
      <ul>
        <?php foreach ($related as $item): ?>
        <li><a href="<?= $e($item['url']) ?>"><?= $e($item['title']) ?></a><?php if ($item['category'] !== ''): ?> <span class="related-cat"><?= $e($item['category']) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($categoryUrl): ?><p><a href="<?= $e(parse_url($categoryUrl, PHP_URL_PATH)) ?>">Tous les podcasts « <?= $e($category) ?> » →</a></p><?php endif; ?>
    </section>
    <?php endif; ?>

    <aside class="episode-cta">
      <h2 class="display">À vous l'antenne</h2>
      <p>Donnez un sujet en une phrase : Vokso écrit, illustre et enregistre votre épisode en une à deux minutes.</p>
      <a class="vk-btn vk-btn-signal" href="/#creer">Générer votre propre podcast gratuitement</a>
    </aside>
  </article>
</main>

<?php include __DIR__.'/../partials/site-footer.php'; ?>

<script src="/assets/site.js?v=20261005b"></script>
<script>
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };

  // ---- Lecteur ---------------------------------------------------------------
  // Les commandes pilotent le lecteur commun du site (window.Vokso), pour que
  // l'écoute continue quand on quitte la page. L'élément <audio> de la page ne
  // sert plus qu'à lire la durée (et de lecteur de secours sans JavaScript).
  var probe = $('player-audio');
  if (probe) {
    var V = window.Vokso;
    var audio = V.audio;
    var fmt = V.fmt;
    var player = $('player');
    var ep = {
      id: player.getAttribute('data-ep'),
      title: player.getAttribute('data-title'),
      url: player.getAttribute('data-url'),
      src: probe.getAttribute('src'),
      image_src: player.getAttribute('data-image')
    };
    var seek = $('player-seek');
    var rates = [1, 1.25, 1.5, 2, 0.75];
    var rateIndex = Math.max(0, rates.indexOf(audio.defaultPlaybackRate));
    var dragging = false;
    var pendingSeek = null;
    probe.removeAttribute('controls');

    var mine = function () { var c = V.current(); return !!c && c.id === ep.id; };
    var duration = function () { return (mine() && audio.duration) || probe.duration; };
    var showRate = function () { $('player-rate').textContent = String(rates[rateIndex]).replace('.', ',') + '×'; };
    var sync = function () {
      var playing = mine() && !audio.paused;
      $('player-play').setAttribute('aria-pressed', playing ? 'true' : 'false');
      $('player-play').setAttribute('aria-label', playing ? 'Pause' : 'Lecture');
      player.classList.toggle('is-playing', playing);
    };
    var progress = function () {
      var d = duration();
      if (!d) return;
      var t = mine() ? audio.currentTime : 0;
      var pct = (t / d) * 100;
      if (!dragging) seek.value = Math.round(pct * 10);
      seek.style.setProperty('--pct', pct + '%');
      $('player-cur').textContent = fmt(t);
    };
    var onMeta = function () {
      if (!mine()) return;
      $('player-dur').textContent = fmt(audio.duration);
      if (pendingSeek !== null) { audio.currentTime = audio.duration * pendingSeek; pendingSeek = null; }
    };
    var update = function () { sync(); progress(); };
    var listeners = { play: update, pause: update, ended: update, emptied: update, timeupdate: progress, loadedmetadata: onMeta };
    Object.keys(listeners).forEach(function (k) { audio.addEventListener(k, listeners[k]); });
    V.onLeave(function () { Object.keys(listeners).forEach(function (k) { audio.removeEventListener(k, listeners[k]); }); });

    // Lance l'épisode de la page s'il n'est pas déjà dans le lecteur.
    var start = function () {
      if (mine()) return false;
      audio.defaultPlaybackRate = rates[rateIndex];
      V.toggle(ep);
      audio.playbackRate = rates[rateIndex];
      return true;
    };
    probe.addEventListener('loadedmetadata', function () { if (!mine()) $('player-dur').textContent = fmt(probe.duration); });
    $('player-play').addEventListener('click', function () { if (!start()) V.toggle(ep); });
    $('player-back').addEventListener('click', function () { if (mine()) audio.currentTime = Math.max(0, audio.currentTime - 15); });
    $('player-fwd').addEventListener('click', function () { if (mine()) audio.currentTime = Math.min(audio.duration || 0, audio.currentTime + 15); });
    $('player-rate').addEventListener('click', function () {
      rateIndex = (rateIndex + 1) % rates.length;
      showRate();
      if (mine()) { audio.defaultPlaybackRate = rates[rateIndex]; audio.playbackRate = rates[rateIndex]; }
    });
    seek.addEventListener('input', function () {
      dragging = true;
      seek.style.setProperty('--pct', seek.value / 10 + '%');
      var d = duration();
      if (d) $('player-cur').textContent = fmt(d * seek.value / 1000);
    });
    seek.addEventListener('change', function () {
      dragging = false;
      if (start()) { pendingSeek = seek.value / 1000; return; }
      if (audio.duration) audio.currentTime = audio.duration * seek.value / 1000;
    });
    if (mine()) {
      rateIndex = Math.max(0, rates.indexOf(audio.playbackRate));
      if (audio.duration) $('player-dur').textContent = fmt(audio.duration);
    } else if (probe.readyState >= 1) {
      $('player-dur').textContent = fmt(probe.duration);
    }
    showRate();
    update();
  }

  // ---- Partage ---------------------------------------------------------------
  var copy = $('share-copy');
  var url = copy.getAttribute('data-url');
  copy.addEventListener('click', function () {
    var done = function () {
      copy.classList.add('is-done');
      $('share-copy-label').textContent = 'Lien copié !';
      setTimeout(function () { copy.classList.remove('is-done'); $('share-copy-label').textContent = 'Copier le lien'; }, 2200);
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(url).then(done, fallback);
    } else {
      fallback();
    }
    function fallback() {
      var field = document.createElement('textarea');
      field.value = url;
      field.setAttribute('readonly', '');
      field.style.position = 'fixed';
      field.style.opacity = '0';
      document.body.appendChild(field);
      field.select();
      try { document.execCommand('copy'); done(); } catch (e) { /* rien à faire */ }
      document.body.removeChild(field);
    }
  });

  if (navigator.share) {
    var native = $('share-native');
    native.hidden = false;
    native.addEventListener('click', function () {
      navigator.share({ title: document.title, url: url }).catch(function () { /* partage annulé */ });
    });
  }
})();
</script>
</body>
</html>
