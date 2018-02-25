FROM php
MAINTAINER Duhon <duhon@rambler.ru>

RUN apt-get update && apt-get install -y \
    libmcrypt-dev \
    zlib1g-dev \
    git \
    vim \
    openssh-server \
    libssl-dev \
    --no-install-recommends && rm -r /var/lib/apt/lists/*

RUN git clone https://github.com/duhon/xhgui.git /var/xhgui
RUN docker-php-ext-install mcrypt zip && pecl install mongodb && docker-php-ext-enable mongodb
WORKDIR /var/xhgui
RUN chmod 777 cache
RUN php install.php
COPY php.ini /usr/local/etc/php/conf.d/custom_php.ini

EXPOSE 80
CMD ["php", "-S", "0.0.0.0:80", "-t", "/var/xhgui/webroot"]
