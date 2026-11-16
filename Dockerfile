FROM php:8.2-apache

RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql gd \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html

ENV PORT=80
RUN sed -i 's|Alias /icons/|# Alias /icons/|g' /etc/apache2/mods-available/alias.conf \
    && printf 'Listen ${PORT}\n' > /etc/apache2/ports.conf \
    && printf '<VirtualHost *:${PORT}>\n\tDocumentRoot /var/www/html\n\t<Directory /var/www/html>\n\t\tAllowOverride All\n\t\tRequire all granted\n\t</Directory>\n\tErrorLog ${APACHE_LOG_DIR}/error.log\n\tCustomLog ${APACHE_LOG_DIR}/access.log combined\n</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf
