#!/bin/bash
sed -i -e "s#/w/api.php#${MEDIAWIKI_API_ENDPOINT}#" -e "s#http://localhost#${MEDIAWIKI_URL}#g" /etc/mediawiki/parsoid/config.yaml
exec $@
