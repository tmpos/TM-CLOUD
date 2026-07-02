<<<<<<< HEAD
FROM php:8.4-apache

# --------------------------------------------------
# Instalar dependencias del sistema
# --------------------------------------------------
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    libzip-dev \
    libpq-dev \
    libsqlite3-dev \
    sqlite3 \
    && docker-php-ext-install \
        pdo \
        pdo_pgsql \
        pdo_sqlite \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# --------------------------------------------------
# Habilitar mod_rewrite
# --------------------------------------------------
RUN a2enmod rewrite

# --------------------------------------------------
# Instalar Composer
# --------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --------------------------------------------------
# Directorio de trabajo
# --------------------------------------------------
WORKDIR /var/www/html

# --------------------------------------------------
# Copiar composer primero para aprovechar la cache
# --------------------------------------------------
COPY composer.json composer.lock* ./

RUN composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

# --------------------------------------------------
# Copiar el resto del proyecto
# --------------------------------------------------
COPY . .

# --------------------------------------------------
# Si composer cambió después del COPY
# --------------------------------------------------
RUN composer dump-autoload --optimize

# --------------------------------------------------
# Configurar Apache para servir /public
# --------------------------------------------------
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/*.conf

RUN sed -ri -e 's!/var/www/!/var/www/html/public!g' \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# --------------------------------------------------
# Permisos
# --------------------------------------------------
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# --------------------------------------------------
# Puerto
# --------------------------------------------------
EXPOSE 80

# --------------------------------------------------
# Iniciar Apache
# --------------------------------------------------
CMD ["apache2-foreground"]
=======
FROM php:8.4-apache

# Instalar dependencias
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libpq-dev \
    procps \
    && docker-php-ext-install pdo pdo_pgsql

# Activar mod_rewrite
RUN a2enmod rewrite

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Carpeta de trabajo
WORKDIR /var/www/html

# Copiar proyecto
COPY . .

# Instalar dependencias PHP
RUN composer install --no-dev --optimize-autoloader

# Hacer que Apache sirva la carpeta public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' \
    /etc/apache2/sites-available/*.conf

RUN sed -ri -e 's!/var/www/!/var/www/html/public!g' \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

# Realtime WebSocket server entrypoint
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80 8080 8081

ENTRYPOINT ["docker-entrypoint.sh"]
>>>>>>> 43f301d (Add realtime WebSocket system (Supabase-like) with Ratchet)
