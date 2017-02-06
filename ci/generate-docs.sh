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

VIP_DOCS_DIR="/tmp/${TRAVIS_REPO_SLUG}/docs/"

# Get the encrypted private key from the repo settings
# This is the private pair to the "Travis Docs Deploy Key"
# GitHub Deploy Key here:
# https://github.com/Automattic/vip-go-mu-plugins/settings/keys
echo -e $VIP_GITHUB_DEPLOY_KEY > /tmp/vip_deploy_key
cat /tmp/vip_deploy_key
chmod 600 /tmp/vip_deploy_key

echo "\nHost github.com \n  IdentityFile /tmp/vip_deploy_key \n" >> ~/.ssh/config
cat ~/.ssh/config

ssh -T git@github.com

git clone "git@github.com:${TRAVIS_REPO_SLUG}.git" ${VIP_DOCS_DIR}
cd ${VIP_DOCS_DIR}
git fetch --all
git checkout gh-pages

# Composer runs faster without Xdebug, and we don't need Xdebug any more
phpenv config-rm xdebug.ini

mkdir -p $TRAVIS_BUILD_DIR/../phpdoc
cd $TRAVIS_BUILD_DIR/../phpdoc
pwd

composer require phpdocumentor/phpdocumentor
ls -alh vendor/phpdocumentor/phpdocumentor/bin/
vendor/phpdocumentor/phpdocumentor/bin/phpdoc --no-interaction --directory="${TRAVIS_BUILD_DIR}" --target="${VIP_DOCS_DIR}" --title="WordPress.com VIP – VIP Go Function Documentation" --template clean
ls -alh $VIP_DOCS_DIR

cd ${VIP_DOCS_DIR}

git config user.name "Travis CI"
git config user.email "travis@travis-ci.com"
git config push.default "current"

git add -A .

set +e
GIT_MSG=$( printf %"s \n\n" "Built at ${TRAVIS_REPO_SLUG}@${TRAVIS_COMMIT}" "Commits included:" "$(git log ${TRAVIS_COMMIT_RANGE})"; )
echo ${GIT_MSG}
git commit -am "${GIT_MSG}"
if [ 0 != $? ]; then
	echo "Nothing to push"
else
	git branch
	git push
	echo "Pushing!"
fi

