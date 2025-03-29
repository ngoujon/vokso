UPDATE
    `prompt`
SET
    `content` = 'Create a high-quality, minimalist and modern illustration centered on ###REPLACE###, blending the aesthetic of a well-composed instant photo or portrait with Scandinavian-inspired design principles. Use clean geometric shapes and soft pastel or muted tones to evoke calm and clarity. The subject should be treated with the precision and focus of a professional photo—sharp, centered, and well-lit—while maintaining a flat, stylized look without textures or over-detailing. Emphasize negative space, visual symmetry, and a calm, uncluttered background, as in minimalist photography. Incorporate subtle gradients or soft shadows sparingly to enhance depth, giving the piece a photo-like presence while staying true to flat design. The result should feel like a refined snapshot of an idea, elegant and consistent across various themes.'
WHERE
    `prompt`.`idprompt` = 1;

UPDATE
    `prompt`
SET
    `content` = ' Write a clear, well-structured, and concise text in English (maximum 4000 characters) on the topic: ###REPLACE###.\n\n Start with a brief introduction explaining the relevance and importance of ###REPLACE### today. Then, provide a short historical background or origin if applicable.\n\n Explain the key concepts or essential elements related to ###REPLACE### in a way that is easy to understand. Use concrete examples or real-world applications to illustrate its significance in fields such as science, culture, or the economy.\n\n Briefly mention any major challenges, controversies, or limitations. Conclude with a reflection on the future outlook or potential evolution of ###REPLACE###.\n\n The tone should be informative yet engaging, avoiding a rigid or academic structure. Do not use bullet points or headings — the text should read like a natural, flowing explanation. \n\nThe text need to be in French'
WHERE
    `prompt`.`idprompt` = 3;