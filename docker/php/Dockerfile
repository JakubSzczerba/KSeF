FROM php:8.5-fpm

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash -

RUN apt-get update  \
    && apt-get install -y libpq-dev libpng-dev libzip-dev libxml2-dev zip nodejs \
    && docker-php-ext-install zip pgsql pdo_pgsql sysvsem gd bcmath soap \
    && apt-get clean \
    && curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer

RUN pecl install xdebug && docker-php-ext-enable xdebug

RUN echo "openssl_conf = openssl_init" >> /etc/ssl/openssl.cnf && \
    echo "" >> /etc/ssl/openssl.cnf && \
    echo "[openssl_init]" >> /etc/ssl/openssl.cnf && \
    echo "providers = provider_sect" >> /etc/ssl/openssl.cnf && \
    echo "" >> /etc/ssl/openssl.cnf && \
    echo "[provider_sect]" >> /etc/ssl/openssl.cnf && \
    echo "default = default_sect" >> /etc/ssl/openssl.cnf && \
    echo "legacy = legacy_sect" >> /etc/ssl/openssl.cnf && \
    echo "" >> /etc/ssl/openssl.cnf && \
    echo "[default_sect]" >> /etc/ssl/openssl.cnf && \
    echo "activate = 1" >> /etc/ssl/openssl.cnf && \
    echo "" >> /etc/ssl/openssl.cnf && \
    echo "[legacy_sect]" >> /etc/ssl/openssl.cnf && \
    echo "activate = 1" >> /etc/ssl/openssl.cnf

COPY xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini

WORKDIR /var/www/ksef
