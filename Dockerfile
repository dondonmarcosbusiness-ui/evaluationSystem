FROM php:8.2-cli AS base

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpng-dev libonig-dev libxml2-dev \
    libzip-dev libicu-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

FROM base AS build

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

RUN rm -rf node_modules

FROM base AS production

COPY --from=build /app /app

RUN mkdir -p storage/framework/{sessions,views,cache,testing} storage/logs bootstrap/cache \
    && chmod -R a+rw storage bootstrap/cache

EXPOSE 8000

COPY start.sh /app/start.sh
RUN chmod +x /app/start.sh

CMD ["/app/start.sh"]
