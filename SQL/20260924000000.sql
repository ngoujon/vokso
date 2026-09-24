-- Durcissement de l'authentification admin : changement de mot de passe
-- imposable (comptes de démarrage, comptes créés par un admin) et 2FA TOTP
-- optionnelle mais réservée au rôle admin (voir AuthController::twoFactor*).
ALTER TABLE `users`
    ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
    ADD COLUMN `totp_secret` VARCHAR(64) DEFAULT NULL AFTER `must_change_password`,
    ADD COLUMN `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `totp_secret`;

-- Rotation du mot de passe des comptes de démarrage : le mot de passe
-- "ChangeMoi123!" utilisé jusqu'ici (SQL/20260819010000.sql et
-- SQL/20260923000000.sql) est visible dans l'historique git et ne doit plus
-- être considéré comme secret. Nouveaux mots de passe communiqués hors
-- dépôt ; changement forcé à la prochaine connexion (must_change_password).
UPDATE `users`
SET `password_hash` = '*** empreinte supprimée : créer les comptes avec php artisan vokso:create-admin ***',
    `must_change_password` = 1
WHERE `email` = 'admin@vokso.fr';

UPDATE `users`
SET `password_hash` = '*** empreinte supprimée : créer les comptes avec php artisan vokso:create-admin ***',
    `must_change_password` = 1
WHERE `email` = 'user@vokso.fr';
