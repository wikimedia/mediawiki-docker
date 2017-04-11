#!/bin/bash

set -e

# Some defaults
: ${MEDIAWIKI_SITE_NAME:=MediaWiki}
: ${MEDIAWIKI_SITE_LANG:=en}
: ${MEDIAWIKI_ADMIN_USER:=admin}
: ${MEDIAWIKI_ADMIN_PASS:=admin}
: ${MEDIAWIKI_DB_TYPE:=mysql}
: ${MEDIAWIKI_DB_PORT:=3306}
: ${MEDIAWIKI_DB_SCHEMA:=mediawiki}
: ${MEDIAWIKI_UPDATE:=false}
: ${MEDIAWIKI_RESTBASE_URL:=restbase-is-not-specified}

if [ -z "$MEDIAWIKI_DB_HOST" ]; then
	echo "No database host specified"
	echo "Add -e MEDIAWIKI_DB_HOST env variable"
	exit 1
fi

if [ -z "$MEDIAWIKI_DB_USER" ]; then
	echo "No database user specified."
	echo "Add -e MEDIAWIKI_DB_USER env variable"
	exit 1
fi

if [ -z "$MEDIAWIKI_DB_PASSWORD" ]; then
	echo "No database password specified."
	echo "Add -e MEDIAWIKI_DB_PASSWORD env variable"
	exit 1
fi

if [ "$MEDIAWIKI_DB_TYPE" != "mysql" ]; then
	echo "Only MySQL database typr is supported."
	exit 1
fi

# Checking for dependencies
yes | php /var/www/html/composer.phar --working-dir=/var/www/html --no-dev update

# Generate LocalSettings.php
php /var/www/html/maintenance/install.php \
	--confpath /var/www/html \
	--dbname "$MEDIAWIKI_DB_NAME" \
	--dbschema "$MEDIAWIKI_DB_SCHEMA" \
	--dbport "$MEDIAWIKI_DB_PORT" \
	--dbserver "$MEDIAWIKI_DB_HOST" \
	--dbtype "$MEDIAWIKI_DB_TYPE" \
	--dbuser "$MEDIAWIKI_DB_USER" \
	--dbpass "$MEDIAWIKI_DB_PASSWORD" \
	--installdbuser "$MEDIAWIKI_DB_USER" \
	--installdbpass "$MEDIAWIKI_DB_PASSWORD" \
	--server "$MEDIAWIKI_SITE_SERVER" \
	--scriptpath "" \
	--lang "$MEDIAWIKI_SITE_LANG" \
	--pass "$MEDIAWIKI_ADMIN_PASS" \
	"$MEDIAWIKI_SITE_NAME" \
	"$MEDIAWIKI_ADMIN_USER"

# Dirty but... good enough :D
sed '/?>/d' /var/www/html/LocalSettings.php && { cat /data/custom_settings.php; echo; echo "?>"; } >> /var/www/html/LocalSettings.php

# Check for some updates
php /var/www/html/maintenance/update.php --quick --conf /var/www/html/LocalSettings.php

# Fix file ownership and permissions
chown -R www-data:www-data /var/www/html /data

exec "$@"
