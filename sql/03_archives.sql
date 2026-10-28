CREATE TABLE IF NOT EXISTS archives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(50) NOT NULL,
    record_id INT NOT NULL,
    data JSON NOT NULL,
    archived_by INT NULL,
    archived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    restored_at DATETIME NULL,
    INDEX idx_table_record (table_name, record_id)
);

DELIMITER //

CREATE OR REPLACE PROCEDURE sp_archive_record(IN p_table VARCHAR(50), IN p_id INT, IN p_user INT)
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

CREATE OR REPLACE PROCEDURE sp_restore_record(IN p_archive_id INT)
BEGIN
    UPDATE archives SET restored_at = NOW() WHERE id = p_archive_id;
END //
DELIMITER ;
