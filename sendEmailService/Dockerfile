# --- 1. Stage 'base' ---
# Contient les dépendances PHP nécessaires pour tous les stages
FROM php:8.2-fpm-alpine as base
# On ajoute supervisor et nginx à l'image de base
RUN apk add --no-cache supervisor nginx
WORKDIR /var/www

# --- 2. Stage 'vendor' ---
# Installe les dépendances Composer
FROM base as vendor
RUN apk add --no-cache git curl unzip
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY database/ composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader

# --- 3. Stage 'app' (Image finale) ---
FROM base as app
# Installer uniquement les extensions PHP requises pour l'exécution
RUN apk add --no-cache libpng-dev oniguruma-dev libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Copier les dépendances depuis le stage 'vendor'
COPY --from=vendor /var/www/vendor/ ./vendor/
# Copier le code de l'application
COPY . .

# Copier les fichiers de configuration pour Nginx et Supervisor
COPY nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf
COPY supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Définir les permissions
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Exposer le port 80 (Nginx) et lancer Supervisor
EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]