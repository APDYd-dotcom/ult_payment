CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    category VARCHAR(50) NULL DEFAULT 'theme',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_settings_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value, category)
VALUES
    ('primary_color', '#1e3a8a', 'theme'),
    ('secondary_color', '#2563eb', 'theme'),
    ('background_color', '#f4f6f9', 'theme'),
    ('font_family', '''Segoe UI'', sans-serif', 'theme'),
    ('logo_url', '', 'theme'),
    ('favicon_url', '', 'theme'),
    ('theme_name', 'ULT Payment', 'theme')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
