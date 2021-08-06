#!/usr/bin/env bash

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]"
	exit 1
fi

source "$(dirname $0)"/utils.sh

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}

if [[ $5 == 'nightly' ]]; then
	WP_VERSION='nightly'
else
	WP_VERSION=${5-latest}
fi

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null && pwd )"
$DIR/download-wp-tests.sh "$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST" "$WP_VERSION"

composer update

install_db "$DB_NAME" "$DB_USER" "$DB_PASS" "$DB_HOST"
