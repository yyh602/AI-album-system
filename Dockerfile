# 使用官方 PHP 8.1 FPM 映像檔作為基礎
FROM php:8.1-fpm

# 安裝系統依賴和您所需的套件
# apt-get update 用於更新套件列表
# apt-get install -y 用於安裝指定的套件
# libmagickwand-dev: ImageMagick 開發函式庫
# libjpeg-dev: JPEG 支援
# libpng-dev: PNG 支援
# libzip-dev: ZIP 壓縮支援
# unzip: 解壓縮工具
# git: 用於 clone 專案
# exiftool: 您指定的套件
# imagemagick: 您指定的套件
# libonig-dev: PCRE 函式庫
# libfreetype6-dev: 字型函式庫
# libjpeg62-turbo-dev: JPEG 函式庫
# libpng-dev: PNG 函式庫
# libwebp-dev: WebP 函式庫
RUN apt-get update && apt-get install -y \
    libmagickwand-dev \
    libjpeg-dev \
    libpng-dev \
    libzip-dev \
    unzip \
    git \
    exiftool \
    imagemagick \
    libonig-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    && rm -rf /var/lib/apt/lists/*

# 安裝 PHP 擴充功能
# docker-php-ext-install: 安裝 PHP 內建擴充功能
# gd: GD 圖形函式庫
# zip: ZIP 函式庫
# pdo_mysql: MySQL 資料庫支援
# exif: Exif 中繼資料支援
# imagick: ImageMagick 支援
# opcache: PHP 快取
RUN docker-php-ext-install -j$(nproc) gd zip pdo_mysql exif imagick opcache \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp

# 安裝 Composer (PHP 套件管理工具)
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# 將您的應用程式檔案複製到容器中的 /var/www/html
# COPY . /var/www/html/
# 建議使用 .dockerignore 排除不必要的檔案
COPY . /var/www/html

# 設定工作目錄
WORKDIR /var/www/html

# 安裝您的專案依賴 (如果有的話)
RUN if [ -f "composer.json" ]; then composer install --no-dev --optimize-autoloader; fi

# 設定 PHP-FPM 的使用者和群組
# www-data 是 Linux 系統中用於 Web 伺服器的標準使用者
RUN chown -R www-data:www-data /var/www/html

# 啟動 PHP-FPM 服務
CMD ["php-fpm"]
