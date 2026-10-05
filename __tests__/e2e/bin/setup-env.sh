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

# Exercise the real import command with Jetpack disabled, before customer cleanup hooks.
vip dev-env exec --slug e2e-test-site --quiet -- wp config set VIP_JETPACK_SKIP_LOAD true --raw --add --type=constant
vip dev-env exec --slug e2e-test-site --quiet -- wp eval '
$names = [ "jetpack_options", "jetpack_private_options", "jetpack_secrets", "vaultpress", "wordpress_api_key", "vip_jetpack_connection_pilot_heartbeat" ];
foreach ( $names as $name ) { if ( ! update_option( $name, "e2e-import-credential" ) ) { WP_CLI::error( "Could not seed credential: " . $name ); } }
update_option( "e2e_unrelated_option", "keep" );
$hook_ran = false;
add_action( "vip_sqlimport_cleanup", function () use ( &$hook_ran ) {
    $hook_ran = true;
    if ( false !== get_option( "jetpack_private_options" ) ) { WP_CLI::error( "Credentials reached a customer cleanup hook." ); }
} );
WP_CLI::runcommand( "vip data-cleanup sql-import", [ "launch" => false ] );
if ( ! $hook_ran ) { WP_CLI::error( "Customer cleanup hook did not run." ); }
global $wpdb;
foreach ( $names as $name ) {
    if ( null !== $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM $wpdb->options WHERE option_name = %s", $name ) ) ) { WP_CLI::error( "Imported credential retained: " . $name ); }
}
if ( "keep" !== get_option( "e2e_unrelated_option" ) ) { WP_CLI::error( "Unrelated option removed." ); }
delete_option( "e2e_unrelated_option" );
'
vip dev-env exec --slug e2e-test-site --quiet -- wp config delete VIP_JETPACK_SKIP_LOAD --type=constant

# Enable the large media upload warning module and lower its threshold for e2e tests.
# --add is required for constants that don't yet exist in wp-config.php; without it,
# `wp config set` silently no-ops (especially when --quiet is set) and the constants
# never get defined, leaving the module disabled in tests.
vip dev-env exec --slug e2e-test-site --quiet -- wp config set VIP_LARGE_MEDIA_WARNING_ENABLED true --raw --add --type=constant
vip dev-env exec --slug e2e-test-site --quiet -- wp config set VIP_LARGE_MEDIA_WARNING_THRESHOLD_BYTES 524288 --raw --add --type=constant

# Diagnostic: print resolved constants so a future failure is obvious in CI logs.
vip dev-env exec --slug e2e-test-site --quiet -- wp eval '
echo "VIP_LARGE_MEDIA_WARNING_ENABLED=" . ( defined( "VIP_LARGE_MEDIA_WARNING_ENABLED" ) ? var_export( VIP_LARGE_MEDIA_WARNING_ENABLED, true ) : "UNDEFINED" ) . PHP_EOL;
echo "VIP_LARGE_MEDIA_WARNING_THRESHOLD_BYTES=" . ( defined( "VIP_LARGE_MEDIA_WARNING_THRESHOLD_BYTES" ) ? VIP_LARGE_MEDIA_WARNING_THRESHOLD_BYTES : "UNDEFINED" ) . PHP_EOL;
'

# Change admin password to "password"
vip dev-env exec --slug e2e-test-site --quiet -- wp user update vipgo --user_pass=password
