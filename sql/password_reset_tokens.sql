-- Script pour créer la table password_reset_tokens
-- Compatible avec MySQL/MariaDB (InnoDB, utf8mb4)
-- 
-- NOTE POUR INFINITYFREE :
-- Si la contrainte FOREIGN KEY échoue sur InfinityFree (certaines configurations limitent les FKs),
-- utilisez la version alternative sans contrainte ci-dessous :
-- 
-- CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
--   `id` INT AUTO_INCREMENT PRIMARY KEY,
--   `user_id` INT NOT NULL,
--   `token` VARCHAR(64) NOT NULL,
--   `expires_at` DATETIME NOT NULL,
--   `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
--   `used_at` DATETIME NULL DEFAULT NULL
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- CREATE INDEX idx_password_reset_user_id ON password_reset_tokens(user_id);
-- CREATE INDEX idx_password_reset_token ON password_reset_tokens(token);

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `used_at` DATETIME NULL DEFAULT NULL,
  CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `user`(`userId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX `idx_password_reset_user_id` ON `password_reset_tokens`(`user_id`);
CREATE INDEX `idx_password_reset_token` ON `password_reset_tokens`(`token`);
