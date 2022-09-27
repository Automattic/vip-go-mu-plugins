#!/bin/bash

version=latest
pluginPath="./"
clientCodePath=image

while getopts v:p:c: flag
do
    case "${flag}" in
        v) version=${OPTARG};;
        p) pluginPath=${OPTARG};;
        c) clientCodePath=${OPTARG};;
    esac
done

# Destroy existing test site
vip dev-env destroy --slug=e2e-test-site

# Create and run test site
vip --slug=e2e-test-site dev-env create --title="E2E Testing site" --mu-plugins="${pluginPath}" --wordpress="5.9" --multisite=false --client-code="${clientCodePath}"
vip --slug=e2e-test-site dev-env start

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
"
