#!/bin/sh

cp -aR "/mu-plugins" "/wordpress/wordpress-core-${WP_VERSION}/src/wp-content/mu-plugins"
echo "define( 'VIP_JETPACK_SKIP_LOAD', 'true' );" >> "/wordpress/wordpress-core-${WP_VERSION}/src/wp-content/mu-plugins/000-vip-init.php"
