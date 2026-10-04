<?php
$e = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $e($title) ?> — Vokso</title>
<meta name="description" content="<?= $e($description) ?>">
<meta name="robots" content="<?= $indexable ? 'index, follow' : 'noindex, follow' ?>">
<link rel="canonical" href="<?= $e($canonical) ?>">
<meta name="theme-color" content="#f3eee4" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#14120e" media="(prefers-color-scheme: dark)">
<link rel="icon" href="/assets/favicon.ico?v=3" sizes="any">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg?v=3">
<link rel="apple-touch-icon" href="/assets/vokso-icon-180.png?v=3">
<link rel="preload" href="/assets/fonts/archivo-var-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/assets/design-system.css?v=20261004">
<link rel="stylesheet" href="/assets/site.css?v=20261004">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Vokso">
<meta property="og:title" content="<?= $e($title) ?>">
<meta property="og:description" content="<?= $e($description) ?>">
<meta property="og:url" content="<?= $e($canonical) ?>">
<meta property="og:image" content="https://vokso.fr/assets/og-image.png">
<meta property="og:locale" content="fr_FR">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $e($title) ?>">
<meta name="twitter:description" content="<?= $e($description) ?>">
<meta name="twitter:image" content="https://vokso.fr/assets/og-image.png">
<script type="application/ld+json"><?= $jsonLd ?></script>
<style>
  .category { width: min(960px, 100% - 2.5rem); margin: 0 auto; padding: 3.5rem 0 2rem; }
  .category-head { text-align: center; }
  .category h1 { margin: 0.6rem auto 0; max-width: 24ch; font-size: clamp(2rem, 5vw, 3.2rem); line-height: 1; text-wrap: balance; }
  .category-intro { margin: 1rem auto 0; max-width: 60ch; color: var(--vk-ink-2); }
  .breadcrumb ol { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.35rem; margin: 0 0 1rem; padding: 0; list-style: none; font-size: 0.85rem; color: var(--vk-ink-2); }
  .breadcrumb li + li::before { content: "›"; margin-right: 0.35rem; }
  .breadcrumb a { color: inherit; }
  .episodes { margin: 2.5rem 0 0; padding: 0; list-style: none; display: grid; gap: 1.2rem; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }
  .episodes li { background: var(--vk-paper-2); border-radius: var(--vk-radius); overflow: hidden; display: flex; flex-direction: column; }
  .episodes img { width: 100%; aspect-ratio: 1; object-fit: cover; background: var(--vk-paper); }
  .episodes .ep-body { padding: 1rem 1.1rem 1.2rem; }
  .episodes h2 { margin: 0; font-size: 1.15rem; line-height: 1.25; }
  .episodes h2 a { color: inherit; text-decoration: none; }
  .episodes h2 a:hover { text-decoration: underline; }
  .episodes p { margin: 0.5rem 0 0; font-size: 0.92rem; color: var(--vk-ink-2); }
  .episodes time { display: block; margin-top: 0.6rem; font-size: 0.8rem; color: var(--vk-ink-2); }
  .others { margin: 3rem 0 0; text-align: center; }
  .others ul { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.5rem; margin: 1rem 0 0; padding: 0; list-style: none; }
  .others a { display: inline-block; padding: 0.35rem 0.8rem; border-radius: var(--vk-radius-sm); background: var(--vk-paper-2); color: inherit; text-decoration: none; }
  .others a:hover { background: var(--vk-signal); color: var(--vk-on-signal); }
</style>
</head>
<body class="vk-page">

<?php include __DIR__.'/../partials/site-header.php'; ?>

<main class="category">
  <header class="category-head">
    <nav class="breadcrumb" aria-label="Fil d'Ariane">
      <ol>
        <?php foreach ($breadcrumb as $i => [$crumb, $url]): ?>
        <li><?php if ($i < count($breadcrumb) - 1): ?><a href="<?= $e(parse_url($url, PHP_URL_PATH) ?: '/') ?>"><?= $e($crumb) ?></a><?php else: ?><span aria-current="page"><?= $e($crumb) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ol>
    </nav>
    <h1 class="display">Podcasts <?= $e($label) ?></h1>
    <p class="category-intro"><?= $e(count($items)) ?> épisode<?= count($items) > 1 ? 's' : '' ?> de podcast en français sur le thème « <?= $e($label) ?> », à écouter gratuitement et sans inscription. Chaque épisode dure environ cinq minutes et propose sa transcription complète.</p>
  </header>

  <ul class="episodes">
    <?php foreach ($items as $item): ?>
    <li>
      <?php if ($item['image']): ?><a href="<?= $e($item['path']) ?>" tabindex="-1" aria-hidden="true"><img src="<?= $e($item['image']) ?>" alt="" width="300" height="300" loading="lazy" decoding="async"></a><?php endif; ?>
      <div class="ep-body">
        <h2><a href="<?= $e($item['path']) ?>"><?= $e($item['title']) ?></a></h2>
        <p><?= $e($item['excerpt']) ?></p>
        <?php if ($item['date'] !== ''): ?><time><?= $e($item['date']) ?></time><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($others): ?>
  <nav class="others" aria-labelledby="others-title">
    <h2 class="display" id="others-title">Autres thèmes</h2>
    <ul>
      <?php foreach ($others as $other): ?>
      <li><a href="<?= $e($other['path']) ?>"><?= $e($other['label']) ?> (<?= $e($other['count']) ?>)</a></li>
      <?php endforeach; ?>
    </ul>
  </nav>
  <?php endif; ?>
</main>

<?php include __DIR__.'/../partials/site-footer.php'; ?>

<script src="/assets/site.js?v=20261004s"></script>
</body>
</html>
