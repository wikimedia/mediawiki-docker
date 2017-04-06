FROM debian:sid

ENV MEDIAWIKI_VERSION wmf/1.29.0-wmf.18

RUN set -ex \
    && apt-get update \
    && mediawikiDeps=' \
        ca-certificates \
        apache2 \
        libapache2-mod-php \
        php-mysql \
        php-cli \
        php-gd \
        php-curl \
        php7.0-mbstring \
        php7.0-xml \
        imagemagick \
        netcat \
        git \
        curl \
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

RUN mkdir -p /data && chown www-data:www-data /data
VOLUME /data

# MediaWiki setup
RUN set -x; \
    rm -rf /var/www/html || true \
    && git clone \
        --depth 1 \
        -b $MEDIAWIKI_VERSION \
        https://github.com/wikimedia/mediawiki.git \
        /var/www/html \
    && cd /var/www/html \
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
