<?php /* En-tête commun des pages rendues côté serveur (épisode, catégorie). */ ?>
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
