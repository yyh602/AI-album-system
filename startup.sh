#!/bin/bash

# Azure App Service 啟動腳本
echo "Starting AI Album System..."

# 檢查必要檔案
if [ ! -f "save_album_blob.php" ]; then
    echo "ERROR: save_album_blob.php not found!"
    ls -la *.php
    exit 1
fi

# 檢查 PHP 擴展
php -m | grep -E "(exif|imagick|curl|mysqli)"

# 啟動 PHP-FPM
php-fpm -F
