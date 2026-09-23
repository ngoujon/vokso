-- Compte utilisateur de démonstration (role='user'), pour tester l'espace
-- utilisateur sans passer par l'inscription publique. Le compte admin de
-- démarrage existe déjà (voir SQL/20260819010000.sql, admin@vokso.fr).
-- Mot de passe temporaire "ChangeMoi123!" (même convention que le compte
-- admin, hash bcrypt ci-dessous) : à changer dès la première connexion.
INSERT IGNORE INTO `users` (`email`, `password_hash`, `role`, `status`)
VALUES ('user@vokso.fr', '*** empreinte supprimée : créer les comptes avec php artisan vokso:create-admin ***', 'user', 'active');

-- Formule gratuite par défaut, comme le fait AuthController::register() pour
-- tout nouveau compte (GenerationController s'appuie sur cette ligne pour
-- connaître le plan de l'utilisateur).
INSERT IGNORE INTO `subscriptions` (`user_id`, `plan`, `status`)
SELECT `id`, 'decouverte', 'active' FROM `users` WHERE `email` = 'user@vokso.fr';
