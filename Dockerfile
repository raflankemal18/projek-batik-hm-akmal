FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    libzip-dev \
    postgresql-dev \
    build-base \
    autoconf \
    openssl-dev \
    icu-dev \
    sqlite-dev \
    nodejs \
    npm

# Install PHP extensions (sockets & database drivers)
RUN docker-php-ext-install \
    pdo_mysql \
    zip \
    exif \
    pcntl \
    bcmath \
    gd \
    intl \
    pdo_pgsql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install frontend + backend dependencies and build assets
# (skipped when the bind-mounted dev volume already has them; the
# entrypoint re-runs this at container start if the folders are missing)
RUN npm install && npm run build \
    && composer install --no-dev --optimize-autoloader

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose PHP-FPM port (consumed by the nginx container via fastcgi_pass)
EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
