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

if [ "${version}" = "latest" ]; then
    WPVER="$(wget https://github.com/Automattic/vip-container-images/raw/refs/heads/master/wordpress/versions.json -O - | jq -r '[.[] | select(.prerelease == false)] | max_by(.tag) | .tag')"
else
    WPVER="$(wget https://github.com/Automattic/vip-container-images/raw/refs/heads/master/wordpress/versions.json -O - | jq -r --arg ref_value "${version}" '.[] | select(.ref == $ref_value) | .tag')"
fi

if [ -z "${WPVER}" ]; then
    WPVER=trunk
fi

# Destroy existing test site
vip dev-env destroy --slug=e2e-test-site || true

# Create and run test site
vip --slug=e2e-test-site dev-env create --title="E2E Testing site" --mu-plugins="${pluginPath}" --mailpit false --wordpress="${WPVER}" --multisite=false --app-code="${clientCodePath}" --php 8.2 --xdebug false --phpmyadmin false --elasticsearch true < /dev/null
vip dev-env start --slug e2e-test-site --skip-wp-versions-check
vip dev-env shell --root --slug e2e-test-site -- chown -R www-data:www-data /wp/wp-content/plugins
vip dev-env exec --slug e2e-test-site --quiet -- wp plugin install --activate classic-editor
if [ "${WPVER}" = 'trunk' ]; then
    vip dev-env exec --slug e2e-test-site --quiet -- wp core update --force --version="${version}"
    vip dev-env exec --slug e2e-test-site --quiet -- wp core update-db
fi
vip dev-env exec --slug e2e-test-site --quiet -- wp rewrite structure '/%postname%/'
