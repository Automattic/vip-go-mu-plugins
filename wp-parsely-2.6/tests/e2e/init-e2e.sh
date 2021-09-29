#!/bin/bash

set -e

until mysql -u wordpress -h db --password="wordpress" -e 'USE wordpress;'; do
	>&2 echo "Waiting for database connection..."
	sleep 1
done

>&2 echo "mysqld is up!"

if [[ "$1" == "reset" ]]; then
	>&2 echo "Resetting the WordPress database"
	wp --yes db reset
fi

>&2 echo "Configuring the WordPress install"

wp core install --url="http://localhost:8889" --title="wp-parsely e2e test" --admin_user="admin" --admin_password="password" --admin_email="nobody@example.com"
