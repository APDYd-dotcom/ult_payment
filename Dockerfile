FROM php:8.4-apache

# Activer le module de réécriture d'Apache
RUN a2enmod rewrite

# Installer toutes les dépendances système nécessaires
RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libxml2-dev \
    libonig-dev \
    libicu-dev \
    default-mysql-client \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Configurer et installer les extensions PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j1 pdo_mysql zip gd mbstring bcmath intl opcache

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Définir le répertoire de travail
WORKDIR /var/www/html

# Configuration PHP pour la production : on n'affiche pas les erreurs, mais on les log
RUN echo "display_errors = Off" > /usr/local/etc/php/conf.d/docker-php-ext-error.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/docker-php-ext-error.ini \
    && echo "error_reporting = E_ALL & ~E_DEPRECATED" >> /usr/local/etc/php/conf.d/docker-php-ext-error.ini

# Copier le code source
COPY . /var/www/html

# Installer les dépendances Composer
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Créer les dossiers nécessaires et définir les permissions
RUN mkdir -p /var/www/html/uploads /var/www/html/backups /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/uploads /var/www/html/backups /var/www/html/storage
