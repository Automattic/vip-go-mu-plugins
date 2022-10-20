#!/bin/sh

set -ex

basedir="${0%/*}/.."

version=latest
pluginPath="${basedir}/../../"
clientCodePath=demo

while getopts v:p:c: flag
do
    case "${flag}" in
        v) version=${OPTARG};;
        p) pluginPath=${OPTARG};;
        c) clientCodePath=${OPTARG};;
        *) echo "WARNING: Unexpected option ${flag}";;
    esac
done

if [ -z "${version}" ]; then
    version=${WORDPRESS_VERSION:-latest}
fi

# Destroy existing test site
vip dev-env destroy --slug=e2e-test-site || true

# Create and run test site
vip --slug=e2e-test-site dev-env create --title="E2E Testing site" --mu-plugins="${pluginPath}" --wordpress="5.9" --multisite=false --app-code="${clientCodePath}" --php 8.0 --xdebug false --phpmyadmin false --elasticsearch 7.17.2
vip dev-env start --slug e2e-test-site --skip-wp-versions-check

# Enable Enterprise Search
docker exec e2etestsite_wordpress_1 sh -c '\
    echo "define( \"VIP_ENABLE_VIP_SEARCH\", true );" | tee -a /app/config/wp-config.php; \
    echo "define( \"VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION\", true );" | tee -a /app/config/wp-config.php; \
'

# Install classic editor plugin
# Install specified version of WordPress
# Create index
docker exec e2etestsite_php_1 sh -c "\
    wp plugin install --activate --allow-root classic-editor; \
    wp core update --allow-root --version=\"${version}\" --force; \
    wp --allow-root vip-search index --setup --skip-confirm; \
    chown -R www-data:www-data /wp
"
