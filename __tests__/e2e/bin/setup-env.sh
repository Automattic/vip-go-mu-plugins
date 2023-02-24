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
vip --slug=e2e-test-site dev-env create --title="E2E Testing site" --mu-plugins="${pluginPath}" --mailhog false --wordpress=trunk --multisite=false --app-code="${clientCodePath}" --php 8.0 --xdebug false --phpmyadmin false --elasticsearch true
vip dev-env start --slug e2e-test-site --skip-wp-versions-check

# Install classic editor plugin
# Install specified version of WordPress
# Create index
docker exec e2etestsite_php_1 sh -c "\
    wp plugin install --activate --allow-root classic-editor; \
    wp core update --allow-root --version=\"${version}\" --force; \
    chown -R www-data:www-data /wp
"
