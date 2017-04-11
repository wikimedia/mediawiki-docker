FROM debian:jessie

RUN set -x; \
    apt-get update -qq \
    && apt-get install -y apt-transport-https curl \
    && rm -rf /var/lib/apt/lists/*

RUN apt-key advanced --keyserver keys.gnupg.net --recv-keys 90E9F83F22250DD7 \
    && echo "deb https://releases.wikimedia.org/debian jessie-mediawiki main" > /etc/apt/sources.list.d/parsoid.list


RUN apt-get update \
    && apt-get install -y --no-install-recommends parsoid \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /etc/mediawiki/parsoid

EXPOSE 8000

COPY docker-entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/nodejs", "/usr/lib/parsoid/src/bin/server.js", "-c", "/etc/mediawiki/parsoid/config.yaml", "-n", "0"]
