# 使用官方 PHP 8.1 FPM 映像檔作為基礎
FROM php:8.1-fpm

# 設定非互動式安裝，避免安裝時需要使用者互動
ENV DEBIAN_FRONTEND=noninteractive

# 安裝系統依賴和您所需的套件
# 包括 PHP 擴充功能所需的函式庫，以及 exiftool、ImageMagick
# 另外，為了 insightface，我們需要安裝 python3 和 pip
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
    python3 \
    python3-pip \
    && rm -rf /var/lib/apt/lists/*

# 連結 python3 為 python
RUN ln -s /usr/bin/python3 /usr/bin/python

# 安裝 PHP 擴充功能
# pdo_mysql: MySQL 資料庫支援
# exif: Exif 中繼資料支援
# imagick: ImageMagick 支援
# opcache: PHP 快取
RUN docker-php-ext-install -j$(nproc) pdo_mysql exif imagick opcache \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) gd zip

# 安裝 Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# 將應用程式檔案複製到容器中
COPY . /var/www/html

# 設定工作目錄
WORKDIR /var/www/html

# 安裝 Python 依賴
# 假設您的 Python 依賴寫在 requirements.txt 檔案中
# 如果沒有，請手動列出您需要的套件，例如: RUN pip3 install insightface
RUN if [ -f "requirements.txt" ]; then pip3 install -r requirements.txt; fi

# 安裝 PHP 專案依賴
RUN if [ -f "composer.json" ]; then composer install --no-dev --optimize-autoloader; fi

# 設定 PHP-FPM 的使用者和群組
RUN chown -R www-data:www-data /var/www/html

# 啟動 PHP-FPM 服務
CMD ["php-fpm"]
