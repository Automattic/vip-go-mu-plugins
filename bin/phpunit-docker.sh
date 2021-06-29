#!/bin/bash

# Note: you can pass in additional phpunit args
# Test with explicit WP version and Multisite config: ./bin/phpunit-docker.sh --version 5.4.4 --multisite 0
# Test with explicit WP version and file: ./bin/phpunit-docker.sh --file tests/path/to/test --version 5.4.4
# Test a specific file: ./bin/phpunit-docker.sh --file tests/path/to/test.php
# Stop on failures: ./bin/phpunit-docker.sh --stop-on-failure
# etc.

while test $# -gt 0; do
  case "$1" in
  --version)
    shift
    RAW_WP_VERSION=$1
    ;;
  --multisite)
    shift
    RAW_WP_MULTISITE=$1
    ;;
  --file)
    shift
    PATH_TO_TEST=$1
    ;;
  --stop-on-failure)
    STOP_ON_FAILURE="--stop-on-failure"
    ;;
  --*)
    echo "bad option $1"
    ;;
  esac
  shift
done

WP_VERSION=${RAW_WP_VERSION-latest}
WP_MULTISITE=${RAW_WP_MULTISITE-0}

echo "--------------"
echo "Will test with WP_VERSION=$WP_VERSION and WP_MULTISITE=$WP_MULTISITE"
if [ -z "$PATH_TO_TEST" ]; then
  echo "Will test ALL FILES"
else
  echo "Will test FILE $PATH_TO_TEST"
fi
if [ -z "$STOP_ON_FAILURE" ]; then
  echo "Will NOT stop on failure"
else
  echo "Will stop on failure"
fi
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
	--bootstrap /app/tests/bootstrap.php "${STOP_ON_FAILURE} ${PATH_TO_TEST}"
