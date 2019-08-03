FROM php:7.3-apache

# System dependencies
RUN set -eux; \
	\
	apt-get update; \
	apt-get install -y --no-install-recommends \
		git \
		librsvg2-bin \
		imagemagick \
		# Required for SyntaxHighlighting
		python3 \
	; \
	rm -rf /var/lib/apt/lists/*

# Install the PHP extensions we need
RUN set -eux; \
	\
	savedAptMark="$(apt-mark showmanual)"; \
	\
	apt-get update; \
	apt-get install -y --no-install-recommends \
		libicu-dev \
	; \
	\
	docker-php-ext-install -j "$(nproc)" \
		intl \
		mbstring \
		mysqli \
		opcache \
	; \
	\
	pecl install apcu-5.1.17; \
	docker-php-ext-enable \
		apcu \
	; \
	\
	# reset apt-mark's "manual" list so that "purge --auto-remove" will remove all build dependencies
	apt-mark auto '.*' > /dev/null; \
	apt-mark manual $savedAptMark; \
	ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
		| awk '/=>/ { print $3 }' \
		| sort -u \
		| xargs -r dpkg-query -S \
		| cut -d: -f1 \
		| sort -u \
		| xargs -rt apt-mark manual; \
	\
	apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
	rm -rf /var/lib/apt/lists/*

# Enable Short URLs
RUN set -eux; \
	a2enmod rewrite; \
	{ \
		echo '<Directory /var/www/html>'; \
		echo '  RewriteEngine On'; \
		echo '  RewriteCond %{REQUEST_FILENAME} !-f'; \
		echo '  RewriteCond %{REQUEST_FILENAME} !-d'; \
		echo '  RewriteRule ^ %{DOCUMENT_ROOT}/index.php [L]'; \
		echo '</Directory>'; \
	} > "$APACHE_CONFDIR/conf-available/short-url.conf"; \
	a2enconf short-url

# set recommended PHP.ini settings
# see https://secure.php.net/manual/en/opcache.installation.php
RUN { \
		echo 'opcache.memory_consumption=128'; \
		echo 'opcache.interned_strings_buffer=8'; \
		echo 'opcache.max_accelerated_files=4000'; \
		echo 'opcache.revalidate_freq=60'; \
		echo 'opcache.fast_shutdown=1'; \
	} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# SQLite Directory Setup
RUN set -eux; \
	mkdir -p /var/www/data; \
	chown -R www-data:www-data /var/www/data

# Version
ENV MEDIAWIKI_MAJOR_VERSION 1.33
ENV MEDIAWIKI_BRANCH REL1_33
ENV MEDIAWIKI_VERSION 1.33.0
ENV MEDIAWIKI_SHA512 e31f5d8bd0bef39b9e2db71f129da128d20174f86e6a4799de5e24195bdcbbc06778b978a48073934b6e59d837629d6b83c182c8271b5fb944ef4ce5df856c68

# MediaWiki setup
RUN set -eux; \
	curl -fSL "https://releases.wikimedia.org/mediawiki/${MEDIAWIKI_MAJOR_VERSION}/mediawiki-${MEDIAWIKI_VERSION}.tar.gz" -o mediawiki.tar.gz; \
	echo "${MEDIAWIKI_SHA512} *mediawiki.tar.gz" | sha512sum -c -; \
	tar -x --strip-components=1 -f mediawiki.tar.gz; \
	rm mediawiki.tar.gz; \
	chown -R www-data:www-data extensions skins cache images
