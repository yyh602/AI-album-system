#!/bin/bash
# Azure App Service 啟動腳本

# 設定 Nginx client_max_body_size
echo "設定 Nginx 上傳限制..."

# 建立自定義 nginx 配置（如果可能）
if [ -d "/etc/nginx" ]; then
    echo "client_max_body_size 100M;" > /tmp/nginx_upload.conf
    echo "proxy_read_timeout 300;" >> /tmp/nginx_upload.conf
    echo "proxy_connect_timeout 300;" >> /tmp/nginx_upload.conf
    echo "proxy_send_timeout 300;" >> /tmp/nginx_upload.conf
fi

# 顯示目前 PHP 設定
echo "目前 PHP 設定："
php -i | grep -E "(upload_max_filesize|post_max_size|max_execution_time|memory_limit)"

echo "啟動腳本完成"