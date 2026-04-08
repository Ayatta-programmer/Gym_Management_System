# Use official PHP with Apache
FROM php:8.2-apache

# Install PostgreSQL dependencies
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Enable Apache mod_rewrite (optional but recommended)
RUN a2enmod rewrite

# Copy all project files into container
COPY . /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80