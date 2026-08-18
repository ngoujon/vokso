-- Le mot de passe ne doit jamais être versionné en clair.
-- Remplacer :db_password par une valeur forte générée hors dépôt (ex: gestionnaire de secrets),
-- puis exécuter ce script avec la variable substituée.
CREATE USER 'webcli'@'%' IDENTIFIED BY :db_password;
GRANT SELECT, INSERT, UPDATE, DELETE ON `generation_db`.* TO 'webcli'@'%';
FLUSH PRIVILEGES;