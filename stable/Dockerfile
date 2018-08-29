FROM php:7.2-apache

# System Dependencies.
RUN apt-get update && apt-get install -y \
		git \
		imagemagick \
		libicu-dev \
		# Required for SyntaxHighlighting
		python3 \
	--no-install-recommends && rm -r /var/lib/apt/lists/*

# Install the PHP extensions we need
RUN docker-php-ext-install mbstring mysqli opcache intl

# Install the default object cache.
RUN pecl channel-update pecl.php.net \
	&& pecl install apcu \
	&& docker-php-ext-enable apcu

# set recommended PHP.ini settings
# see https://secure.php.net/manual/en/opcache.installation.php
RUN { \
		echo 'opcache.memory_consumption=128'; \
		echo 'opcache.interned_strings_buffer=8'; \
		echo 'opcache.max_accelerated_files=4000'; \
		echo 'opcache.revalidate_freq=60'; \
		echo 'opcache.fast_shutdown=1'; \
		echo 'opcache.enable_cli=1'; \
	} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# SQLite Directory Setup
RUN mkdir -p /var/www/data \
	&& chown -R www-data:www-data /var/www/data

# Version
ENV MEDIAWIKI_MAJOR_VERSION 1.31
ENV MEDIAWIKI_BRANCH REL1_31
ENV MEDIAWIKI_VERSION 1.31.0
ENV MEDIAWIKI_SHA512 50ad9303b0c0bd8380dea7489be18a4022d5b65a31961af8d36c3c9ff6d74cdf25e8e10137ef1e025b4287e9ee9b7e0bf4198ca342a46ab42915c91f1ddaf940

# enable RewriteMod to enable short url
RUN a2enmod rewrite

# MediaWiki setup
RUN curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz" -o mediawiki.tar.gz \
	&& echo "${MEDIAWIKI_SHA512} *mediawiki.tar.gz" | sha512sum -c - \
	&& tar -xz --strip-components=1 -f mediawiki.tar.gz \
	&& rm mediawiki.tar.gz \
	&& chown -R www-data:www-data extensions skins cache images
