#!/bin/bash
set -euo pipefail

cd "$(dirname "$(readlink -f "$BASH_SOURCE")")"

mediawikiReleases=( "$@" )
if [ ${#mediawikiReleases[@]} -eq 0 ]; then
	mediawikiReleases=( 1.*/ )
fi
mediawikiReleases=( "${mediawikiReleases[@]%/}" )

declare -A phpVersion=(
	[1.31]='7.3'
	[default]='7.4'
)

declare -A peclVersions=(
	[APCu]="5.1.20"
)

function mediawiki_version() {
	git ls-remote --sort=version:refname --tags https://github.com/wikimedia/mediawiki.git \
		| cut -d/ -f3 \
		| tr -d '^{}' \
		| grep -E "^$1" \
		| tail -1
}

declare -A variantExtras=(
	[apache]='\n# Enable Short URLs\nRUN set -eux; \\\n\ta2enmod rewrite; \\\n\t{ \\\n\t\techo \"<Directory /var/www/html>\"; \\\n\t\techo \"  RewriteEngine On\"; \\\n\t\techo \"  RewriteCond %{REQUEST_FILENAME} !-f\"; \\\n\t\techo \"  RewriteCond %{REQUEST_FILENAME} !-d\"; \\\n\t\techo \"  RewriteRule ^ %{DOCUMENT_ROOT}/index.php [L]\"; \\\n\t\techo \"</Directory>\"; \\\n\t} > \"$APACHE_CONFDIR/conf-available/short-url.conf\"; \\\n\ta2enconf short-url\n\n# Enable AllowEncodedSlashes for VisualEditor\nRUN sed -i \"s/<\\/VirtualHost>/\\tAllowEncodedSlashes NoDecode\\n<\\/VirtualHost>/\" \"$APACHE_CONFDIR/sites-available/000-default.conf\"'
	[fpm]=''
	[fpm-alpine]=''
)
declare -A variantCmds=(
	[apache]='apache2-foreground'
	[fpm]='php-fpm'
	[fpm-alpine]='php-fpm'
)
declare -A variantBases=(
	[apache]='debian'
	[fpm]='debian'
	[fpm-alpine]='alpine'
)

for mediawikiRelease in "${mediawikiReleases[@]}"; do
	mediawikiReleaseDir="$mediawikiRelease"
	mediawikiVersion="$(mediawiki_version $mediawikiRelease)"
	phpVersion=${phpVersion[$mediawikiRelease]-${phpVersion[default]}}

	for variant in apache fpm fpm-alpine; do
		dir="$mediawikiReleaseDir/$variant"
		mkdir -p "$dir"

		extras="${variantExtras[$variant]}"
		cmd="${variantCmds[$variant]}"
		base="${variantBases[$variant]}"

		case "$mediawikiRelease" in
			1.31 )
				extras=""
				;;
		esac

		sed -r \
			-e 's!%%MEDIAWIKI_VERSION%%!'"$mediawikiVersion"'!g' \
			-e 's!%%MEDIAWIKI_MAJOR_VERSION%%!'"$mediawikiRelease"'!g' \
			-e 's!%%PHP_VERSION%%!'"$phpVersion"'!g' \
			-e 's!%%VARIANT%%!'"$variant"'!g' \
			-e 's!%%APCU_VERSION%%!'"${peclVersions[APCu]}"'!g' \
			-e 's@%%VARIANT_EXTRAS%%@'"$extras"'@g' \
			-e 's!%%CMD%%!'"$cmd"'!g' \
			"Dockerfile-${base}.template" > "$dir/Dockerfile"
	done
done
