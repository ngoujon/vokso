-- Ajoute les comptes utilisateurs/admin et le suivi du coût de génération,
-- prérequis à l'espace utilisateur et au tableau de bord admin.

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(190) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    `status` ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Jetons d'API opaques (pas de cookies : le front et l'API ne sont pas
-- forcément sur le même sous-domaine). Un jeton par connexion, purgé à
-- l'expiration ou à la déconnexion.
CREATE TABLE IF NOT EXISTS `auth_tokens` (
    `token` VARCHAR(64) NOT NULL,
    `user_id` INT(11) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`token`),
    KEY `auth_tokens_user_id` (`user_id`),
    CONSTRAINT `auth_tokens_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rattachement (optionnel : les générations anonymes existantes restent
-- NULL) des podcasts à un compte, et estimation du coût par étape de
-- génération (texte, image, audio), en dollars US.
ALTER TABLE `generations`
    ADD COLUMN `user_id` INT(11) DEFAULT NULL AFTER `idcategorie`,
    ADD COLUMN `cost_text` DECIMAL(10, 5) NOT NULL DEFAULT 0 AFTER `user_id`,
    ADD COLUMN `cost_image` DECIMAL(10, 5) NOT NULL DEFAULT 0 AFTER `cost_text`,
    ADD COLUMN `cost_audio` DECIMAL(10, 5) NOT NULL DEFAULT 0 AFTER `cost_image`,
    ADD COLUMN `cost_total` DECIMAL(10, 5) NOT NULL DEFAULT 0 AFTER `cost_audio`,
    ADD KEY `generations_user_id` (`user_id`);

ALTER TABLE `generation_jobs`
    ADD COLUMN `user_id` INT(11) DEFAULT NULL AFTER `job_id`;

-- Compte admin de démarrage. Mot de passe temporaire "ChangeMoi123!" (hash
-- bcrypt ci-dessous) : à changer dès la première connexion.
INSERT IGNORE INTO `users` (`email`, `password_hash`, `role`, `status`)
VALUES ('admin@qwaipod.fr', '*** empreinte supprimée : créer les comptes avec php artisan vokso:create-admin ***', 'admin', 'active');
