FROM php:8.2-apache

# ===== System dependencies (IMPORTANT for curl) =====
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    && docker-php-ext-install curl \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# ===== Apache config =====
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# ===== Workdir =====
WORKDIR /var/www/html

# ===== Copy project =====
COPY . /var/www/html

# ===== Permissions for JSON storage =====
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data \
    && chmod -R 777 /var/www/html/data

# ===== Expose port =====
EXPOSE 80
