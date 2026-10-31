SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
DELIMITER //

DROP FUNCTION IF EXISTS fn_get_penalty_percentage//
CREATE FUNCTION fn_get_penalty_percentage(days INT) RETURNS decimal(5,2) DETERMINISTIC
BEGIN
    IF days <= 15 THEN RETURN 0.00;
    ELSEIF days <= 30 THEN RETURN 10.00;
    ELSEIF days <= 60 THEN RETURN 15.00;
    ELSE RETURN 20.00;
    END IF;
END //

DROP PROCEDURE IF EXISTS sp_archive_record//
CREATE PROCEDURE sp_archive_record(IN p_table VARCHAR(50), IN p_id INT, IN p_user INT)
BEGIN
    IF p_table = 'student' THEN
        INSERT INTO archives (table_name, record_id, data, archived_by)
        SELECT 'student', id, JSON_OBJECT('id', id, 'matricule', matricule, 'name', name, 'age', age, 'department_id', department_id, 'created_at', created_at), p_user
        FROM student WHERE id = p_id;
    ELSEIF p_table = 'payment' THEN
        INSERT INTO archives (table_name, record_id, data, archived_by)
        SELECT 'payment', id, JSON_OBJECT('id', id, 'student_id', student_id, 'tranche_id', tranche_id, 'amount', amount, 'payment_method', payment_method, 'reference_number', reference_number, 'created_at', created_at), p_user
        FROM payment WHERE id = p_id;
    END IF;
END //

DROP PROCEDURE IF EXISTS sp_create_tranches_for_department//
CREATE PROCEDURE sp_create_tranches_for_department(IN p_department_id INT, IN p_year INT)
BEGIN
    DECLARE v_year INT DEFAULT p_year;
    IF v_year IS NULL OR v_year = 0 THEN SET v_year = YEAR(CURDATE()); END IF;
    INSERT INTO tranche (department_id, name, date_debut, date_fin, due_date, derogation_deadline, created_at) VALUES (p_department_id, 'Tranche 1 - 25%', MAKEDATE(v_year, 1), MAKEDATE(v_year, 100), MAKEDATE(v_year, 100), MAKEDATE(v_year, 84), NOW());
    INSERT INTO tranche (department_id, name, date_debut, date_fin, due_date, derogation_deadline, created_at) VALUES (p_department_id, 'Tranche 2 - 50%', MAKEDATE(v_year, 101), MAKEDATE(v_year, 181), MAKEDATE(v_year, 181), MAKEDATE(v_year, 166), NOW());
    INSERT INTO tranche (department_id, name, date_debut, date_fin, due_date, derogation_deadline, created_at) VALUES (p_department_id, 'Tranche 3 - 75%', MAKEDATE(v_year, 182), MAKEDATE(v_year, 258), MAKEDATE(v_year, 258), MAKEDATE(v_year, 243), NOW());
    INSERT INTO tranche (department_id, name, date_debut, date_fin, due_date, derogation_deadline, created_at) VALUES (p_department_id, 'Tranche 4 - 100%', MAKEDATE(v_year, 259), MAKEDATE(v_year, 319), MAKEDATE(v_year, 319), MAKEDATE(v_year, 314), NOW());
END //

DROP PROCEDURE IF EXISTS sp_insert_penalty//
CREATE PROCEDURE sp_insert_penalty(IN p_student_id INT, IN p_payment_id INT, IN p_days_late INT)
BEGIN
    DECLARE v_payment_amount DECIMAL(10,2);
    DECLARE v_penalty_percent DECIMAL(5,2);
    DECLARE v_penalty_amount DECIMAL(10,2);
    DECLARE v_tranche_id INT;
    DECLARE v_due_date DATE;
    DECLARE v_paid_date DATETIME;
    SELECT amount, tranche_id, created_at INTO v_payment_amount, v_tranche_id, v_paid_date FROM payment WHERE id = p_payment_id;
    SELECT due_date INTO v_due_date FROM tranche WHERE id = v_tranche_id;
    SET v_penalty_percent = fn_get_penalty_percentage(p_days_late);
    SET v_penalty_amount = (v_penalty_percent / 100) * v_payment_amount;
    INSERT INTO penalite (student_id, payment_id, tranche_id, retard_jours, pourcentage_penalite, montant_penalite, due_date, paid_date, created_at)
    VALUES (p_student_id, p_payment_id, v_tranche_id, p_days_late, v_penalty_percent, v_penalty_amount, v_due_date, v_paid_date, NOW());
END //

DROP PROCEDURE IF EXISTS sp_payment_create_full//
CREATE PROCEDURE sp_payment_create_full(IN p_matricule VARCHAR(50), IN p_amount DECIMAL(10,2), IN p_department_name VARCHAR(100), IN p_tranche_name VARCHAR(100), IN p_payment_method VARCHAR(20), IN p_reference_number VARCHAR(50))
BEGIN
    DECLARE v_student_id INT DEFAULT NULL;
    DECLARE v_tranche_id INT;
    IF p_matricule IS NOT NULL AND p_matricule <> '' THEN
        SELECT id INTO v_student_id FROM student WHERE matricule = p_matricule;
        IF v_student_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Étudiant introuvable.'; END IF;
    END IF;
    SELECT t.id INTO v_tranche_id FROM tranche t JOIN department d ON t.department_id = d.id WHERE d.name = p_department_name AND t.name = p_tranche_name;
    IF v_tranche_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tranche introuvable pour ce département.'; END IF;
    INSERT INTO payment (student_id, tranche_id, amount, payment_method, reference_number, created_at) VALUES (v_student_id, v_tranche_id, p_amount, p_payment_method, p_reference_number, NOW());
END //

DROP PROCEDURE IF EXISTS sp_restore_record//
CREATE PROCEDURE sp_restore_record(IN p_archive_id INT)
BEGIN
    UPDATE archives SET restored_at = NOW() WHERE id = p_archive_id;
END //

DROP PROCEDURE IF EXISTS sp_student_create//
CREATE PROCEDURE sp_student_create(IN p_name VARCHAR(255), IN p_age INT, IN p_department_name VARCHAR(100))
BEGIN
    DECLARE v_department_id INT;
    SELECT id INTO v_department_id FROM department WHERE name = p_department_name;
    IF v_department_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Département introuvable.'; END IF;
    INSERT INTO student (name, age, department_id, matricule) VALUES (p_name, p_age, v_department_id, NULL);
    UPDATE student SET matricule = CONCAT('S-', LPAD(LAST_INSERT_ID(), 3, '0')) WHERE id = LAST_INSERT_ID();
END //

DROP PROCEDURE IF EXISTS sp_student_delete_matricule//
CREATE PROCEDURE sp_student_delete_matricule(IN p_matricule VARCHAR(50))
BEGIN
    DELETE FROM student WHERE matricule = p_matricule;
END //

DROP PROCEDURE IF EXISTS sp_student_update//
CREATE PROCEDURE sp_student_update(IN p_old_matricule VARCHAR(50), IN p_new_matricule VARCHAR(50), IN p_name VARCHAR(255), IN p_age INT, IN p_department_name VARCHAR(100))
BEGIN
    DECLARE v_student_id INT;
    DECLARE v_dept_id INT;
    SELECT id INTO v_student_id FROM student WHERE matricule = p_old_matricule;
    IF v_student_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Étudiant introuvable.'; END IF;
    SELECT id INTO v_dept_id FROM department WHERE name = p_department_name;
    IF v_dept_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Département introuvable.'; END IF;
    UPDATE student SET matricule = p_new_matricule, name = p_name, age = p_age, department_id = v_dept_id WHERE id = v_student_id;
END //

DELIMITER ;
