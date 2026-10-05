-- Format des épisodes (octobre 2026) : durée visée (1 à 15 minutes) et niveau
-- de profondeur (1 = Survol, 2 = Découverte, 3 = Approfondi, 4 = Avancé,
-- 5 = Expert), choisis par curseurs à la création et affichés dans la
-- discothèque et sur la page de l'épisode (voir App\Support\EpisodeFormat).
--
-- Tant que cette migration n'est pas appliquée, le code tourne sans ces
-- colonnes : les épisodes sont créés au format par défaut (5 min, niveau 3)
-- et aucun format n'est affiché.
ALTER TABLE `generations`
    ADD COLUMN `duration_minutes` TINYINT UNSIGNED NULL AFTER `audio_url`,
    ADD COLUMN `level` TINYINT UNSIGNED NULL AFTER `duration_minutes`;

ALTER TABLE `generation_jobs`
    ADD COLUMN `duration_minutes` TINYINT UNSIGNED NULL AFTER `audio_path`,
    ADD COLUMN `level` TINYINT UNSIGNED NULL AFTER `duration_minutes`;

-- Épisodes existants : tous écrits avec l'ancienne consigne (5 minutes,
-- « accessible à un large public »), soit le niveau Découverte.
UPDATE `generations` SET `duration_minutes` = 5, `level` = 2 WHERE `level` IS NULL;

-- La consigne de narration est désormais construite dans le code
-- (EpisodeFormat::narrationPrompt), sans plan imposé : le prompt « texte » en
-- base n'est plus lu : on le désactive (le trigger before_prompt_update en
-- garde l'ancienne version dans histo_prompt).
UPDATE `prompt` SET `statut` = 'off' WHERE `type` = 'texte';
