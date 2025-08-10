-- 修正資料庫結構
-- 為 uploads 表添加 album_id 欄位
ALTER TABLE uploads ADD COLUMN IF NOT EXISTS album_id INTEGER REFERENCES albums(id);

-- 為 photos 表添加 album_id 欄位
ALTER TABLE photos ADD COLUMN IF NOT EXISTS album_id INTEGER REFERENCES albums(id);

-- 檢查欄位是否添加成功
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'uploads' 
ORDER BY ordinal_position;

SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_name = 'photos' 
ORDER BY ordinal_position; 