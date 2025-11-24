# Development image with PHP 8.4 + extensions + Composer
# Used for running tests and composer commands

FROM php:8.4-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libsodium-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    bcmath \
    sockets \
    pcntl \
    zip \
    sodium

# Install Redis extension via PECL
RUN pecl install redis igbinary \
    && docker-php-ext-enable redis igbinary

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure PHP
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/memory.ini \
    && echo "opcache.enable_cli = 1" > /usr/local/etc/php/conf.d/opcache.ini

# Set working directory
WORKDIR /app

# Verify installation
RUN php -v && \
    php -m | grep -E "(pgsql|redis|opcache)" && \
    composer --version

CMD ["php", "-a"]
