<?php

namespace App\Controllers;

use App\Utils\Logger;
use Dotenv\Dotenv;
use PDO;

/**
 * Page publique, indexable et rendue côté serveur pour un épisode donné
 * (accessible en clair via la réécriture /podcast/{id}-{slug} -> voir
 * apache-config/vokso.conf). Contrairement à l'app React (SPA pure, voir
 * AUDIT_CONTENU_HTML.md), le HTML ici contient déjà tout le contenu au
 * premier octet : titre, transcript, JSON-LD PodcastEpisode — sans exécuter
 * de JavaScript. C'est ce qui rend chaque épisode réellement crawlable par
 * les moteurs de recherche et citable par les IA génératives (GEO).
 */
class PodcastPageController
{
    private PDO $pdo;

    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        try {
            $this->pdo = new PDO(
                'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
                $_ENV['DB_USER'],
                $_ENV['DB_PASS']
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            Logger::get()->error($e->getMessage(), ['controller' => 'podcast_page', 'exception' => get_class($e)]);
            $this->renderNotFound();
            exit;
        }
    }

    /**
     * @param string $request Chemin brut après le domaine, ex.
     *                        "podcast/gen_6ab267b302f6d-jupiter-la-geante"
     *                        (generation_id est un VARCHAR, pas un entier —
     *                        voir SQL/20260818010000.sql).
     */
    public function show(string $request): void
    {
        if (!preg_match('#^podcast/([A-Za-z0-9_]+)#', $request, $matches)) {
            $this->renderNotFound();
            return;
        }
        $id = $matches[1];

        $stmt = $this->pdo->prepare(
            'SELECT g.generation_id as id, g.title, g.text_content, g.image_url, g.audio_url,
                    g.created_at, c.label as category
             FROM generations g
             LEFT JOIN categorie c ON c.idcategorie = g.idcategorie
             WHERE g.generation_id = :id AND g.statut = "on"
             LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $episode = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$episode) {
            $this->renderNotFound();
            return;
        }

        $this->renderEpisode($episode);
    }

    private function renderEpisode(array $episode): void
    {
        $title = $this->cleanTitle($episode['title'] ?? '') ?: 'Épisode Vokso';
        $slug = $this->slugify($title);
        $canonical = 'https://vokso.fr/podcast/' . $episode['id'] . ($slug !== '' ? '-' . $slug : '');
        $description = $this->excerpt($episode['text_content'] ?? '', 200);
        $imageUrl = 'https://vokso.fr/static/images/' . rawurlencode($episode['image_url'] ?? '');
        $audioUrl = 'https://vokso.fr/static/audios/' . rawurlencode($episode['audio_url'] ?? '');
        $category = trim((string) ($episode['category'] ?? ''));
        $publishedAt = $episode['created_at'] ?? null;
        $publishedIso = $publishedAt ? date('c', strtotime($publishedAt)) : null;
        $publishedHuman = $publishedAt ? date('d/m/Y', strtotime($publishedAt)) : '';

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'PodcastEpisode',
            'name' => $title,
            'url' => $canonical,
            'description' => $description,
            'datePublished' => $publishedIso,
            'image' => $imageUrl,
            'associatedMedia' => [
                '@type' => 'MediaObject',
                'contentUrl' => $audioUrl,
            ],
            'partOfSeries' => [
                '@type' => 'PodcastSeries',
                'name' => 'Vokso',
                'url' => 'https://vokso.fr/',
            ],
        ];
        if ($category !== '') {
            $jsonLd['genre'] = $category;
        }

        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $this->e($title) ?> — Vokso</title>
<meta name="description" content="<?= $this->e($description) ?>">
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?= $this->e($canonical) ?>">
<link rel="icon" href="/assets/favicon.ico" sizes="any">
<meta property="og:type" content="article">
<meta property="og:site_name" content="Vokso">
<meta property="og:title" content="<?= $this->e($title) ?>">
<meta property="og:description" content="<?= $this->e($description) ?>">
<meta property="og:url" content="<?= $this->e($canonical) ?>">
<meta property="og:image" content="<?= $this->e($imageUrl) ?>">
<meta property="og:locale" content="fr_FR">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $this->e($title) ?>">
<meta name="twitter:description" content="<?= $this->e($description) ?>">
<meta name="twitter:image" content="<?= $this->e($imageUrl) ?>">
<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
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
    <?php if ($category !== ''): ?><span class="badge"><?= $this->e($category) ?></span><?php endif; ?>
    <h1><?= $this->e($title) ?></h1>
    <p class="meta">Publié le <?= $this->e($publishedHuman) ?> · Podcast généré par IA, hébergé en Europe</p>
    <?php if ($episode['image_url']): ?>
      <img class="cover" src="<?= $this->e($imageUrl) ?>" alt="Illustration de l'épisode <?= $this->e($title) ?>" loading="lazy">
    <?php endif; ?>
    <?php if ($episode['audio_url']): ?>
      <audio controls preload="metadata" src="<?= $this->e($audioUrl) ?>"></audio>
    <?php endif; ?>
    <p><?= nl2br($this->e($episode['text_content'] ?? '')) ?></p>
    <a class="cta" href="/app/">Générer votre propre podcast gratuitement</a>
  </article>
</main>
<footer>
  <p>Vokso — générateur de podcasts par IA hébergé en Europe. <a href="/">Accueil</a> · <a href="/app/tarifs">Tarifs</a> · <a href="/app/contact">Contact</a></p>
</footer>
</body>
</html>
        <?php
    }

    private function renderNotFound(): void
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex');
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8">'
            . '<meta name="robots" content="noindex, follow">'
            . '<title>Épisode introuvable — Vokso</title></head>'
            . '<body><h1>Épisode introuvable</h1>'
            . '<p>Cet épisode n\'existe pas ou n\'est plus disponible. '
            . '<a href="/app/">Découvrir les derniers épisodes</a>.</p></body></html>';
    }

    private function cleanTitle(string $rawTitle): string
    {
        $title = trim($rawTitle, " \t\n\r\0\x0B\"'“”«»");
        $title = preg_replace('/^#{1,6}\s+/', '', $title);
        $title = preg_replace('/^[-*+]\s+/', '', $title);
        $title = preg_replace('/(\*\*|__)(.*?)\1/', '$2', $title);
        $title = preg_replace('/(\*|_|`)(.*?)\1/', '$2', $title);
        $title = str_replace(['*', '#', '`'], '', $title);
        $title = preg_replace('/\s+/', ' ', $title);
        return trim($title);
    }

    private function slugify(string $text): string
    {
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        if ($transliterated === false) {
            $transliterated = $text;
        }
        $slug = strtolower($transliterated);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return mb_substr($slug, 0, 80);
    }

    private function excerpt(string $text, int $length): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($clean) <= $length) {
            return $clean;
        }
        return mb_substr($clean, 0, $length - 1) . '…';
    }

    private function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
