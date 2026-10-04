<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, follow">
<title>Épisode introuvable — Vokso</title>
<meta name="theme-color" content="#f3eee4" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#14120e" media="(prefers-color-scheme: dark)">
<link rel="icon" href="/assets/favicon.ico?v=3" sizes="any">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg?v=3">
<link rel="apple-touch-icon" href="/assets/vokso-icon-180.png?v=3">
<link rel="preload" href="/assets/fonts/archivo-var-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/assets/design-system.css?v=20261004">
<link rel="stylesheet" href="/assets/site.css?v=20261004">
<style>
  .lost { padding: 6rem 0 5rem; text-align: center; }
  .lost h1 { font-size: clamp(2.2rem, 6vw, 3.6rem); }
  .lost p { color: var(--vk-ink-2); margin: 1rem auto 2rem; max-width: 46ch; }
  .lost .cta { display: flex; gap: 0.8rem; justify-content: center; flex-wrap: wrap; }
</style>
</head>
<body class="vk-page">

<header class="site-header">
  <div class="wrap">
    <a class="site-brand" href="/" aria-label="Vokso, accueil">
      <span class="site-brand-mark" aria-hidden="true">
        <svg viewBox="218 312 588 382" fill="currentColor">
          <rect x="258" y="432" width="52" height="160" rx="26" />
          <rect x="336" y="352" width="52" height="320" rx="26" />
          <rect x="414" y="402" width="52" height="220" rx="26" />
          <rect x="500" y="362" width="266" height="52" rx="26" opacity="0.55" />
          <rect x="500" y="442" width="200" height="52" rx="26" opacity="0.55" />
          <rect x="500" y="522" width="266" height="52" rx="26" opacity="0.55" />
          <rect x="500" y="602" width="160" height="52" rx="26" opacity="0.55" />
        </svg>
      </span>
      Vokso
    </a>
    <button class="site-nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Menu"><span></span></button>
    <nav class="site-nav" id="site-nav" aria-label="Navigation principale">
      <a class="site-nav-link" href="/#selection">Sélection du jour</a>
      <a class="site-nav-link" href="/discotheque">Discothèque</a>
      <a class="site-nav-link" href="/comment-ca-marche">Comment ça marche ?</a>
      <a class="vk-btn vk-btn-signal" href="/#creer">Créer un épisode</a>
    </nav>
  </div>
</header>

<main class="wrap lost">
  <span class="vk-label">Fréquence perdue</span>
  <h1 class="display">Épisode introuvable</h1>
  <p>Cet épisode n'existe pas ou n'est plus disponible.</p>
  <div class="cta">
    <a class="vk-btn vk-btn-signal" href="/discotheque">Découvrir les derniers épisodes</a>
    <a class="vk-btn" href="/#creer">Créer un épisode</a>
  </div>
</main>

<footer class="site-footer">
  <div class="wrap">
    <div class="site-footer-top">
      <div class="site-footer-about">
        <a class="site-brand" href="/" aria-label="Vokso, accueil">
          <span class="site-brand-mark" aria-hidden="true">
            <svg viewBox="218 312 588 382" fill="currentColor">
              <rect x="258" y="432" width="52" height="160" rx="26" />
              <rect x="336" y="352" width="52" height="320" rx="26" />
              <rect x="414" y="402" width="52" height="220" rx="26" />
              <rect x="500" y="362" width="266" height="52" rx="26" opacity="0.55" />
              <rect x="500" y="442" width="200" height="52" rx="26" opacity="0.55" />
              <rect x="500" y="522" width="266" height="52" rx="26" opacity="0.55" />
              <rect x="500" y="602" width="160" height="52" rx="26" opacity="0.55" />
            </svg>
          </span>
          Vokso
        </a>
        <p class="site-footer-pitch">Un sujet en une phrase, un podcast complet en retour : texte, pochette et voix générés par IA, sur une infrastructure européenne.</p>
        <div class="site-footer-badges"><span>Gratuit</span><span>Sans inscription</span><span>Hébergé en Europe</span></div>
      </div>
      <nav class="site-footer-col" aria-label="Écouter">
        <p class="site-footer-title">Écouter</p>
        <ul>
          <li><a href="/#selection">Sélection du jour</a></li>
          <li><a href="/discotheque">Toute la discothèque</a></li>
          <li><a href="/discotheque#categories">Par catégorie</a></li>
        </ul>
      </nav>
      <nav class="site-footer-col" aria-label="Créer">
        <p class="site-footer-title">Créer</p>
        <ul>
          <li><a href="/#creer">Générer un épisode</a></li>
          <li><a href="/comment-ca-marche">Comment ça marche ?</a></li>
          <li><a href="/comment-ca-marche#souverainete">Souveraineté et données</a></li>
        </ul>
      </nav>
      <nav class="site-footer-col" aria-label="Vokso">
        <p class="site-footer-title">Vokso</p>
        <ul>
          <li><a href="/app/contact">Contact</a></li>
          <li><a href="/app/politique-de-confidentialite">Confidentialité</a></li>
          <li><a href="/app/admin">Administration</a></li>
        </ul>
      </nav>
    </div>
    <p class="site-footer-wordmark" aria-hidden="true">Vokso</p>
    <div class="site-footer-bottom">
      <p>© <span data-year>2026</span> Vokso — podcasts générés par IA, hébergés en Europe.</p>
      <p>Texte, image et voix : Mistral AI · Émis depuis l'Union européenne</p>
    </div>
  </div>
</footer>

<script src="/assets/site.js?v=20261004s"></script>
</body>
</html>
