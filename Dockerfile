FROM php:8.2-apache

# Install required system packages for PHP extensions
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    git \
    unzip \
    && docker-php-ext-install curl

# Enable mod_rewrite
RUN a2enmod rewrite

# Copy project files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html/data
RUN chmod -R 777 /var/www/html/data

EXPOSE 80
