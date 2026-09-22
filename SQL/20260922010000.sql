-- Corrige un contenu de `prompt` désaligné avec son `type`, introduit par
-- deux migrations plus anciennes qui ciblaient des lignes par `idprompt` codé
-- en dur au lieu du `type` : `20250328000000.sql` et `20250329161900.sql`
-- visaient toutes deux `idprompt = 1` en pensant mettre à jour la ligne
-- `image`, mais l'ordre d'insertion de `20250111000000.sql` place `texte` en
-- 1, `image` en 2, `keyword` en 3 — elles ont donc écrasé le prompt `texte`
-- avec un prompt d'image, et `20250329161900.sql` a en plus écrasé `keyword`
-- (idprompt = 3) avec un prompt d'article anglais. Conséquence en
-- production : la génération de podcast produisait une description d'image
-- en anglais comme texte de narration, et la catégorisation renvoyait
-- littéralement le sujet saisi au lieu d'une vraie catégorie.
--
-- Corrigé ici en réassignant le contenu par `type` (la seule clé fiable),
-- pas par `idprompt`.

UPDATE prompt
SET content = 'Write a clear, structured, and engaging text in French on the given topic. Hard constraint: 680 to 750 words, never more, even if it means covering fewer sub-topics in less depth — this is a strict limit, not a suggestion, because the text is read aloud and must fit in a 5-minute podcast segment (aim close to 720 words). The text should be informative and accessible to a wide audience. Introduce ###REPLACE### by explaining its importance and relevance today. Cover its origins and key milestones, its most significant figures or contributions, and the fundamental concepts needed to understand it, using one or two concrete examples. Discuss its practical applications or current impact, and a major challenge or open question. Conclude with a brief, engaging reflection. Ensure the text is logical, fluid, and engaging, with proper grammar and style. Write only the narration text itself, with no title, heading, or markup, and stay within the 680-750 word range.'
WHERE type = 'texte';

UPDATE prompt
SET content = 'Create a high-quality, minimalist and modern illustration centered on ###REPLACE###, blending the aesthetic of a well-composed instant photo or portrait with Scandinavian-inspired design principles. Use clean geometric shapes and soft pastel or muted tones to evoke calm and clarity. The subject should be treated with the precision and focus of a professional photo—sharp, centered, and well-lit—while maintaining a flat, stylized look without textures or over-detailing. Emphasize negative space, visual symmetry, and a calm, uncluttered background, as in minimalist photography. Incorporate subtle gradients or soft shadows sparingly to enhance depth, giving the piece a photo-like presence while staying true to flat design. The result should feel like a refined snapshot of an idea, elegant and consistent across various themes.'
WHERE type = 'image';

-- Le code (PodcastGenerator::generateTextCategoryAndTitle()) fait un
-- str_replace('###REPLACE###', $userInput, $keywordPrompt) : sans ce jeton,
-- le sujet saisi n'était jamais transmis au modèle pour la catégorisation.
UPDATE prompt
SET content = 'Quel est le mot qui décrit la catégorie pour : ###REPLACE###'
WHERE type = 'keyword';
