#!/bin/bash

# Called by Travis CI

# Exit if anything fails AND echo each command before executing
# http://www.peterbe.com/plog/set-ex
set -ex

mkdir -p ~/.ssh
ssh-add -D
echo -e "Host github.com\n  User wpcomvip-deploy\n  IdentityFile ${TRAVIS_BUILD_DIR}/ci/ssh/insecure-key\n" > ~/.ssh/config
chmod 600 ${TRAVIS_BUILD_DIR}/ci/ssh/*
cp ${TRAVIS_BUILD_DIR}/ci/ssh/known_hosts ~/.ssh/known_hosts

# Install unit tests
# ==================

bash bin/install-wp-tests.sh wordpress_test root '' localhost "${WP_VERSION}"
