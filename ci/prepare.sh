#!/bin/bash

# Called by Travis CI

# Exit if anything fails AND echo each command before executing
# http://www.peterbe.com/plog/set-ex
set -ex

cd $TRAVIS_BUILD_DIR

# Convert the URLs in the superproject .gitmodules file,
# then init those submodules
# Done only when running WP tests, as we don't want to run PHPCS on submodules
if [[ ! -z "$WP_VERSION" ]] ; then
	sed -i -e "s|git@\([^:]*\):|https://\1/|" .gitmodules
	git submodule update --init
	# Now recurse over all the contained submodules,
	# sub-submodules, etc, to do the same
	date; git submodule foreach --recursive 'if [ -w .gitmodules ]; then sed -i -e "s|git@\([^:]*\):|https://\1/|" "$toplevel/$path/.gitmodules"; fi; git submodule update --init "$toplevel/$path";'; date;
fi

# Install unit tests
# ==================

bash bin/install-wp-tests.sh wordpress_test root '' localhost "${WP_VERSION}"
