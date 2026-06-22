# Stage 1: Build frontend assets
FROM node:20-alpine AS frontend-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# Stage 2: Final PHP application
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

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy project files
COPY . .

# Copy built assets from Stage 1
COPY --from=frontend-builder /app/public/build ./public/build

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Expose port
EXPOSE 10000

# Start Laravel server (uses PORT from platform if provided)
CMD ["sh", "-c", "php artisan config:clear && php artisan storage:link || true && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"]
