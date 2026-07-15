FROM php:8.2-apache

# pdo_mysql — this app talks to MySQL exclusively via PDO (see core/Database.php)
RUN docker-php-ext-install pdo_mysql

# The app's own .htaccess (at the project root) handles all routing —
# AllowOverride must be enabled or every request 404s regardless of the
# rewrite rules being correct.
RUN a2enmod rewrite \
    && sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Project root doubles as the web root (see .htaccess / README for why —
# this app has no separate public/ document root, everything needed is
# served directly from here with .htaccess denying access to nothing
# sensitive since there are no secrets committed to the repo; DB
# credentials come from environment variables, not a checked-in file).
WORKDIR /var/www/html
COPY . /var/www/html

# uploads/ and uploads/logs/ must be writable by the web server user
RUN mkdir -p /var/www/html/uploads/logs \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/storage

EXPOSE 80
