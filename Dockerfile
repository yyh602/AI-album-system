# ------------------------------------------------------------------
# Phase 1: Build Stage
# 使用官方 PHP 8.1 FPM 映像檔作為基礎
# ------------------------------------------------------------------
FROM php:8.1-fpm AS build

# 設定非互動式安裝，避免安裝時需要使用者互動
ENV DEBIAN_FRONTEND=noninteractive

# 安裝系統依賴和您所需的套件
# apt-get update 用於更新套件列表
# apt-get install -y 用於安裝指定的套件
RUN apt-get update && apt-get install -y \
    # PHP 擴充功能依賴
    libmagickwand-dev \
    libjpeg-dev \
    libpng-dev \
    libzip-dev \
    libonig-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    # 專案套件
    exiftool \
    imagemagick \
    unzip \
    git \
    # insightface 依賴 (假設您的專案需要 python 相關功能)
    python3 \
    python3-pip \
    # 清理不必要的檔案，減少映像檔大小
    && rm -rf /var/lib/apt/lists/*

# 連結 python3 為 python，以防程式碼中使用 'python' 指令
RUN ln -s /usr/bin/python3 /usr/bin/python

# ------------------------------------------------------------------
# Phase 2: Install PHP Extensions
# 獨立安裝每個 PHP 擴充功能，避免因單一錯誤而中斷整個 RUN 指令
# ------------------------------------------------------------------
# 安裝 pdo_mysql, exif, imagick, opcache
RUN docker-php-ext-install -j$(nproc) pdo_mysql exif imagick opcache

# 設定 gd 擴充功能
# 這必須在 gd 安裝之前執行
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp

# 安裝 gd 和 zip
RUN docker-php-ext-install -j$(nproc) gd zip

# ------------------------------------------------------------------
# Phase 3: Application Setup
# 複製程式碼並安裝專案依賴
# ------------------------------------------------------------------
# 安裝 Composer (PHP 套件管理工具)
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# 將您的應用程式檔案複製到容器中的 /var/www/html
COPY . /var/www/html

# 設定工作目錄
WORKDIR /var/www/html

# 安裝 Python 依賴
# 假設您的 Python 依賴寫在 requirements.txt 檔案中
RUN if [ -f "requirements.txt" ]; then pip3 install -r requirements.txt; fi

# 安裝 PHP 專案依賴
RUN if [ -f "composer.json" ]; then composer install --no-dev --optimize-autoloader; fi

# 設定 PHP-FPM 的使用者和群組
# www-data 是 Linux 系統中用於 Web 伺服器的標準使用者
RUN chown -R www-data:www-data /var/www/html

# ------------------------------------------------------------------
# Phase 4: Final Stage
# 啟動服務
# ------------------------------------------------------------------
# 啟動 PHP-FPM 服務
CMD ["php-fpm"]
