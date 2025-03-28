UPDATE
    `prompts`
SET
    `description` = 'Create a modern and minimalistic image featuring ###REPLACE###. The scene should have clean lines, balanced composition, and a harmonious color palette with muted or pastel tones. Use simple geometric shapes, negative space, and a sleek aesthetic to evoke a sense of elegance and sophistication. The background should be uncluttered, with a focus on subtle gradients or solid colors to emphasize the subject. The style should feel contemporary and visually appealing, with a touch of Scandinavian design inspiration. Avoid excessive details, keeping the focus on simplicity and clarity.'
WHERE
    `type` = 'image';

UPDATE
    `prompts`
SET
    `description` = 'Write a clear, structured, and engaging text in French on the given topic, limited to 4000 characters. The text should be informative, detailed, and accessible to a wide audience. Introduce ###REPLACE### by explaining its importance and relevance today, hinting at the main points without explicitly listing them. Provide a historical overview of ###REPLACE###, focusing on its origins, evolution, and key milestones. Highlight significant figures associated with ###REPLACE###, describing their main contributions. Explain fundamental concepts, models, or theories related to ###REPLACE### in a way that is easy to understand, using examples where necessary. Discuss ###REPLACE###’s practical applications and its current impact across various fields, such as science, culture, or the economy. Address challenges, controversies, or limitations tied to ###REPLACE###, and analyze future opportunities or developments. Conclude by summarizing the main ideas about ###REPLACE### and offering a reflection on potential future research or evolution. Ensure the text is logical, fluid, and engaging, with proper grammar and style.'
WHERE
    `type` = 'texte';

DELETE FROM
    `generation_db`.`categorie`;

-- Renommer la colonne `id` en `idcategorie`
ALTER TABLE
    categorie CHANGE id idcategorie INT(11) NOT NULL AUTO_INCREMENT;

-- Supprimer la colonne `generation_id`
ALTER TABLE
    categorie DROP COLUMN generation_id;

ALTER TABLE
    generations
ADD
    COLUMN idcategorie INT(11) NOT NULL DEFAULT 0
AFTER
    audio_url;

INSERT INTO
    prompt (type, content, statut)
VALUES
    (
        'texte',
        'Write a clear, structured, and engaging text in French on the given topic, limited to 4000 characters. The text should be informative, detailed, and accessible to a wide audience. Introduce ###REPLACE### by explaining its importance and relevance today, hinting at the main points without explicitly listing them. Provide a historical overview of ###REPLACE###, focusing on its origins, evolution, and key milestones. Highlight significant figures associated with ###REPLACE###, describing their main contributions. Explain fundamental concepts, models, or theories related to ###REPLACE### in a way that is easy to understand, using examples where necessary. Discuss ###REPLACE###’s practical applications and its current impact across various fields, such as science, culture, or the economy. Address challenges, controversies, or limitations tied to ###REPLACE###, and analyze future opportunities or developments. Conclude by summarizing the main ideas about ###REPLACE### and offering a reflection on potential future research or evolution. Ensure the text is logical, fluid, and engaging, with proper grammar and style.',
        'on'
    );

INSERT INTO
    prompt (type, content, statut)
VALUES
    (
        'image',
        'Create a modern and minimalistic image featuring ###REPLACE###. The scene should have clean lines, balanced composition, and a harmonious color palette with muted or pastel tones. Use simple geometric shapes, negative space, and a sleek aesthetic to evoke a sense of elegance and sophistication. The background should be uncluttered, with a focus on subtle gradients or solid colors to emphasize the subject. The style should feel contemporary and visually appealing, with a touch of Scandinavian design inspiration. Avoid excessive details, keeping the focus on simplicity and clarity.',
        'on'
    );

INSERT INTO
    prompt (type, content, statut)
VALUES
    (
        'keyword',
        'Quel est le mot qui décrit la catégorie pour :',
        'on'
    );

CREATE TABLE histo_prompt (
    id_histo INT AUTO_INCREMENT PRIMARY KEY,
    idprompt INT NOT NULL,
    type ENUM('texte', 'image', 'keyword') NOT NULL,
    content TEXT NOT NULL,
    statut ENUM('on', 'off') NOT NULL,
    modification_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idprompt) REFERENCES prompt(idprompt) ON DELETE CASCADE
);

DELIMITER $ $ CREATE TRIGGER before_prompt_update BEFORE
UPDATE
    ON prompt FOR EACH ROW BEGIN
INSERT INTO
    histo_prompt (
        idprompt,
        type,
        content,
        statut,
        modification_date
    )
VALUES
    (
        OLD.idprompt,
        OLD.type,
        OLD.content,
        OLD.statut,
        CURRENT_TIMESTAMP
    );

END $ $ DELIMITER;

DROP TABLE `generation_db`.`prompts`;

DROP TABLE `generation_db`.`histo_prompts`;