-- SEO / GEO (octobre 2026)
--
-- 1. URL lisibles des épisodes : /podcast/{slug} au lieu de
--    /podcast/gen_6ab267b302f6d-{titre}. Le slug est rempli par
--    `php artisan vokso:episode-slugs` après cette migration ; les anciennes
--    URL redirigent en 301 (PodcastPageController).
ALTER TABLE `generations`
    ADD COLUMN `slug` VARCHAR(100) NULL AFTER `generation_id`,
    ADD UNIQUE KEY `generations_slug_unique` (`slug`);

-- 2. Titres : la consigne « sans ponctuation » produisait des titres
--    illisibles (« Fourmis autoroutes jardin astuces et mystères »). Un titre
--    naturel, ponctué et de longueur adaptée aux résultats de recherche
--    (45 à 65 caractères) est mieux cliqué et mieux compris par les moteurs.
INSERT INTO `histo_prompt` (`idprompt`, `type`, `content`, `statut`)
    SELECT `idprompt`, `type`, `content`, `statut` FROM `prompt` WHERE `type` IN ('titre', 'keyword');

UPDATE `prompt` SET `content` = 'Propose un titre d''épisode de podcast en français pour le sujet suivant. Le titre doit être naturel et ponctué normalement (deux-points, virgule, apostrophes ou point d''interrogation si c''est une question), clair et accrocheur, commencer par le mot-clé principal du sujet, et faire entre 45 et 65 caractères. Pas de guillemets, pas de point final, aucun formatage Markdown (pas d''astérisques, dièses ni tirets de liste) : uniquement le titre en texte brut. Sujet : ###REPLACE###'
WHERE `type` = 'titre';

-- 3. Catégories : la question ouverte « quel mot décrit la catégorie »
--    créait une catégorie par épisode ou presque (26 catégories, la plupart
--    avec un seul épisode), donc des pages thématiques vides de sens pour le
--    référencement. Liste fermée de 10 rubriques.
UPDATE `prompt` SET `content` = 'Choisis la rubrique qui correspond le mieux au sujet de podcast suivant, parmi cette liste fermée : Sciences, Espace, Nature et environnement, Histoire, Société, Culture et arts, Technologie, Santé, Gastronomie, Sport. Réponds uniquement par le nom exact de la rubrique, tel qu''il est écrit dans la liste, sans ponctuation ni explication. Sujet : ###REPLACE###'
WHERE `type` = 'keyword';

-- Les rubriques réutilisent des catégories existantes (icône et couverture déjà générées)...
UPDATE `categorie` SET `label` = 'Sciences' WHERE `label` = 'Science';
UPDATE `categorie` SET `label` = 'Espace' WHERE `label` = 'Astronomie';
UPDATE `categorie` SET `label` = 'Nature et environnement' WHERE `label` = 'Écologie';
UPDATE `categorie` SET `label` = 'Culture et arts' WHERE `label` = 'Art';
UPDATE `categorie` SET `label` = 'Société' WHERE `label` = 'Diplomatie';

-- ... et les épisodes des anciennes catégories y sont rangés.
UPDATE `generations` g
JOIN `categorie` old ON old.idcategorie = g.idcategorie
JOIN `categorie` new ON new.label = CASE old.label
        WHEN 'Astrophysique' THEN 'Espace'
        WHEN 'Aérospatial' THEN 'Espace'
        WHEN 'Phénomène' THEN 'Sciences'
        WHEN 'Optique' THEN 'Sciences'
        WHEN 'Géologie' THEN 'Sciences'
        WHEN 'Biologie' THEN 'Nature et environnement'
        WHEN 'Zoologie' THEN 'Nature et environnement'
        WHEN 'Botanique' THEN 'Nature et environnement'
        WHEN 'Neurosciences' THEN 'Santé'
        WHEN 'Immunologie' THEN 'Santé'
        WHEN 'Archéologie' THEN 'Histoire'
        WHEN 'Viticulture' THEN 'Gastronomie'
        WHEN 'Littérature' THEN 'Culture et arts'
        WHEN 'Cinéma' THEN 'Culture et arts'
        WHEN 'Mobilité' THEN 'Société'
        WHEN 'Génération' THEN 'Technologie'
    END
SET g.idcategorie = new.idcategorie;
