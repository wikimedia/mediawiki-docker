FROM debian:jessie

ENV MEDIAWIKI_VERSION 1.29.0
ENV MEDIAWIKI_SHA512 3b77d04f13488b2a602d9030a4833c881188c93e2708ea7941d9e82f7a7215f3028695f396914da09ae1288cb9e980cc04f78559edaff6559e3d50b0c47afeb3
ENV MEDIAWIKI_MAJOR_VERSION 1.29

RUN set -ex \
    && apt-get update \
    && mediawikiDeps=' \
        ca-certificates \
        apache2 \
        libapache2-mod-php5 \
        php5-mysql \
        php5-cli \
        php5-gd \
        php5-curl \
        php-net-smtp \
        php-auth-sasl \
        php-mail \
        php-net-socket \
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
