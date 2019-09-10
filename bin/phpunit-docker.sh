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

MYSQL_ROOT_PASSWORD='wordpress'
docker network create tests
db=$(docker run --network tests --name db -e MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD -d mariadb)
function cleanup() {
	docker rm -f $db
	docker network rm tests
}
trap cleanup EXIT

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
WP_VERSION=$($DIR/download-wp-tests.sh wordpress_test root "$MYSQL_ROOT_PASSWORD" "db" "$WP_VERSION")

## Ensure there's a database connection for the rest of the steps
until docker exec -it $db mysql -u root -h db --password="wordpress" -e 'CREATE DATABASE wordpress_test' > /dev/null; do
	echo "Waiting for database connection..."
	sleep 5
done

docker run \
 	--network tests \
	-e WP_MULTISITE="$WP_MULTISITE" \
	-v $(pwd):/app \
	-v /tmp/wordpress-tests-lib-$WP_VERSION:/tmp/wordpress-tests-lib \
	-v /tmp/wordpress-$WP_VERSION:/tmp/wordpress \
	--rm phpunit/phpunit \
	--bootstrap /app/tests/bootstrap.php "$@"
