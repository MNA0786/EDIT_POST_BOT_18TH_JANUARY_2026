# Use official PHP image with Apache
FROM php:8.2-apache

# Enable required extensions
RUN apt-get update && apt-get install -y libcurl4-openssl-dev pkg-config unzip && \
    docker-php-ext-install curl

# Enable mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy files
COPY . .

# Create data folder and set permissions
RUN mkdir -p data && chown -R www-data:www-data data && chmod -R 777 data

# Expose default port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
