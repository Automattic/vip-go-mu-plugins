#!/bin/sh

set -ex

basedir="${0%/*}/.."

E2E_SITE_SLUG=${E2E_SITE_SLUG:-e2e-$(date +%s)-$$}
export E2E_SITE_SLUG

version=${WORDPRESS_VERSION:-latest}
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

if [ "${version}" = "latest" ]; then
    WPVER="$(wget https://github.com/Automattic/vip-container-images/raw/refs/heads/master/wordpress/versions.json -O - | jq -r '[.[] | select(.prerelease == false)] | max_by(.tag) | .tag')"
else
    WPVER="$(wget https://github.com/Automattic/vip-container-images/raw/refs/heads/master/wordpress/versions.json -O - | jq -r --arg ref_value "${version}" '.[] | select(.ref == $ref_value) | .tag')"
fi

if [ -z "${WPVER}" ]; then
    WPVER=trunk
fi

echo "Creating E2E site with WordPress version ${WPVER} (requested: ${version})"

# Create and run test site
vip --slug="${E2E_SITE_SLUG}" dev-env create --title="E2E Testing site" --mu-plugins="${pluginPath}" --mailpit false --wordpress="${WPVER}" --multisite=false --app-code="${clientCodePath}" --php 8.2 --xdebug false --phpmyadmin false --elasticsearch true < /dev/null
if [ -n "${E2E_SITE_CREATED_MARKER:-}" ]; then
    : > "${E2E_SITE_CREATED_MARKER}"
fi
vip dev-env start --slug "${E2E_SITE_SLUG}" --skip-wp-versions-check
vip dev-env shell --root --slug "${E2E_SITE_SLUG}" -- chown -R www-data:www-data /wp/wp-content/plugins
if [ "${WPVER}" = 'trunk' ]; then
    vip dev-env exec --slug "${E2E_SITE_SLUG}" --quiet -- wp core update --force --version="${version}"
    vip dev-env exec --slug "${E2E_SITE_SLUG}" --quiet -- wp core update-db
fi

# Every `vip dev-env exec` costs a few seconds, so commands that can share a WP-CLI process run in one.
# The import check below needs a new process, started after VIP_JETPACK_SKIP_LOAD is set, so that
# Jetpack is not loaded during the import cleanup.
vip dev-env exec --slug "${E2E_SITE_SLUG}" --quiet -- wp eval '
WP_CLI::runcommand( "plugin install --activate classic-editor", [ "launch" => false ] );
WP_CLI::runcommand( "rewrite structure /%postname%/", [ "launch" => false ] );
WP_CLI::runcommand( "config set VIP_JETPACK_SKIP_LOAD true --raw --add --type=constant", [ "launch" => false ] );
'

# Exercise the real import command with Jetpack disabled, before customer cleanup hooks. Then restore
# Jetpack, set up the large media upload warning and the admin password for the tests.
#
# --add is required for constants that don't yet exist in wp-config.php; without it,
# `wp config set` silently no-ops and the constants never get defined, leaving the large media
# upload warning disabled in tests. `config get` prints each constant and fails setup if one is missing.
vip dev-env exec --slug "${E2E_SITE_SLUG}" --quiet -- wp eval '
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

WP_CLI::runcommand( "config delete VIP_JETPACK_SKIP_LOAD --type=constant", [ "launch" => false ] );
WP_CLI::runcommand( "config set VIP_LARGE_MEDIA_WARNING_ENABLED true --raw --add --type=constant", [ "launch" => false ] );
WP_CLI::runcommand( "config set VIP_LARGE_MEDIA_WARNING_THRESHOLD_BYTES 524288 --raw --add --type=constant", [ "launch" => false ] );
// `config get` loads wp-config.php, which this process already has, so it runs in its own process.
foreach ( [ "VIP_LARGE_MEDIA_WARNING_ENABLED", "VIP_LARGE_MEDIA_WARNING_THRESHOLD_BYTES" ] as $constant ) {
    echo $constant . "=" . WP_CLI::runcommand( "config get " . $constant . " --type=constant", [ "launch" => true, "return" => true ] ) . PHP_EOL;
}

WP_CLI::runcommand( "user update vipgo --user_pass=password", [ "launch" => false ] );
'
