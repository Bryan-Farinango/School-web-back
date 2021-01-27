FROM php:7.3-fpm-alpine

RUN docker-php-ext-install pdo pdo_mysql

RUN apk --update add --virtual build-dependencies build-base openssl-dev autoconf \
  && pecl install mongodb \
  && docker-php-ext-enable mongodb \
  && apk del build-dependencies build-base openssl-dev autoconf \
  && rm -rf /var/cache/apk/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# composer require mikehaertl/php-pdftk barryvdh/laravel-dompdf jenssegers/mongodb