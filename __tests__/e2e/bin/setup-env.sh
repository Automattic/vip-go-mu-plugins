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
vip --slug=e2e-test-site dev-env create --title="E2E Testing site" --mu-plugins="${pluginPath}" --mailpit false --wordpress=trunk --multisite=false --app-code="${clientCodePath}" --php 8.0 --xdebug false --phpmyadmin false --elasticsearch true < /dev/null
vip dev-env start --slug e2e-test-site --skip-wp-versions-check
vip dev-env shell --root --slug e2e-test-site -- chown -R www-data:www-data /wp
vip dev-env exec --slug e2e-test-site --quiet -- wp plugin install --activate classic-editor
vip dev-env exec --slug e2e-test-site --quiet -- wp core update --force --version="${version}"
vip dev-env exec --slug e2e-test-site --quiet -- wp core update-db
vip dev-env exec --slug e2e-test-site --quiet -- wp rewrite structure '/%postname%/'
