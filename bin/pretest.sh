#!/bin/sh

if php -r 'exit(PHP_VERSION_ID < 80100);'; then
	# This is executed if the PHP version is GREATER THAN OR EQUALS TO 8.1
	# because `true` evaluates to `1` and `if` is executed if the exit code is `0`.
	echo "Disabling WP_DEBUG in wp-test-config.php"
	sed -i "s@define( 'WP_DEBUG', true );@// define( 'WP_DEBUG', true );@" /tmp/wordpress-tests-lib/wp-tests-config.php
fi
