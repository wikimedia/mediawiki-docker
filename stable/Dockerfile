FROM php:7.1-apache

# System Dependencies.
RUN apt-get update && apt-get install -y \
		git libpg-dev \
		imagemagick \
		libicu-dev \
	--no-install-recommends && rm -r /var/lib/apt/lists/*

# Install the PHP extensions we need
RUN docker-php-ext-install mbstring mysqli opcache intl pgsql

# Install the default object cache.
RUN pecl channel-update pecl.php.net \
	&& pecl install apcu-5.1.8 \
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
ENV MEDIAWIKI_MAJOR_VERSION 1.29
ENV MEDIAWIKI_BRANCH REL1_29
ENV MEDIAWIKI_VERSION 1.29.1
ENV MEDIAWIKI_SHA512 c4e04c4fb665c3d8299f3e03e608904aaf0e06381240c7259813eb670c3e32cde919353dd19993250cf49be81d604ac5f6d468bc563116a4b268e5011d34119f

# MediaWiki setup
RUN curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz" -o mediawiki.tar.gz \
	&& echo "${MEDIAWIKI_SHA512} *mediawiki.tar.gz" | sha512sum -c - \
	&& tar -xz --strip-components=1 -f mediawiki.tar.gz \
	&& rm mediawiki.tar.gz \
	&& chown -R www-data:www-data extensions skins cache images
