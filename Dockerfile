FROM php:8.4-apache

RUN docker-php-ext-install pdo_mysql \
 && a2dismod -f alias \
 && a2dismod -f autoindex \
 && a2enmod remoteip headers rewrite \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf 'expose_php=Off\n' > "$PHP_INI_DIR/conf.d/zz-hardening.ini" \
 && printf 'ServerTokens Prod\nServerSignature Off\nRemoteIPHeader X-Forwarded-For\nRemoteIPInternalProxy 172.16.0.0/12\nRemoteIPInternalProxy 127.0.0.1\n' > /etc/apache2/conf-available/zz-local.conf \
 && a2enconf zz-local

# Note: Since docker-compose mounts ./public directly to /var/www/html, 
# you can omit the COPY instruction for local development, but keeping it 
# doesn't hurt for standalone image builds.
COPY --chown=www-data:www-data public/ /var/www/html/

