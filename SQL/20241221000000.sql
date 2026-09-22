-- Schéma de base manquant : `prompts`, `categorie` et `generations` existaient
-- déjà dans la base historique avant que ce dossier SQL/ ne commence à suivre
-- les migrations (2024-12-22) ; leur CREATE TABLE original n'a jamais été
-- commité, ce qui empêchait de repartir d'une base MySQL vide. Reconstitué à
-- partir des ALTER TABLE ... AFTER <colonne> des migrations suivantes et des
-- requêtes de webserver/api/src/Services/PodcastGenerator.php.
-- `histo_prompts` n'est volontairement pas créée ici : SQL/20241222000000.sql
-- la crée elle-même sans IF NOT EXISTS.

-- Pas de COLLATE explicite : on s'aligne sur la collation par défaut de la
-- base (utf8mb4_0900_ai_ci sous MySQL 8), la même que celle utilisée
-- implicitement par `generation_jobs` (20260818010000.sql) et explicitement
-- par `prompt`/`histo_prompt` (20250111000000.sql). Fixer utf8mb4_general_ci
-- ici cassait la jointure `generations.generation_id = generation_jobs.generation_id`
-- (SQLSTATE HY000 1267 "Illegal mix of collations").

CREATE TABLE IF NOT EXISTS prompts (
    id INT(11) NOT NULL AUTO_INCREMENT,
    type ENUM('image', 'texte') NOT NULL,
    description TEXT NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categorie (
    id INT(11) NOT NULL AUTO_INCREMENT,
    generation_id INT(11) DEFAULT NULL,
    label VARCHAR(100) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS generations (
    id INT(11) NOT NULL AUTO_INCREMENT,
    generation_id VARCHAR(255) NOT NULL,
    text_content TEXT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    audio_url VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY generations_generation_id_unique (generation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
