-- 1) Le titre généré atterrit tel quel dans un <h3> côté front (pas de rendu
-- Markdown) : un modèle qui renvoie "**Mon titre**" ou "# Mon titre" affiche
-- donc les astérisques/dièses bruts à l'utilisateur. Le prompt interdisait
-- déjà les guillemets/ponctuation finale mais pas le formatage Markdown.
UPDATE prompt
SET content = 'Propose un titre court, accrocheur et en français pour un épisode de podcast sur le sujet suivant, sans guillemets ni ponctuation finale, sans aucun formatage Markdown (pas d''astérisques, dièses, tirets de liste ou autres symboles de mise en forme : uniquement du texte brut), 80 caractères maximum : ###REPLACE###'
WHERE type = 'titre';

-- 2) Deux ajustements au prompt de narration :
--    - il ne doit pas ouvrir en reformulant/répétant la question de
--      l'utilisateur ("Vous avez demandé...", ou le sujet tel quel en
--      intro) : ça sonne comme un accusé de réception, pas comme un podcast ;
--    - le modèle peut chercher sur le web des informations complémentaires
--      récentes ou factuelles quand c'est utile (voir MistralAgentTextProvider,
--      qui équipe cet appel précis de l'outil web_search côté Mistral) — mais
--      seulement si besoin, pas systématiquement.
-- La contrainte 680-750 mots (calibrée le 22/09/2026, voir 20260922000000.sql)
-- est conservée à l'identique.
UPDATE prompt
SET content = 'Write a clear, structured, and engaging text in French on the given topic. Hard constraint: 680 to 750 words, never more, even if it means covering fewer sub-topics in less depth — this is a strict limit, not a suggestion, because the text is read aloud and must fit in a 5-minute podcast segment (aim close to 720 words). The text should be informative and accessible to a wide audience. Do not open by restating, repeating, or echoing the listener''s question or topic as given (avoid phrasing like "Vous avez demandé..." or starting with the topic verbatim as a title/heading) — begin directly with an engaging hook, as a podcast host would, that draws the listener into the subject. If you are unsure about recent facts, figures, or developments related to ###REPLACE###, use web search to verify them before writing, so the content stays accurate and up to date; do not mention the search itself in the narration. Cover its origins and key milestones, its most significant figures or contributions, and the fundamental concepts needed to understand it, using one or two concrete examples. Discuss its practical applications or current impact, and a major challenge or open question. Conclude with a brief, engaging reflection. Ensure the text is logical, fluid, and engaging, with proper grammar and style. Write only the narration text itself, with no title, heading, or markup, and stay within the 680-750 word range. Topic: ###REPLACE###'
WHERE type = 'texte';

-- 3) Gestion des catégories par IA : jusqu'ici seul le libellé (un mot-clé)
-- était généré. On ajoute une icône (Bootstrap Icons, déjà la seule
-- bibliothèque d'icônes utilisée côté front, voir bi-mic-fill/bi-shield-lock
-- dans Home.js) et une image de couverture, choisies/générées à la création
-- de chaque nouvelle catégorie (voir PodcastGenerator::ensureCategoryAssets()).
ALTER TABLE categorie
    ADD COLUMN icon VARCHAR(50) DEFAULT NULL AFTER label,
    ADD COLUMN cover_image VARCHAR(255) DEFAULT NULL AFTER icon;

-- La liste d'icônes est volontairement fermée et embarquée dans le prompt :
-- on ne fait confiance au modèle que pour un choix parmi des valeurs connues
-- d'exister dans bootstrap-icons, jamais pour inventer une classe CSS
-- arbitraire (PodcastGenerator::sanitizeIcon() revalide de toute façon la
-- réponse contre cette même liste et retombe sur "soundwave" sinon).
INSERT INTO prompt (type, content, statut)
SELECT 'categorie_icone',
    'Voici une catégorie de podcast : "###REPLACE###". Choisis, dans la liste suivante, le seul nom d''icône Bootstrap Icons qui la représente le mieux, et réponds uniquement avec ce nom (sans "bi-", sans ponctuation, sans explication) : cpu, laptop, flask, heart-pulse, hourglass-split, bank, music-note-beamed, trophy, egg-fried, graph-up-arrow, cash-coin, airplane, tree, palette, mortarboard, people, camera-reels, book, rocket, controller, briefcase, emoji-smile, lightbulb, moon-stars, car-front, bag, globe, newspaper, film, gear, house, cup-hot, joystick, umbrella, compass, map, calculator, code-slash, star, building, flag, puzzle, chat-dots, soundwave.',
    'on'
WHERE NOT EXISTS (SELECT 1 FROM prompt WHERE type = 'categorie_icone');

INSERT INTO prompt (type, content, statut)
SELECT 'categorie_cover',
    'Create a high-quality, minimalist and modern cover illustration representing the podcast category "###REPLACE###". Use clean geometric shapes, soft pastel or muted tones, and a calm, uncluttered composition in the same Scandinavian-inspired flat design style as the rest of the app''s visuals. The image should read clearly as a small thumbnail: a single strong symbolic subject, centered, with no text and no clutter.',
    'on'
WHERE NOT EXISTS (SELECT 1 FROM prompt WHERE type = 'categorie_cover');
