DELIMITER $$

DROP TRIGGER IF EXISTS generate_matricule_after_insert$$

CREATE TRIGGER generate_matricule_after_insert
AFTER INSERT ON `user`
FOR EACH ROW
BEGIN
    DECLARE new_matricule VARCHAR(50);
    DECLARE prefix VARCHAR(2);
    
    IF NEW.role = 'student' THEN
        SET prefix = 'S';
    ELSE
        SET prefix = 'A';
    END IF;
    
    SET new_matricule = CONCAT(prefix, '-', LPAD(NEW.userId, 3, '0'));
    
    UPDATE `user` 
    SET matricule = new_matricule 
    WHERE userId = NEW.userId;
END$$

DELIMITER ;

-- Libérer les anciens matricules pour éviter les conflits
UPDATE `user` SET matricule = NULL;

-- Régénérer tous les matricules
UPDATE `user` 
SET matricule = CONCAT(
    IF(role = 'student', 'S', 'A'), 
    '-', 
    LPAD(userId, 3, '0')
);
