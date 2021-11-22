#!/bin/bash

while test $# -gt 0; do
  case "$1" in
  --restart)
    RESTART=true
    shift;;
  --wp)
    shift
    RAW_WP_VERSION=$1
    ;;
  esac
  shift
done

while getopts r: flag
do
    case "${flag}" in
        r) username=${OPTARG};;
    esac
done

WP_VERSION=${RAW_WP_VERSION-latest}
WP_MULTISITE=${0}

MARIADB_VERSION="10.3"
NETWORK_NAME="tests-e2e"
DB_CONTAINER_NAME="db_e2e"
WP_CONTAINER_NAME="wp_e2e"
NG_CONTAINER_NAME="ng_e2e"
FPM_CONTAINER_NAME="php"
MYSQL_ROOT_PASSWORD="wordpress"
SHARED_VOLUME="shared"
NEEDS_DB="false"

if [ $RESTART ]; then
  docker rm -f $NG_CONTAINER_NAME
  docker rm -f $FPM_CONTAINER_NAME
  docker rm -f $DB_CONTAINER_NAME
  docker network rm $NETWORK_NAME
  docker volume rm $SHARED_VOLUME
fi

if ! docker network ls --format '{{.Name}}' | grep -w $NETWORK_NAME &> /dev/null; then
  docker network create $NETWORK_NAME
fi
docker volume create --name $SHARED_VOLUME

if ! docker ps --format '{{.Names}}' | grep -w $DB_CONTAINER_NAME &> /dev/null; then
    NEEDS_DB="true"
    db=$(docker run --network $NETWORK_NAME --name $DB_CONTAINER_NAME -e MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD -d mariadb/server:$MARIADB_VERSION)
fi

## Ensure there's a database connection for the rest of the steps
if [ $NEEDS_DB == "true" ]; then
  until docker exec -it $db mysql -u root -h $DB_CONTAINER_NAME --password="wordpress" -e 'CREATE DATABASE wordpress_test' > /dev/null; do
	  echo "Waiting for database connection..."
	  sleep 5
  done
fi

[[ $(docker ps -f "name=$FPM_CONTAINER_NAME" --format '{{.Names}}') == $FPM_CONTAINER_NAME ]] ||
docker run \
  --network $NETWORK_NAME \
  --name $FPM_CONTAINER_NAME \
  -v $SHARED_VOLUME:/wp \
  -v $(pwd):/wp/wp-content/mu-plugins \
  -v $(pwd)/__tests__/e2e/wp-config-e2e.php:/wp/wp-config.php \
  -v $(pwd)/__tests__/e2e/php-startup.sh:/scripts/startup.sh \
  -e WP_VERSION="$WP_VERSION" \
  -e WORDPRESS_DB_HOST="$DB_CONTAINER_NAME" \
  -e MYSQL_ROOT_PASSWORD="$MYSQL_ROOT_PASSWORD" \
  -e WORDPRESS_DB_NAME="wordpress_test" \
  -e WORDPRESS_DB_USER="root" \
  -e WORDPRESS_DB_PASSWORD="wordpress" \
  -d \
  --rm ghcr.io/automattic/vip-container-images/php-fpm:7.4

if [ $CI ]; then
  docker cp $(pwd) php:/wp/wp-content/mu-plugins
  docker cp $(pwd)/__tests__/e2e/wp-config-e2e.php php:/wp/wp-config.php
  docker cp $(pwd)/__tests__/e2e/php-startup.sh php:/scripts/startup.sh
fi
docker exec php /scripts/startup.sh
  

[[ $(docker ps -f "name=$NG_CONTAINER_NAME" --format '{{.Names}}') == $NG_CONTAINER_NAME ]] ||
docker run \
  --network $NETWORK_NAME \
  --name $NG_CONTAINER_NAME \
  -v $SHARED_VOLUME:/wp \
  -v $(pwd):/wp/wp-content/mu-plugins \
  -p 80:80 \
  -d \
  --rm ghcr.io/automattic/vip-container-images/nginx:1.21.3

  while [[ "$(curl -s -o /dev/null -w ''%{http_code}'' localhost:80)" != "200" ]]; do 
    sleep 3; 
  done