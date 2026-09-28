<?php

namespace App\Controllers;

use App\Utils\Logger;
use Dotenv\Dotenv;
use PDO;

/**
 * Sitemap généré dynamiquement (voir apache-config/vokso.conf, réécriture
 * /sitemap.xml -> /api/sitemap) : le fichier statique historique
 * (site/sitemap.xml) ne listait que la page d'accueil et ne pouvait pas
 * suivre les épisodes publiés en continu. Ici, chaque épisode public obtient
 * son URL /podcast/{id}-{slug} (voir PodcastPageController) avec sa vraie
 * date de publication comme <lastmod>.
 */
class SitemapController
{
    // Plafond très au-dessus du volume actuel, pour rester sous la limite du
    // protocole sitemap (50 000 URLs) sans jamais tronquer silencieusement.
    private const MAX_EPISODES = 45000;

    private PDO $pdo;

    public function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();

        $this->pdo = new PDO(
            'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
            $_ENV['DB_USER'],
            $_ENV['DB_PASS']
        );
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function show(): void
    {
        header('Content-Type: application/xml; charset=utf-8');

        $urls = [
            ['loc' => 'https://vokso.fr/', 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => 'https://vokso.fr/app/tarifs', 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => 'https://vokso.fr/app/contact', 'changefreq' => 'monthly', 'priority' => '0.3'],
            ['loc' => 'https://vokso.fr/app/politique-de-confidentialite', 'changefreq' => 'yearly', 'priority' => '0.2'],
        ];

        try {
            $stmt = $this->pdo->prepare(
                'SELECT generation_id as id, title, created_at
                 FROM generations
                 WHERE statut = "on"
                 ORDER BY created_at DESC
                 LIMIT ' . self::MAX_EPISODES
            );
            $stmt->execute();

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $slug = $this->slugify($this->cleanTitle($row['title'] ?? ''));
                $urls[] = [
                    'loc' => 'https://vokso.fr/podcast/' . $row['id'] . ($slug !== '' ? '-' . $slug : ''),
                    'lastmod' => date('c', strtotime($row['created_at'])),
                    'changefreq' => 'monthly',
                    'priority' => '0.6',
                ];
            }
        } catch (\PDOException $e) {
            // Un sitemap partiel (pages statiques seules) reste préférable à
            // une 500 : on journalise et on sert ce qu'on a déjà.
            Logger::get()->error($e->getMessage(), ['controller' => 'sitemap', 'exception' => get_class($e)]);
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $url) {
            echo "  <url>\n";
            echo '    <loc>' . htmlspecialchars($url['loc'], ENT_QUOTES, 'UTF-8') . "</loc>\n";
            if (!empty($url['lastmod'])) {
                echo '    <lastmod>' . $url['lastmod'] . "</lastmod>\n";
            }
            if (!empty($url['changefreq'])) {
                echo '    <changefreq>' . $url['changefreq'] . "</changefreq>\n";
            }
            if (!empty($url['priority'])) {
                echo '    <priority>' . $url['priority'] . "</priority>\n";
            }
            echo "  </url>\n";
        }
        echo '</urlset>' . "\n";
    }

    private function cleanTitle(string $rawTitle): string
    {
        $title = trim($rawTitle, " \t\n\r\0\x0B\"'“”«»");
        $title = preg_replace('/(\*\*|__)(.*?)\1/', '$2', $title);
        $title = str_replace(['*', '#', '`'], '', $title);
        return trim(preg_replace('/\s+/', ' ', $title));
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
}
