CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    document_id INT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pair (sender_id, receiver_id),
    CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id) REFERENCES user(userId) ON DELETE CASCADE,
    CONSTRAINT fk_msg_receiver FOREIGN KEY (receiver_id) REFERENCES user(userId) ON DELETE CASCADE,
    CONSTRAINT fk_msg_doc FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commentable_type ENUM('payment','student','penalite') NOT NULL,
    commentable_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type_id (commentable_type, commentable_id),
    CONSTRAINT fk_comment_user FOREIGN KEY (user_id) REFERENCES user(userId) ON DELETE CASCADE
);
