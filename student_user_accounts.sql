USE ult_payment;

ALTER TABLE user
    ADD COLUMN IF NOT EXISTS matricule VARCHAR(50) DEFAULT NULL
        COMMENT 'Matricule de connexion pour les comptes etudiants'
        AFTER email,
    ADD UNIQUE INDEX IF NOT EXISTS idx_user_matricule (matricule);

UPDATE user u
JOIN vw_students_with_department s
    ON LOWER(TRIM(CONVERT(s.student_name USING utf8mb4)) COLLATE utf8mb4_unicode_ci)
        = LOWER(TRIM(CONVERT(u.fullname USING utf8mb4)) COLLATE utf8mb4_unicode_ci)
SET u.matricule = s.matricule
WHERE u.role = 'student'
  AND (u.matricule IS NULL OR u.matricule = '');
