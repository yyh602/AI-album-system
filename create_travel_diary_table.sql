-- 建立 travel_diary 資料表
CREATE TABLE IF NOT EXISTS travel_diary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    album_id INT,
    album_name VARCHAR(255),
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_album_id (album_id),
    INDEX idx_created_at (created_at)
);

-- 檢查資料表是否建立成功
DESCRIBE travel_diary;
