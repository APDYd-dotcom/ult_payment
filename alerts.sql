USE ult_payment;

CREATE TABLE IF NOT EXISTS alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT DEFAULT NULL,
    source_key VARCHAR(120) DEFAULT NULL,
    type VARCHAR(60) NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    severity ENUM('info', 'warning', 'important', 'danger') NOT NULL DEFAULT 'info',
    link VARCHAR(255) DEFAULT NULL,
    is_resolved TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME DEFAULT NULL,
    CONSTRAINT fk_alerts_user
        FOREIGN KEY (user_id) REFERENCES user(userId)
        ON DELETE CASCADE
);

CREATE INDEX idx_alerts_user ON alerts (user_id);
CREATE INDEX idx_alerts_type ON alerts (type);
CREATE INDEX idx_alerts_resolved ON alerts (is_resolved);
CREATE INDEX idx_alerts_user_type_resolved ON alerts (user_id, type, is_resolved);
CREATE INDEX idx_alerts_source ON alerts (source_key);
