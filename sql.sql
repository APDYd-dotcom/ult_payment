USE ult_payment;

-- 1. Ajouter la colonne d'accès aux examens
ALTER TABLE penalite
    ADD COLUMN IF NOT EXISTS exam_acces TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '1 = accès aux examens conservé, 0 = accès perdu'
    AFTER montant_penalite;

-- 2. Recréer la fonction de barème des pénalités
DROP FUNCTION IF EXISTS fn_get_penalty_percentage;

DELIMITER $$

CREATE FUNCTION fn_get_penalty_percentage(days_late INT)
RETURNS DECIMAL(5,2)
DETERMINISTIC
BEGIN
    IF days_late <= 15 THEN
        RETURN 0.00;
    ELSEIF days_late BETWEEN 16 AND 30 THEN
        RETURN 10.00;
    ELSEIF days_late BETWEEN 31 AND 60 THEN
        RETURN 15.00;
    ELSE
        RETURN 20.00;
    END IF;
END$$

DELIMITER ;

-- 3. Recréer la procédure appelée automatiquement par le trigger paiement
DROP PROCEDURE IF EXISTS sp_recalculate_payment_business;

DELIMITER $$

CREATE PROCEDURE sp_recalculate_payment_business(IN p_payment_id INT)
BEGIN
    DECLARE v_amount           DECIMAL(10,2);
    DECLARE v_tranche_id       INT;
    DECLARE v_student_id       INT;
    DECLARE v_created_at       DATETIME;
    DECLARE v_date_fin         DATE;
    DECLARE v_minerval_total   DECIMAL(10,2);
    DECLARE v_expected_amount  DECIMAL(10,2);
    DECLARE v_delay            INT;
    DECLARE v_penalty_pct      DECIMAL(5,2);
    DECLARE v_penalty_amount   DECIMAL(10,2);
    DECLARE v_exam_acces       TINYINT(1);

    SELECT amount, tranche_id, student_id, created_at
    INTO   v_amount, v_tranche_id, v_student_id, v_created_at
    FROM payment
    WHERE id = p_payment_id;

    SELECT t.date_fin, d.minerval_total
    INTO   v_date_fin, v_minerval_total
    FROM tranche t
    JOIN department d ON t.department_id = d.id
    WHERE t.id = v_tranche_id;

    -- Montant dû pour une tranche : minerval total / 4, selon la logique existante.
    SET v_expected_amount = ROUND(v_minerval_total / 4, 2);

    DELETE FROM partial_payment WHERE payment_id = p_payment_id;
    DELETE FROM penalite WHERE payment_id = p_payment_id;

    IF v_amount < v_expected_amount THEN
        INSERT INTO partial_payment (
            student_id,
            payment_id,
            expected_amount,
            paid_amount,
            missing_amount
        ) VALUES (
            v_student_id,
            p_payment_id,
            v_expected_amount,
            v_amount,
            v_expected_amount - v_amount
        );
    END IF;

    SET v_delay = GREATEST(DATEDIFF(DATE(v_created_at), v_date_fin), 0);
    SET v_penalty_pct = fn_get_penalty_percentage(v_delay);
    SET v_exam_acces = IF(v_delay > 60, 0, 1);

    -- Aucun enregistrement de pénalité pendant le délai de grâce.
    IF v_penalty_pct > 0 THEN
        SET v_penalty_amount = ROUND(v_expected_amount * v_penalty_pct / 100, 2);

        INSERT INTO penalite (
            student_id,
            payment_id,
            tranche_id,
            due_date,
            paid_date,
            retard_jours,
            pourcentage_penalite,
            montant_penalite,
            exam_acces
        ) VALUES (
            v_student_id,
            p_payment_id,
            v_tranche_id,
            v_date_fin,
            v_created_at,
            v_delay,
            v_penalty_pct,
            v_penalty_amount,
            v_exam_acces
        );
    END IF;
END$$

DELIMITER ;

-- 4. S'assurer que le trigger automatique existe
DROP TRIGGER IF EXISTS trg_payment_after_insert;

DELIMITER $$

CREATE TRIGGER trg_payment_after_insert
AFTER INSERT ON payment
FOR EACH ROW
BEGIN
    CALL sp_recalculate_payment_business(NEW.id);
END$$

DELIMITER ;

-- Optionnel : garder aussi le recalcul si un paiement est modifié
DROP TRIGGER IF EXISTS trg_payment_after_update;

DELIMITER $$

CREATE TRIGGER trg_payment_after_update
AFTER UPDATE ON payment
FOR EACH ROW
BEGIN
    CALL sp_recalculate_payment_business(NEW.id);
END$$

DELIMITER ;









DELIMITER $$

DROP PROCEDURE IF EXISTS sp_recalculate_all_payments$$

CREATE PROCEDURE sp_recalculate_all_payments()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_payment_id INT;

    DECLARE cur CURSOR FOR
        SELECT id FROM payment;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;

    read_loop: LOOP
        FETCH cur INTO v_payment_id;

        IF done = 1 THEN
            LEAVE read_loop;
        END IF;

        CALL sp_recalculate_payment_business(v_payment_id);
    END LOOP;

    CLOSE cur;
END$$

DELIMITER ;

CALL sp_recalculate_all_payments();

DROP PROCEDURE IF EXISTS sp_recalculate_all_payments;