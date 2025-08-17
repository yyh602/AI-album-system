-- 建立相簿系統所需的資料表

-- 1. 建立 albums 資料表
CREATE TABLE IF NOT EXISTS albums (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    cover_photo VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_created_at (created_at)
);

-- 2. 建立 photos 資料表
CREATE TABLE IF NOT EXISTS photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    album_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255),
    file_size INT,
    mime_type VARCHAR(100),
    width INT,
    height INT,
    datetime DATETIME,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    location VARCHAR(255),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE,
    INDEX idx_album_id (album_id),
    INDEX idx_datetime (datetime),
    INDEX idx_location (location)
);

-- 3. 建立 uploads 資料表（如果不存在）
CREATE TABLE IF NOT EXISTS uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    path VARCHAR(500) NOT NULL,
    original_name VARCHAR(255),
    file_size INT,
    mime_type VARCHAR(100),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_uploaded_at (uploaded_at)
);

-- 4. 檢查資料表是否建立成功
SHOW TABLES LIKE 'albums';
SHOW TABLES LIKE 'photos';
SHOW TABLES LIKE 'uploads';

-- 5. 顯示資料表結構
DESCRIBE albums;
DESCRIBE photos;
DESCRIBE uploads;
