<?php
/* Transcription d'un épisode, mise en page pour dompdf (PDF A4). */
$e = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$paragraphs = array_values(array_filter(array_map('trim', preg_split('/\R+/u', $text) ?: []), fn ($p) => $p !== ''));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title><?= $e($title) ?> — Vokso</title>
<style>
  @page { margin: 2.2cm 2cm 2.4cm; }
  body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5pt; line-height: 1.6; color: #17140f; }
  .brand { font-size: 9pt; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; color: #17140f; }
  .brand span { background: #f5c518; padding: 1px 6px; }
  .meta { margin-top: 18px; font-size: 8.5pt; color: #6b655b; text-transform: uppercase; letter-spacing: 1px; }
  h1 { margin: 6px 0 0; font-size: 22pt; line-height: 1.15; }
  hr { border: 0; border-top: 2px solid #17140f; margin: 18px 0 20px; }
  p { margin: 0 0 10px; text-align: justify; }
  .footer { position: fixed; bottom: -1.5cm; left: 0; right: 0; font-size: 8pt; color: #6b655b; border-top: 1px solid #d9d2c4; padding-top: 6px; }
</style>
</head>
<body>
  <div class="footer">Un podcast Vokso — <?= $e($canonical) ?></div>
  <div class="brand"><span>Vokso</span></div>
  <div class="meta"><?= $e(implode(' · ', array_filter([$category, $publishedHuman !== '' ? 'Publié le '.$publishedHuman : '']))) ?></div>
  <h1><?= $e($title) ?></h1>
  <hr>
  <?php foreach ($paragraphs as $paragraph): ?>
  <p><?= $e($paragraph) ?></p>
  <?php endforeach; ?>
</body>
</html>
