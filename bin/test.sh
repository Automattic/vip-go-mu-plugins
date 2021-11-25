#!/bin/sh

while [ $# -gt 0 ]; do
    case "$1" in
        --wp)
            shift
            WP_VERSION="$1"
        ;;

        --multisite)
            shift
            WP_MULTISITE="$1"
        ;;

        --php)
            shift
            PHP_VERSION="$1"
        ;;

        --php-options)
            shift
            PHP_OPTIONS="$1"
        ;;

        --phpunit)
            shift
            PHPUNIT_VERSION="$1"
        ;;

        --network)
            shift
            NETWORK_NAME_OVERRIDE="$1"
        ;;

        --dbhost)
            shift
            MYSQL_HOST_OVERRIDE="$1"
        ;;

        --docker-options)
            shift
            DOCKER_OPTIONS="$1"
        ;;

        *)
            ARGS="${ARGS} $1"
        ;;
    esac

    shift
done

: "${WP_VERSION:=latest}"
: "${WP_MULTISITE:=0}"
: "${PHP_VERSION:=""}"
: "${PHP_OPTIONS:=""}"
: "${PHPUNIT_VERSION:=""}"
: "${DOCKER_OPTIONS:=""}"

export WP_VERSION
export WP_MULTISITE
export PHP_VERSION
export PHP_OPTIONS
export PHPUNIT_VERSION

echo "--------------"
echo "Will test with WP_VERSION=${WP_VERSION} and WP_MULTISITE=${WP_MULTISITE}"
echo "--------------"
echo

MARIADB_VERSION="10.3"

UUID=$(date +%s000)
if [ -z "${NETWORK_NAME_OVERRIDE}" ]; then
    NETWORK_NAME="tests-${UUID}"
    docker network create "${NETWORK_NAME}"
else
    NETWORK_NAME="${NETWORK_NAME_OVERRIDE}"
fi

export MYSQL_USER=wordpress
export MYSQL_PASSWORD=wordpress
export MYSQL_DATABASE=wordpress_test

db=""
if [ -z "${MYSQL_HOST_OVERRIDE}" ]; then
    MYSQL_HOST="db-${UUID}"
    db=$(docker run --rm --network "${NETWORK_NAME}" --name "${MYSQL_HOST}" -e MYSQL_ROOT_PASSWORD="wordpress" -e MARIADB_INITDB_SKIP_TZINFO=1 -e MYSQL_USER -e MYSQL_PASSWORD -e MYSQL_DATABASE -d "mariadb:${MARIADB_VERSION}")
else
    MYSQL_HOST="${MYSQL_HOST_OVERRIDE}"
fi

export MYSQL_HOST

cleanup() {
    if [ -n "${db}" ]; then
        docker rm -f "${db}"
    fi

    if [ -z "${NETWORK_NAME_OVERRIDE}" ]; then
        docker network rm "${NETWORK_NAME}"
    fi
}

trap cleanup EXIT

# shellcheck disable=SC2086 # ARGS and DOCKER_OPTIONS must not be quoted
docker run \
    -it \
    --rm \
    --network "${NETWORK_NAME}" \
    -e WP_VERSION \
    -e WP_MULTISITE \
    -e PHP_VERSION \
    -e PHP_OPTIONS \
    -e PHPUNIT_VERSION \
    -e MYSQL_USER \
    -e MYSQL_PASSWORD \
    -e MYSQL_DATABASE \
    -e MYSQL_HOST \
    -e DISABLE_XDEBUG=1 \
    ${DOCKER_OPTIONS} \
    -v "$(pwd):/home/circleci/project" \
    ghcr.io/automattic/vip-container-images/wp-test-runner:latest \
    ${ARGS}
