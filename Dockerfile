FROM php:5.4-apache
# Install needed extensions
# TODO: Need to find out which extensions are needed.
RUN apt-get update \
  && apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libpng12-dev libmcrypt-dev \
  && docker-php-ext-install pdo_mysql mysqli mbstring gd iconv mcrypt bcmath

RUN apt-get install -y curl git unzip zip npm

RUN npm install -g uglifycss

# Activating rewrite
RUN a2enmod rewrite

RUN service apache2 restart

ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf
RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copy files needed for composer
COPY composer.json ./

# Install compose and run compose install
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN composer install --no-scripts --no-autoloader

# Copy needed files over
COPY . /var/www/html

COPY ./docker/inc.innstillinger_local.php /var/www/html/app

RUN composer dump-autoload --optimize

RUN mkdir public/imgs/profilbilder && mkdir app/gamelogs

RUN chown www-data:www-data app/gamelogs
RUN chown www-data:www-data public/imgs/profilbilder

RUN php app/scripts/assetic_dump.php

EXPOSE 80

# By default start up apache in the foreground, override with /bin/bash for interative.
CMD /usr/sbin/apache2ctl -D FOREGROUND