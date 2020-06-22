FROM php:7.2-apache

ENV MEDIAWIKI_VERSION 1.34.1
ENV MEDIAWIKI_SHA512 3a03ac696e2d5300faba0819ba0d876a21798c8dcdc64cc2792c6db0aa81d4feaced8dc133b6ca3e476c770bf51516b0a624cb336784ae3d2b51c8c0aa5987a0
ENV MEDIAWIKI_MAJOR_VERSION 1.34

RUN set -ex \
    && apt-get update \
    && apt-get -y install libicu-dev apt-transport-https ca-certificates wget libpng-dev \ 
    && mediawikiDeps=' \
        ca-certificates \
        imagemagick \
        netcat \
        git \
        curl \
        unzip \
	  '  \
    && apt-get install -y $mediawikiDeps --no-install-recommends \
    && rm -rf /var/lib/apt/lists/* \
    && rm -rf /var/cache/apt/archives/* \
    && a2enmod rewrite \
    && a2enmod proxy \
    && a2enmod proxy_http \
    && a2dissite 000-default \
    && a2disconf other-vhosts-access-log \
    # Remove the default Debian index page.
    && rm -rf /var/www/html/*

RUN docker-php-ext-install gd intl mysqli mbstring opcache \
    && pecl install apcu-5.1.16 \
    && docker-php-ext-enable apcu

RUN mkdir -p /data && chown www-data:www-data /data
VOLUME /data

# MediaWiki setup
RUN set -x; \
    rm -rf /var/www/html/* || true \
    && cd /var/www/html \
    && curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz" -o mediawiki.tar.gz \
    && echo "${MEDIAWIKI_SHA512} *mediawiki.tar.gz" | sha512sum -c - \
    && tar -xz --strip-components=1 -f mediawiki.tar.gz \
    && rm -f mediawiki.tar.gz \
    && for d in images extensions skins vendor; do rm -rf $d && ln -s /data/$d; done \
    && curl -sS https://getcomposer.org/installer | php

COPY php.ini /usr/local/etc/php/conf.d/mediawiki.ini
COPY docker-entrypoint.sh /entrypoint.sh
RUN echo "Include /etc/apache2/mediawiki.conf" >> /etc/apache2/apache2.conf
COPY apache/mediawiki.conf /etc/apache2/

# Make apache docker friendly :)
RUN set -ex \
  # disable binding for default virtualhost on *:80 and *:443
  && echo "" > /etc/apache2/ports.conf \
  # apache logs to stdout/stderr
  && ln -sf /proc/self/fd/1 /var/log/apache2/access.log \
  && ln -sf /proc/self/fd/2 /var/log/apache2/error.log

EXPOSE 8080
ENTRYPOINT ["/entrypoint.sh"]
CMD ["apachectl", "-e", "info", "-D", "FOREGROUND"]
