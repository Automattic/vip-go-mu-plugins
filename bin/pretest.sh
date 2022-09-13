#!/bin/sh

sed -i "s@define( 'WP_DEBUG', true );@// define( 'WP_DEBUG', true );@" /tmp/wordpress-tests-lib/wp-tests-config.php
