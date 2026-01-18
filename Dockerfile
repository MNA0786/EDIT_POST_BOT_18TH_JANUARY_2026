# Use official PHP image with Apache
FROM php:8.2-apache

# Enable cURL
RUN docker-php-ext-install curl

# Enable mod_rewrite
RUN a2enmod rewrite

# Copy project files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html/data
RUN chmod -R 777 /var/www/html/data

# Expose port
EXPOSE 80
