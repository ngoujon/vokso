<?php $e = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title) ?> — Vokso</title>
<meta name="description" content="<?= $e($description) ?>">
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?= $e($canonical) ?>">
<link rel="icon" href="/assets/favicon.ico?v=2" sizes="any">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg?v=2">
<meta property="og:type" content="article">
<meta property="og:site_name" content="Vokso">
<meta property="og:title" content="<?= $e($title) ?>">
<meta property="og:description" content="<?= $e($description) ?>">
<meta property="og:url" content="<?= $e($canonical) ?>">
<?php if ($imageUrl): ?><meta property="og:image" content="<?= $e($imageUrl) ?>">
<?php endif; ?>
<meta property="og:locale" content="fr_FR">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $e($title) ?>">
<meta name="twitter:description" content="<?= $e($description) ?>">
<?php if ($imageUrl): ?><meta name="twitter:image" content="<?= $e($imageUrl) ?>">
<?php endif; ?>
<script type="application/ld+json"><?= $jsonLd ?></script>
<style>
  :root { color-scheme: dark; }
  body { margin: 0; background: #0e1016; color: #f4f5fa; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; }
  header, main, footer { max-width: 720px; margin: 0 auto; padding: 24px 20px; }
  header { display: flex; align-items: center; justify-content: space-between; }
  header a.brand { color: #f4f5fa; text-decoration: none; font-weight: 700; font-size: 1.1rem; }
  .badge { display: inline-block; background: #1c1f2b; color: #b9bfd6; border-radius: 999px; padding: 4px 12px; font-size: 0.8rem; margin-right: 8px; }
  h1 { font-size: 1.9rem; margin: 16px 0 8px; }
  .meta { color: #9aa0b8; font-size: 0.9rem; margin-bottom: 20px; }
  img.cover { width: 100%; max-width: 480px; display: block; border-radius: 12px; margin: 16px 0; }
  audio { width: 100%; margin: 16px 0; }
  article p { color: #d7d9e6; white-space: pre-line; }
  a.cta { display: inline-block; margin-top: 24px; background: #6c63ff; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; }
  footer { color: #7d829a; font-size: 0.85rem; }
  footer a { color: #9aa0b8; }
</style>
</head>
<body>
<header>
  <a class="brand" href="/">Vokso</a>
  <a class="brand" href="/app/">Essayer l'app ↗</a>
</header>
<main>
  <article>
    <?php if ($category !== ''): ?><span class="badge"><?= $e($category) ?></span><?php endif; ?>
    <h1><?= $e($title) ?></h1>
    <p class="meta">Publié le <?= $e($publishedHuman) ?> · Podcast généré par IA, hébergé en Europe</p>
    <?php if ($imageUrl): ?>
      <img class="cover" src="<?= $e($imageUrl) ?>" alt="Illustration de l'épisode <?= $e($title) ?>" loading="lazy">
    <?php endif; ?>
    <?php if ($audioUrl): ?>
      <audio controls preload="metadata" src="<?= $e($audioUrl) ?>"></audio>
    <?php endif; ?>
    <p><?= nl2br($e($text)) ?></p>
    <a class="cta" href="/app/">Générer votre propre podcast gratuitement</a>
  </article>
</main>
<footer>
  <p>Vokso — générateur de podcasts par IA hébergé en Europe. <a href="/">Accueil</a> · <a href="/app/contact">Contact</a></p>
</footer>
</body>
</html>
