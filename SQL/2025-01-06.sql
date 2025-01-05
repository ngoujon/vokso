ALTER TABLE generations
ADD COLUMN unique_code VARCHAR(255) COLLATE utf8mb4_general_ci NULL AFTER generation_id;