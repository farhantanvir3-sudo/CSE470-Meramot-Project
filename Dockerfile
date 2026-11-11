# ==============================================================================
# Stage 1: Build Frontend Tailwind CSS Bundle
# ==============================================================================
FROM node:20-alpine AS frontend-builder

WORKDIR /app/frontend
COPY frontend/package*.json ./
RUN npm install

COPY frontend/ ./
COPY backend/ ../backend/
RUN npm run build:css

# ==============================================================================
# Stage 2: Production PHP 8.2 + Apache Web Server (Laravel 10 MVC + Blade)
# ==============================================================================
FROM php:8.2-apache-bookworm

# Install system libraries & PostgreSQL PDO extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/meramot

# Copy separated frontend/ and backend/ directories preserving relative paths
COPY frontend/ ./frontend/
COPY --from=frontend-builder /app/frontend/public/dist/styles.css ./frontend/public/dist/styles.css
COPY backend/ ./backend/

# Install PHP dependencies in backend/
WORKDIR /var/www/meramot/backend
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs bootstrap/cache \
    && chown -R www-data:www-data /var/www/meramot/backend/storage /var/www/meramot/backend/bootstrap/cache

# Configure Apache DocumentRoot to /var/www/meramot/backend/public
ENV APACHE_DOCUMENT_ROOT=/var/www/meramot/backend/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && printf "<Directory /var/www/meramot/backend/public>\n    Options Indexes FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>\n" > /etc/apache2/conf-enabled/meramot.conf

COPY docker/start.sh /usr/local/bin/start.sh
RUN sed -i 's/\r$//' /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

EXPOSE 8000

CMD ["/usr/local/bin/start.sh"]
