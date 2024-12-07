# Use an official PHP image with required extensions
FROM php:8.1-apache

# Install Node.js and npm for frontend dependencies
RUN curl -fsSL https://deb.nodesource.com/setup_16.x | bash -
RUN apt-get update && apt-get install -y nodejs

# Install additional PHP extensions for Laravel
RUN docker-php-ext-install pdo pdo_mysql

# Set working directory
WORKDIR /var/www/html

# Copy the project files
COPY . .

# Install PHP dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --optimize-autoloader --no-dev

# Install and build Node.js dependencies
RUN npm install
RUN npm run build

# Set permissions for storage and cache
RUN chmod -R 777 storage bootstrap/cache

# Expose port 80 for the web server
EXPOSE 80

# Start the Apache server
CMD ["apache2-foreground"]
