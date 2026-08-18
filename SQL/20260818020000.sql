-- Permet de démarrer une génération à partir d'un fichier audio (transcrit
-- par le fournisseur de transcription) plutôt que d'un sujet texte : le job
-- garde une trace du type de source et, pour l'audio, du chemin du fichier
-- déposé le temps que le worker le transcrive.
ALTER TABLE `generation_jobs`
    ADD COLUMN `source_type` ENUM('text', 'audio') NOT NULL DEFAULT 'text' AFTER `job_id`,
    ADD COLUMN `audio_path` VARCHAR(255) DEFAULT NULL AFTER `input`,
    MODIFY COLUMN `input` VARCHAR(300) DEFAULT NULL;
