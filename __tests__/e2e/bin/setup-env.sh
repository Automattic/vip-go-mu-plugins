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

# Install the Parse.ly consent enabling mu-plugin. The repo root is bind-mounted as
# WPMU_PLUGIN_DIR (--mu-plugins below), so a root-level PHP file is auto-loaded by WordPress.
# A dedicated, gitignored filename is used so this can never clobber dev-env-plugin.php, which
# `vip dev-env create` generates and owns. The fixture points the tracker at a same-origin stub
# URL, so no spec ever reaches cdn.parsely.com or beacons production p1.parsely.com.
cp "${basedir}/fixtures/e2e-parsely-consent.php" "${pluginPath}/e2e-parsely-consent.php"

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

# Enable the large media upload warning module and lower its threshold for e2e tests.
# --add is required for constants that don't yet exist in wp-config.php; without it,
# `wp config set` silently no-ops (especially when --quiet is set) and the constants
# never get defined, leaving the module disabled in tests.
vip dev-env exec --slug e2e-test-site --quiet -- wp config set VIP_LARGE_MEDIA_WARNING_ENABLED true --raw --add --type=constant
vip dev-env exec --slug e2e-test-site --quiet -- wp config set VIP_LARGE_MEDIA_WARNING_THRESHOLD_BYTES 524288 --raw --add --type=constant

# Diagnostic: print resolved constants so a future failure is obvious in CI logs.
vip dev-env exec --slug e2e-test-site --quiet -- wp eval 'echo "VIP_LARGE_MEDIA_WARNING_ENABLED=" . ( defined( "VIP_LARGE_MEDIA_WARNING_ENABLED" ) ? var_export( VIP_LARGE_MEDIA_WARNING_ENABLED, true ) : "UNDEFINED" ) . PHP_EOL;'
vip dev-env exec --slug e2e-test-site --quiet -- wp eval 'echo "VIP_LARGE_MEDIA_WARNING_THRESHOLD_BYTES=" . ( defined( "VIP_LARGE_MEDIA_WARNING_THRESHOLD_BYTES" ) ? VIP_LARGE_MEDIA_WARNING_THRESHOLD_BYTES : "UNDEFINED" ) . PHP_EOL;'

# Diagnostic: the Parse.ly consent module is enabled by the mu-plugin copied in above (not by
# `wp config set`), so prove it resolved. If this prints UNDEFINED, parsely-consent.spec.ts will
# fail with "tracker not enqueued" rather than anything useful.
vip dev-env exec --slug e2e-test-site --quiet -- wp eval 'echo "VIP_PARSELY_CONSENT_TRACKING_ENABLED=" . ( defined( "VIP_PARSELY_CONSENT_TRACKING_ENABLED" ) ? var_export( VIP_PARSELY_CONSENT_TRACKING_ENABLED, true ) : "UNDEFINED" ) . PHP_EOL;'

# Change admin password to "password"
vip dev-env exec --slug e2e-test-site --quiet -- wp user update vipgo --user_pass=password
