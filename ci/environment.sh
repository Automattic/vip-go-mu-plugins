#!/bin/bash

# Called by Travis CI

# Exit if anything fails AND echo each command before executing
# http://www.peterbe.com/plog/set-ex
set -ex

mkdir -p ~/.ssh
echo -e "Host github.com\n  IdentityFile ${TRAVIS_BUILD_DIR}/ci/new-key" > ~/.ssh/config
cat ~/.ssh/config
cp ${TRAVIS_BUILD_DIR}/ci/ssh/known_hosts ~/.ssh/known_hosts
cat ~/.ssh/known_hosts
