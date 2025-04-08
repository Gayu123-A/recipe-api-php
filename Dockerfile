#FROM quay.io/hellofresh/php70:7.1
FROM php:7.4-apache

# Adds nginx configurations
ADD ./docker/nginx/default.conf   /etc/nginx/sites-available/default

# Environment variables to PHP-FPM
# RUN sed -i -e "s/;clear_env\s*=\s*no/clear_env = no/g" /etc/php/7.4/fpm/pool.d/www.conf

# Install pdo_mysql extension
RUN docker-php-ext-install pdo pdo_mysql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Enable mod_rewrite
RUN a2enmod rewrite

# Set apps home directory.
ENV APP_DIR /var/www/html

# Adds the application code to the image
ADD . ${APP_DIR}

# Define current working directory.
WORKDIR ${APP_DIR}

# Cleanup
RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

EXPOSE 80
