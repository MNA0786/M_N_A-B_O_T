FROM php:8.1-apache

# Enable Apache modules
RUN a2enmod rewrite headers

# Install dependencies
RUN apt-get update && apt-get install -y \
    curl \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install zip

# Copy application
WORKDIR /var/www/html
COPY . .

# Create required files with proper permissions
RUN mkdir -p backups \
    && touch movies.csv users.json bot_stats.json movie_requests.json user_settings.json delete_queue.json filter_sessions.json bot_activity.log \
    && chmod 666 movies.csv users.json bot_stats.json movie_requests.json user_settings.json delete_queue.json filter_sessions.json bot_activity.log \
    && chmod 777 backups

# PHP error logging enable karo
RUN echo "error_log = /dev/stderr" >> /usr/local/etc/php/conf.d/error-logging.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/error-logging.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/error-logging.ini

# Composer install (if needed)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN if [ -f "composer.json" ]; then composer install --no-dev --optimize-autoloader || true; fi

EXPOSE 80
CMD ["apache2-foreground"]
