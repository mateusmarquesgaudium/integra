FROM ghcr.io/mateusmarquesgaudium/mchlogtoolkit-php:1.0 AS mchlogtoolkit-php

FROM php:8.2-apache

WORKDIR /mnt/efs/

RUN a2enmod rewrite

RUN mkdir -p /applog/mchlog/
RUN mkdir -p /applog/mch/
RUN chown -R www-data:www-data /applog/

RUN apt-get update && apt-get install -y \
    curl \
    unzip \
    git \
    zip \
    sudo \
    gettext \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY . .

COPY --from=mchlogtoolkit-php / /mnt/efs/www/mchlogtoolkit/
RUN cd /mnt/efs/www/mchlogtoolkit/ && composer install

RUN cd /mnt/efs/www/src && composer install

RUN chmod -R u+x /mnt/efs/scripts/cron/*

RUN rm /mnt/efs/www/config/custom.php

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
    
ENTRYPOINT ["/entrypoint.sh"]