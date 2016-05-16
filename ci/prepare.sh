#!/bin/bash

# Called by Travis CI

# Exit if anything fails AND echo each command before executing
# http://www.peterbe.com/plog/set-ex
set -ex

# We cannot checkout even public repositories via SSH unless we
# are authenticated and authorised on that repo (and we aren't,
# for the submodules here)
if [ -w ${TRAVIS_BUILD_DIR}/.gitmodules ]; then
    sed -i "s#https\?://github.com/#git@github.com:#" ${TRAVIS_BUILD_DIR}/.gitmodules
fi;

# Install unit tests
# ==================

bash bin/install-wp-tests.sh wordpress_test root '' localhost "${WP_VERSION}"
