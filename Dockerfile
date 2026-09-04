# ============================================================================
# KIVARA API - Dockerfile
# Laravel / PHP 8.3 / Octane / Swoole
# ============================================================================

FROM php:8.3-fpm-alpine AS builder

# ============================================================================
# Dependências de compilação
# ============================================================================

RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    zlib-dev \
    linux-headers \
    autoconf \
    gcc \
    g++ \
    make \
    curl \
    git \
    unzip \
    pkgconf

# ============================================================================
# Extensões PHP
# ============================================================================

RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    zip \
    bcmath \
    sockets

# ============================================================================
# Redis
# ============================================================================

RUN pecl install redis \
    && docker-php-ext-enable redis

# ============================================================================
# Swoole com Brotli desabilitado
# ============================================================================

RUN pecl install -D 'enable-brotli="no"' swoole \
    && docker-php-ext-enable swoole

# ============================================================================
# Composer
# ============================================================================

RUN curl -sS https://getcomposer.org/installer \
    | php -- \
        --install-dir=/usr/local/bin \
        --filename=composer

WORKDIR /app

COPY composer.json composer.lock* ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY . .

RUN composer dump-autoload --optimize


# ============================================================================
# STAGE 2 - Runtime
# ============================================================================

FROM php:8.3-fpm-alpine AS runtime

RUN apk add --no-cache \
    libpq \
    libzip \
    zlib \
    curl \
    libstdc++ \
    netcat-openbsd \
    bash

# Extensões PHP compiladas
COPY --from=builder /usr/local/lib/php/extensions/ \
    /usr/local/lib/php/extensions/

# Configurações PHP
COPY --from=builder /usr/local/etc/php/conf.d/ \
    /usr/local/etc/php/conf.d/

WORKDIR /app

# Aplicação
COPY --from=builder /app /app

# Script de inicialização
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN php artisan package:discover --ansi

# ============================================================================
# Utilizador da aplicação
# ============================================================================

RUN addgroup -S appgroup \
    && adduser -S appuser -G appgroup

# ============================================================================
# Diretórios Laravel
# ============================================================================

RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache

# ============================================================================
# Permissões
# ============================================================================

RUN chown -R appuser:appgroup /app \
    && chmod -R 775 storage bootstrap/cache

USER appuser

EXPOSE 8000

# ============================================================================
# Entrypoint com Migrations automáticas
# ============================================================================

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
