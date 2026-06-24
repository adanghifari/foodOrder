FROM php:8.4-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libzip-dev \
    libssl-dev \
    libsasl2-dev \
    pkg-config \
    zip

# Install PHP extensions
RUN docker-php-ext-install zip
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Increase PHP execution limits so Midtrans API calls don't cause 502
RUN echo "max_execution_time=120" >> /usr/local/etc/php/conf.d/custom.ini \
 && echo "default_socket_timeout=60" >> /usr/local/etc/php/conf.d/custom.ini \
 && echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/custom.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy project files
COPY . .

# Create required Laravel storage directories and set permissions
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views
RUN chmod -R 775 storage bootstrap/cache

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Expose port
EXPOSE 10000

# Start Laravel server (uses PORT from platform if provided)
CMD ["sh", "-c", "php artisan config:clear && php artisan storage:link || true && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]
