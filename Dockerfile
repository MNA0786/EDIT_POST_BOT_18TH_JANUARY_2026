# Use official PHP Apache image
FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP curl extension (simpler method)
RUN apt-get update && apt-get install -y libcurl4-openssl-dev \
    && docker-php-ext-configure curl \
    && docker-php-ext-install curl

# Enable Apache modules
RUN a2enmod rewrite headers

# Copy Apache configuration
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Copy all application files
COPY . /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/data \
    && chmod -R 777 /var/www/html/data

# Create necessary files with proper permissions
RUN touch /var/www/html/error.log \
    && touch /var/www/html/data/error.log \
    && chmod 666 /var/www/html/error.log \
    && chmod 666 /var/www/html/data/error.log

# Expose port 80
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]
