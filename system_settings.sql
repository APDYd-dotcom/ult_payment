USE ult_payment;

INSERT INTO settings (setting_key, setting_value, category)
VALUES
    ('tranche_1_due_date', '2026-04-10', 'system'),
    ('tranche_1_derogation_deadline', '2026-03-25', 'system'),
    ('tranche_2_due_date', '2026-06-30', 'system'),
    ('tranche_2_derogation_deadline', '2026-06-15', 'system'),
    ('tranche_3_due_date', '2026-09-15', 'system'),
    ('tranche_3_derogation_deadline', '2026-08-31', 'system'),
    ('tranche_4_due_date', '2026-11-15', 'system'),
    ('tranche_4_derogation_deadline', '2026-11-10', 'system'),
    ('penalty_grace_period', '15', 'system'),
    ('penalty_percent_level1', '10', 'system'),
    ('penalty_days_level1', '16', 'system'),
    ('penalty_percent_level2', '15', 'system'),
    ('penalty_days_level2', '31', 'system'),
    ('penalty_percent_level3', '20', 'system'),
    ('penalty_days_level3', '61', 'system'),
    ('school_name', 'Universite du Lac Tanganyika ASBL', 'system'),
    ('school_address', 'Q. Kigobe, B.P. 5403 Mutanga', 'system'),
    ('school_phone', '22 243645 / 22 246843', 'system'),
    ('school_nif', '2281591484', 'system'),
    ('academic_year_start', '2026-01-01', 'system'),
    ('academic_year_end', '2026-12-31', 'system'),
    ('max_login_attempts', '5', 'system'),
    ('session_timeout_minutes', '15', 'system'),
    ('enable_2fa', '0', 'system')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);

DROP FUNCTION IF EXISTS get_setting;

DELIMITER $$

CREATE FUNCTION get_setting(p_key VARCHAR(255), p_default VARCHAR(255))
RETURNS VARCHAR(255)
READS SQL DATA
BEGIN
    DECLARE v_value VARCHAR(255);

    SELECT setting_value
    INTO v_value
    FROM settings
    WHERE setting_key = p_key
    LIMIT 1;

    RETURN COALESCE(v_value, p_default);
END$$

DROP FUNCTION IF EXISTS fn_get_penalty_percentage$$

CREATE FUNCTION fn_get_penalty_percentage(days_late INT)
RETURNS DECIMAL(5,2)
READS SQL DATA
BEGIN
    DECLARE v_grace INT DEFAULT CAST(get_setting('penalty_grace_period', '15') AS UNSIGNED);
    DECLARE v_level1_days INT DEFAULT CAST(get_setting('penalty_days_level1', '16') AS UNSIGNED);
    DECLARE v_level2_days INT DEFAULT CAST(get_setting('penalty_days_level2', '31') AS UNSIGNED);
    DECLARE v_level3_days INT DEFAULT CAST(get_setting('penalty_days_level3', '61') AS UNSIGNED);
    DECLARE v_level1_pct DECIMAL(5,2) DEFAULT CAST(get_setting('penalty_percent_level1', '10') AS DECIMAL(5,2));
    DECLARE v_level2_pct DECIMAL(5,2) DEFAULT CAST(get_setting('penalty_percent_level2', '15') AS DECIMAL(5,2));
    DECLARE v_level3_pct DECIMAL(5,2) DEFAULT CAST(get_setting('penalty_percent_level3', '20') AS DECIMAL(5,2));

    IF days_late <= v_grace THEN
        RETURN 0.00;
    ELSEIF days_late >= v_level3_days THEN
        RETURN v_level3_pct;
    ELSEIF days_late >= v_level2_days THEN
        RETURN v_level2_pct;
    ELSEIF days_late >= v_level1_days THEN
        RETURN v_level1_pct;
    END IF;

    RETURN 0.00;
END$$

DROP PROCEDURE IF EXISTS sp_recalculate_payment_business$$

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
    DECLARE v_exam_loss_days   INT DEFAULT CAST(get_setting('penalty_days_level3', '61') AS UNSIGNED);

    SELECT amount, tranche_id, student_id, created_at
    INTO   v_amount, v_tranche_id, v_student_id, v_created_at
    FROM payment
    WHERE id = p_payment_id;

    SELECT t.date_fin, d.minerval_total
    INTO   v_date_fin, v_minerval_total
    FROM tranche t
    JOIN department d ON t.department_id = d.id
    WHERE t.id = v_tranche_id;

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
    SET v_exam_acces = IF(v_delay >= v_exam_loss_days, 0, 1);

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

DROP TRIGGER IF EXISTS trg_payment_after_insert$$

CREATE TRIGGER trg_payment_after_insert
AFTER INSERT ON payment
FOR EACH ROW
BEGIN
    CALL sp_recalculate_payment_business(NEW.id);
END$$

DROP TRIGGER IF EXISTS trg_payment_after_update$$

CREATE TRIGGER trg_payment_after_update
AFTER UPDATE ON payment
FOR EACH ROW
BEGIN
    CALL sp_recalculate_payment_business(NEW.id);
END$$

DROP PROCEDURE IF EXISTS sp_recalculate_all_payments$$

CREATE PROCEDURE sp_recalculate_all_payments()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_payment_id INT;

    DECLARE cur CURSOR FOR SELECT id FROM payment;
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

-- Decommentez pour recalculer les paiements existants apres modification des baremes.
-- CALL sp_recalculate_all_payments();
