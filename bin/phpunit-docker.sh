#!/bin/bash

# Note: you can pass in additional phpunit args
# Test a specific file: ./bin/phpunit-docker.sh tests/path/to/test.php
# Stop on failures: ./bin/phpunit-docker.sh --stop-on-failure
# etc.

WP_VERSION=${2-latest}
WP_MULTISITE=${3-0}

echo "--------------"
echo "Will test with WP_VERSION=$WP_VERSION and WP_MULTISITE=$WP_MULTISITE"
echo "--------------"
echo

MARIADB_VERSION="10.3"
UUID=`date +%s000`
NETWORK_NAME="tests-$UUID"
DB_CONTAINER_NAME="db-$UUID"
MYSQL_ROOT_PASSWORD='wordpress'

docker network create $NETWORK_NAME

db=$(docker run --network $NETWORK_NAME --name $DB_CONTAINER_NAME -e MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD -d mariadb/server:$MARIADB_VERSION)
function cleanup() {
	echo "cleanup!"
	docker rm -f $db
	docker network rm $NETWORK_NAME
}
trap cleanup EXIT

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
WP_VERSION=$($DIR/download-wp-tests.sh wordpress_test root "$MYSQL_ROOT_PASSWORD" "$DB_CONTAINER_NAME" "$WP_VERSION")

## Ensure there's a database connection for the rest of the steps
until docker exec -it $db mysql -u root -h $DB_CONTAINER_NAME --password="wordpress" -e 'CREATE DATABASE wordpress_test' > /dev/null; do
	echo "Waiting for database connection..."
	sleep 5
done

docker run \
	--network $NETWORK_NAME \
	-e WP_MULTISITE="$WP_MULTISITE" \
	-v $(pwd):/app \
	-v /tmp/wordpress-tests-lib-$WP_VERSION:/tmp/wordpress-tests-lib \
	-v /tmp/wordpress-$WP_VERSION:/tmp/wordpress \
	--rm ghcr.io/automattic/phpunit-docker/phpunit:latest \
	--bootstrap /app/tests/bootstrap.php "$@"
