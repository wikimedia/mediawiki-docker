FROM debian:sid

ENV MEDIAWIKI_VERSION wmf/1.30.0-wmf.4

# XXX: Consider switching to nginx.
RUN set -x; \
    apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        apache2 \
        libapache2-mod-php7.1 \
        php7.1-mysql \
        php7.1-cli \
        php7.1-gd \
        php7.1-curl \
        php7.1-mbstring \
        php7.1-xml \
        imagemagick \
        netcat \
        git \
    && rm -rf /var/lib/apt/lists/* \
    && rm -rf /var/cache/apt/archives/* \
    && a2enmod rewrite \
    && a2enmod proxy \
    && a2enmod proxy_http \
    # Remove the default Debian index page.
    && rm /var/www/html/index.html


# MediaWiki setup
RUN set -x; \
    mkdir -p /usr/src \
    && git clone \
        --depth 1 \
        -b $MEDIAWIKI_VERSION \
        https://gerrit.wikimedia.org/r/p/mediawiki/core.git \
        /usr/src/mediawiki \
    && cd /usr/src/mediawiki \
    && git submodule update --init skins \
    && git submodule update --init vendor \
    && cd extensions \
    # Extensions
    # TODO: make submodules shallow clones?
    && git submodule update --init --recursive VisualEditor \
    && git submodule update --init --recursive Math \
    && git submodule update --init --recursive EventBus \
    && git submodule update --init --recursive Scribunto \
    && git submodule update --init --recursive ParserFunctions \
    && git submodule update --init --recursive SyntaxHighlight_GeSHi \
    && git submodule update --init --recursive Cite \
    && git submodule update --init --recursive Echo \
    && git submodule update --init --recursive Flow \
    && git submodule update --init --recursive PageImages \
    && git submodule update --init --recursive TextExtracts \
    && git submodule update --init --recursive MobileFrontend \
    && git submodule update --init --recursive TemplateData \
    && git submodule update --init --recursive ParserFunctions \
    && git submodule update --init --recursive Citoid


COPY php.ini /usr/local/etc/php/conf.d/mediawiki.ini

COPY apache/mediawiki.conf /etc/apache2/
RUN echo "Include /etc/apache2/mediawiki.conf" >> /etc/apache2/apache2.conf

COPY docker-entrypoint.sh /entrypoint.sh

EXPOSE 80 443
ENTRYPOINT ["/entrypoint.sh"]
CMD ["apachectl", "-e", "info", "-D", "FOREGROUND"]
