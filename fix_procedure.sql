SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
DELIMITER //

DROP PROCEDURE IF EXISTS sp_payment_create_full//
CREATE PROCEDURE sp_payment_create_full(
    IN p_matricule VARCHAR(50), 
    IN p_amount DECIMAL(10,2), 
    IN p_department_name VARCHAR(100), 
    IN p_tranche_name VARCHAR(100), 
    IN p_payment_method VARCHAR(20), 
    IN p_reference_number VARCHAR(50)
)
BEGIN
    DECLARE v_student_id INT DEFAULT NULL;
    DECLARE v_tranche_id INT;
    
    IF p_matricule IS NOT NULL AND p_matricule <> '' THEN
        SELECT id INTO v_student_id 
        FROM student 
        WHERE matricule = p_matricule COLLATE utf8mb4_unicode_ci;
        
        IF v_student_id IS NULL THEN 
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Étudiant introuvable.'; 
        END IF;
    END IF;
    
    SELECT t.id INTO v_tranche_id 
    FROM tranche t 
    JOIN department d ON t.department_id = d.id 
    WHERE d.name = p_department_name COLLATE utf8mb4_unicode_ci 
      AND t.name = p_tranche_name COLLATE utf8mb4_unicode_ci;
      
    IF v_tranche_id IS NULL THEN 
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tranche introuvable pour ce département.'; 
    END IF;
    
    INSERT INTO payment (student_id, tranche_id, amount, payment_method, reference_number, created_at) 
    VALUES (v_student_id, v_tranche_id, p_amount, p_payment_method, p_reference_number, NOW());
END //

DELIMITER ;
