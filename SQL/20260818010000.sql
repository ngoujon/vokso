-- Passage de /generation en traitement asynchrone : une ligne par requête,
-- mise à jour par le worker au fur et à mesure des étapes (texte, image,
-- audio, catégorie), et interrogée par le front via /generation-status.
CREATE TABLE IF NOT EXISTS `generation_jobs` (
    `job_id` VARCHAR(64) NOT NULL PRIMARY KEY,
    `status` ENUM('pending', 'processing', 'done', 'error') NOT NULL DEFAULT 'pending',
    `step` VARCHAR(50) NOT NULL DEFAULT 'queued',
    `progress` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `input` VARCHAR(300) NOT NULL,
    `generation_id` VARCHAR(64) DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
