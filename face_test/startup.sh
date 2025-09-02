#!/bin/bash

# Azure App Service Linux 啟動腳本
echo "Starting Azure App Service Linux..."

# 檢查 PHP 是否可用
if command -v php &> /dev/null; then
    echo "PHP is available: $(php -v | head -n1)"
else
    echo "PHP is not available"
fi

# 檢查 PHP-FPM 狀態
if systemctl is-active --quiet php8.1-fpm; then
    echo "PHP-FPM 8.1 is running"
elif systemctl is-active --quiet php-fpm; then
    echo "PHP-FPM is running"
else
    echo "PHP-FPM is not running, starting..."
    # 嘗試啟動 PHP-FPM
    if command -v php-fpm8.1 &> /dev/null; then
        php-fpm8.1 -D
    elif command -v php-fpm &> /dev/null; then
        php-fpm -D
    fi
fi

# 檢查 Python 是否可用
if command -v python3 &> /dev/null; then
    echo "Python3 is available: $(python3 --version)"
else
    echo "Python3 is not available"
fi

# 檢查必要目錄
echo "Checking directories..."
if [ -d "/home/site/wwwroot" ]; then
    echo "wwwroot directory exists"
    ls -la /home/site/wwwroot/
    
    # 檢查 face_test 目錄
    if [ -d "/home/site/wwwroot/face_test" ]; then
        echo "face_test directory exists"
        ls -la /home/site/wwwroot/face_test/
    else
        echo "face_test directory not found"
    fi
else
    echo "wwwroot directory not found"
fi

# 檢查 PHP 擴展
echo "Checking PHP extensions..."
php -m | grep -E "(gd|curl|exif|json|mbstring)"

# 檢查 nginx 配置
echo "Checking nginx configuration..."
if [ -f "/etc/nginx/nginx.conf" ]; then
    echo "nginx.conf exists"
    nginx -t
else
    echo "nginx.conf not found"
fi

# 檢查 PHP-FPM 配置
echo "Checking PHP-FPM configuration..."
if [ -f "/etc/php/8.1/fpm/php-fpm.conf" ]; then
    echo "PHP-FPM 8.1 config exists"
elif [ -f "/etc/php-fpm.conf" ]; then
    echo "PHP-FPM config exists"
else
    echo "PHP-FPM config not found"
fi

# 啟動必要的服務
echo "Starting services..."

# 啟動 PHP-FPM（如果沒有運行）
if ! pgrep -f "php-fpm" > /dev/null; then
    echo "Starting PHP-FPM..."
    if command -v php-fpm8.1 &> /dev/null; then
        php-fpm8.1 -D
    elif command -v php-fpm &> /dev/null; then
        php-fpm -D
    fi
fi

# 檢查 nginx 狀態
if ! pgrep -f "nginx" > /dev/null; then
    echo "Starting nginx..."
    nginx
fi

echo "Startup script completed. Keeping alive..."
tail -f /dev/null 