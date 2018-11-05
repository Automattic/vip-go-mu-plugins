#!/bin/bash

WP_VERSION=${1-latest}

MYSQL_ROOT_PASSWORD='wordpress'
db=$(docker run -p 3306:3306 -e MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD -d mariadb)
function cleanup() {
	docker rm -f $db
}
trap cleanup EXIT

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
WP_VERSION=$($DIR/download-wp-tests.sh wordpress_test root "$MYSQL_ROOT_PASSWORD" "127.0.0.1" "$WP_VERSION")

## Ensure there's a database connection for the rest of the steps
until docker exec -it $db mysql -u root --password="wordpress" -e 'CREATE DATABASE wordpress_test' > /dev/null; do
	echo "Waiting for database connection..."
	sleep 5
done

docker run \
	-v $(pwd):/app \
	-v /tmp/wordpress-tests-lib-$WP_VERSION:/tmp/wordpress-tests-lib \
	-v /tmp/wordpress-$WP_VERSION:/tmp/wordpress \
	--network host \
	--rm phpunit/phpunit \
	--bootstrap /app/tests/bootstrap.php
