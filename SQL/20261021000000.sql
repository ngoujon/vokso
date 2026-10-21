-- Statistiques d'écoute et de téléchargement, et registre des choix du
-- bandeau cookies (octobre 2026).
--
-- podcast_events : une ligne par écoute (première lecture d'un épisode dans
-- une page) ou par téléchargement (MP3, PDF). Aucune donnée personnelle :
-- ni IP, ni identifiant de visiteur, seulement l'épisode, le type et l'heure.
--
-- cookie_consents : preuve des choix exprimés dans le bandeau cookies
-- (article 7.1 du RGPD), exportable depuis l'administration en cas de
-- contrôle. Identifiant aléatoire tiré par le navigateur, IP pseudonymisée
-- (empreinte salée, jamais l'adresse en clair). Purge automatique après
-- 5 ans (voir App\Http\Controllers\ConsentController).
--
-- Tant que cette migration n'est pas appliquée, le site fonctionne : les
-- événements et les choix ne sont simplement pas enregistrés.
CREATE TABLE IF NOT EXISTS `podcast_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `generation_id` VARCHAR(64) NOT NULL,
    `type` VARCHAR(20) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `podcast_events_type_created` (`type`, `created_at`),
    KEY `podcast_events_generation` (`generation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cookie_consents` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `consent_id` CHAR(36) NOT NULL,
    `choice` VARCHAR(10) NOT NULL,
    `banner_version` VARCHAR(20) NOT NULL,
    `page` VARCHAR(255) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    `ip_hash` CHAR(64) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `cookie_consents_created` (`created_at`),
    KEY `cookie_consents_consent_id` (`consent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
