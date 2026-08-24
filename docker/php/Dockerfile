FROM php:8.3-apache

RUN a2enmod rewrite \
    && docker-php-ext-install pdo pdo_mysql \
    && printf '%s\n' \
        '<Directory /var/www/html>' \
        '    Options -Indexes +FollowSymLinks' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/pastorxbox.conf \
    && a2enconf pastorxbox

WORKDIR /var/www/html
