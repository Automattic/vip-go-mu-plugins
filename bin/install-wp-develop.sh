if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}

WP_CORE_DEVELOP_DIR="${WP_CORE_DEVELOP_DIR-/tmp/wordpress-develop}"

download() {
    if [ `which curl` ]; then
        curl -s "$1" > "$2";
    elif [ `which wget` ]; then
        wget -nv -O "$2" "$1"
    fi
}


if [[ $WP_VERSION == 'nightly' ]]; then
	WP_TESTS_TAG="trunk"
elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+(\.[0-9]+)? ]]; then
	WP_TESTS_TAG="tags/$WP_VERSION"
else
	# http serves a single offer, whereas https serves multiple. we only want one
	download http://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
	grep '[0-9]+\.[0-9]+(\.[0-9]+)?' /tmp/wp-latest.json
	WP_VERSION=$(grep -o '"version":"[^"]*' /tmp/wp-latest.json | sed 's/"version":"//')
	if [[ -z "$WP_VERSION" ]]; then
		echo "Latest WordPress version could not be found"
		exit 1
	fi
	WP_TESTS_TAG="tags/$WP_VERSION"
fi

echo $WP_VERSION

set -ex
shopt -s extglob;

# portable in-place argument for both GNU sed and Mac OSX sed
if [[ $(uname -s) == 'Darwin' ]]; then
	IOPTION='-i .bak'
else
	IOPTION='-i'
fi

# set up testing suite if it doesn't yet exist
if [ ! -d $WP_CORE_DEVELOP_DIR ]; then
	# set up testing suite
	mkdir -p $WP_CORE_DEVELOP_DIR
	svn co --quiet --ignore-externals https://develop.svn.wordpress.org/${WP_TESTS_TAG} $WP_CORE_DEVELOP_DIR
fi

cd $WP_CORE_DEVELOP_DIR

if [ ! -f wp-tests-config.php ]; then
	cp wp-tests-config-sample.php wp-tests-config.php
	sed $IOPTION "s/youremptytestdbnamehere/$DB_NAME/" wp-tests-config.php
	sed $IOPTION "s/yourusernamehere/$DB_USER/" wp-tests-config.php
	sed $IOPTION "s/yourpasswordhere/$DB_PASS/" wp-tests-config.php
	sed $IOPTION "s|localhost|${DB_HOST}|" wp-tests-config.php
fi


install_db() {
	# parse DB_HOST for port or socket references
	local PARTS=(${DB_HOST//\:/ })
	local DB_HOSTNAME=${PARTS[0]};
	local DB_SOCK_OR_PORT=${PARTS[1]};
	local EXTRA=""

	if ! [ -z $DB_HOSTNAME ] ; then
		if [ $(echo $DB_SOCK_OR_PORT | grep -e '^[0-9]\{1,\}$') ]; then
			EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
		elif ! [ -z $DB_SOCK_OR_PORT ] ; then
			EXTRA=" --socket=$DB_SOCK_OR_PORT"
		elif ! [ -z $DB_HOSTNAME ] ; then
			EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
		fi
	fi

	# create database
	mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA
}

install_db
