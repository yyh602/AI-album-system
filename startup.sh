#!/bin/bash

# Azure App Service 啟動指令碼
# 安裝 PostgreSQL 擴展

echo "開始安裝 PostgreSQL 擴展..."

# 更新套件列表
apt-get update

# 安裝 PostgreSQL 開發套件
apt-get install -y libpq-dev

# 安裝 PHP PostgreSQL 擴展
docker-php-ext-install pdo_pgsql pgsql

# 重新啟動 Apache
service apache2 restart

echo "PostgreSQL 擴展安裝完成！"

# 啟動 Apache
apache2-foreground 