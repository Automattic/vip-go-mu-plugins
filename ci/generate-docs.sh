#!/bin/bash

# called by Travis CI

set -ex

# if [[ "false" != "$TRAVIS_PULL_REQUEST" ]]; then
# 	echo "Not deploying pull requests."
# 	exit
# fi
#
# if [[ "$TRAVIS_BRANCH" != "$DEPLOY_BRANCH" ]]; then
# 	echo "Not on the '${DEPLOY_BRANCH}' branch."
# 	exit
# fi

## Install the PHPdoc binary
pear channel-discover pear.phpdoc.org
pear install phpdoc/phpDocumentor
phpenv rehash

phpdoc -d $TRAVIS_BUILD_DIR --title="WordPress.com VIP – VIP Go Function Documentation" --template clean
