-- Corrige un bug latent découvert en tentant d'appliquer 20260923010000.sql
-- en production : le trigger `before_prompt_update` (20250111000000.sql)
-- copie OLD.type dans histo_prompt.type à chaque UPDATE de `prompt`, mais
-- histo_prompt.type est resté un ENUM('texte','image','keyword') alors que
-- `prompt.type` a été élargi en VARCHAR(20) par 20250328000000.sql (pour
-- accueillir 'injection', puis 'titre' par 20260922000000.sql). En sql_mode
-- STRICT_TRANS_TABLES (par défaut sur ce serveur), toute UPDATE d'une ligne
-- `prompt` dont le type est 'injection' ou 'titre' échoue donc avec
-- "ERROR 1265: Data truncated for column 'type'" — ce qui bloquait déjà
-- silencieusement toute mise à jour de ces deux prompts avant même cette
-- migration.
ALTER TABLE histo_prompt
    MODIFY type VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL;
