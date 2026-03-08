FROM php:8.1-apache

LABEL maintainer="Mahatab Ansari <admin@entertainmenttadka.com>"
LABEL description="Entertainment Tadka Telegram Movie Bot"

# System dependencies install karo
RUN apt-get update && apt-get install -y \
    git \
    curl \
    wget \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && docker-php-ext-enable mbstring exif

# Composer install karo
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Working directory set karo
WORKDIR /var/www/html

# Apache configuration - modules enable karo
RUN a2enmod rewrite headers expires

# PHP configuration
RUN echo "upload_max_filesize = 50M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini

# Port expose karo
EXPOSE 80

# Application copy karo
COPY . .

# File permissions set karo
RUN mkdir -p backups logs \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 666 movies.csv users.json bot_stats.json movie_requests.json user_settings.json delete_queue.json filter_sessions.json bot_activity.log 2>/dev/null || true \
    && chmod 777 backups logs

# Composer install (agar hai to)
RUN if [ -f "composer.json" ]; then composer install --no-dev --optimize-autoloader || true; fi

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

CMD ["apache2-foreground"]