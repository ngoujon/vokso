-- Ajouter la colonne 'statut' avec une valeur par défaut 'on'
ALTER TABLE prompts
ADD COLUMN statut ENUM('on', 'off') NOT NULL DEFAULT 'on';

-- Ajouter la colonne 'update_date' qui s'actualise automatiquement
ALTER TABLE prompts
ADD COLUMN update_date DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Ajouter la colonne 'statut' avec une valeur par défaut 'on'
ALTER TABLE generations
ADD COLUMN statut ENUM('on', 'off') NOT NULL DEFAULT 'on';

-- Ajouter la colonne 'update_date' qui s'actualise automatiquement
ALTER TABLE generations
ADD COLUMN update_date DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Ajouter la colonne 'statut' avec une valeur par défaut 'on'
ALTER TABLE categorie
ADD COLUMN statut ENUM('on', 'off') NOT NULL DEFAULT 'on';

-- Ajouter la colonne 'update_date' qui s'actualise automatiquement
ALTER TABLE categorie
ADD COLUMN update_date DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;


------

-- Création de la table histo_prompts
CREATE TABLE histo_prompts (
    id INT(11) NOT NULL AUTO_INCREMENT,               -- ID unique pour l'historique
    prompt_id INT(11) NOT NULL,                       -- Référence à l'ID de la table prompts
    type ENUM('image', 'texte') NOT NULL,             -- Type provenant de prompts
    description TEXT NOT NULL,                        -- Description provenant de prompts
    statut ENUM('on', 'off') NOT NULL,                -- Statut provenant de prompts
    update_date DATETIME DEFAULT NULL,                -- Date de mise à jour provenant de prompts
    action_type ENUM('INSERT', 'UPDATE', 'DELETE') NOT NULL, -- Type d'action (INSERT, UPDATE, DELETE)
    action_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Date/heure de l'action
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Création d'un trigger pour historiser les INSERTs
DELIMITER $$
CREATE TRIGGER after_prompts_insert
AFTER INSERT ON prompts
FOR EACH ROW
BEGIN
    INSERT INTO histo_prompts (prompt_id, type, description, statut, update_date, action_type)
    VALUES (NEW.id, NEW.type, NEW.description, NEW.statut, NEW.update_date, 'INSERT');
END$$
DELIMITER ;

-- Création d'un trigger pour historiser les UPDATEs
DELIMITER $$
CREATE TRIGGER after_prompts_update
AFTER UPDATE ON prompts
FOR EACH ROW
BEGIN
    INSERT INTO histo_prompts (prompt_id, type, description, statut, update_date, action_type)
    VALUES (NEW.id, NEW.type, NEW.description, NEW.statut, NEW.update_date, 'UPDATE');
END$$
DELIMITER ;

-- Création d'un trigger pour historiser les DELETEs
DELIMITER $$
CREATE TRIGGER after_prompts_delete
AFTER DELETE ON prompts
FOR EACH ROW
BEGIN
    INSERT INTO histo_prompts (prompt_id, type, description, statut, update_date, action_type)
    VALUES (OLD.id, OLD.type, OLD.description, OLD.statut, OLD.update_date, 'DELETE');
END$$
DELIMITER ;
