USE ult_payment;

ALTER TABLE user
    ADD COLUMN IF NOT EXISTS failed_attempts INT NOT NULL DEFAULT 0
        COMMENT 'Nombre de tentatives de connexion echouees dans la fenetre active, verrouillage a 3'
        AFTER created_at,
    ADD COLUMN IF NOT EXISTS is_locked TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '0 = deverrouille, 1 = verrouille'
        AFTER failed_attempts,
    ADD COLUMN IF NOT EXISTS last_failed_attempt DATETIME DEFAULT NULL
        COMMENT 'Date de la derniere tentative de connexion echouee'
        AFTER is_locked,
    ADD COLUMN IF NOT EXISTS unlock_time DATETIME DEFAULT NULL
        COMMENT 'Reserve pour un deverrouillage automatique futur'
        AFTER last_failed_attempt;
