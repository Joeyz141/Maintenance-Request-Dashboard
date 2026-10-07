# deploy/web/Dockerfile
# The PHP + Apache container: the same app you run in XAMPP, packed into an image.
# Build context = the project root (see compose.yaml), so paths below start there.

# Official image: Debian + Apache + PHP 8.2 (same PHP version as XAMPP 8.2.12).
FROM php:8.2-apache

# XAMPP has pdo_mysql built in; this image doesn't, so we compile it in.
RUN docker-php-ext-install pdo_mysql

# Production settings: start from PHP's recommended production php.ini,
# then add our own overrides (errors hidden from visitors, Secure cookie, ...).
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY deploy/web/php-production.ini "$PHP_INI_DIR/conf.d/zz-production.ini"

# Apache hardening: hide version numbers, block direct access to src/ and config/.
COPY deploy/web/apache-security.conf /etc/apache2/conf-enabled/zz-security.conf

# Copy ONLY the files the website needs. Not copied on purpose:
# database/ (SQL), tests/, reports/ (own container), LEARNING_NOTES.md, composer files, .git.
COPY index.php create.php edit.php /var/www/html/
COPY api/    /var/www/html/api/
COPY config/ /var/www/html/config/
COPY src/    /var/www/html/src/
COPY js/     /var/www/html/js/

# Apache listens on port 80 INSIDE the Docker network. Only Caddy talks to it.
EXPOSE 80
