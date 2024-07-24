#!/bin/sh

echo "Installing WP-CLI in $1"

./bin/search/wp-env-cli $1 curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
./bin/search/wp-env-cli $1 chmod +x wp-cli.phar
./bin/search/wp-env-cli $1 mv -f wp-cli.phar /usr/local/bin/wp
