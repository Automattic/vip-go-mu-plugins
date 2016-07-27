#!/bin/bash

# Called by Travis CI

# Exit if anything fails AND echo each command before executing
# http://www.peterbe.com/plog/set-ex
set -ex

cd $TRAVIS_BUILD_DIR
cat .gitmodules
if [ -w .gitmodules ]; then
    sed -i -e "s|git@\([^:]*\):|https://\1/|" ./gitmodules.txt
fi;
cat .gitmodules
git submodule update --init --recursive

# Install unit tests
# ==================

bash bin/install-wp-tests.sh wordpress_test root '' localhost "${WP_VERSION}"
