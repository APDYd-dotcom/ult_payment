ALTER TABLE user
    ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER created_at,
    ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(64) NULL AFTER two_factor_enabled,
    ADD COLUMN IF NOT EXISTS two_factor_backup_codes JSON NULL AFTER two_factor_secret,
    ADD COLUMN IF NOT EXISTS two_factor_confirmed_at DATETIME NULL AFTER two_factor_backup_codes;
