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
VIP_PHPDOC_DIR="$TRAVIS_BUILD_DIR/../phpdoc"

cat ~/.ssh/config

# Get the encrypted private key from the repo settings
# This is the private pair to the "Travis GH Pages Deploy Key"
# The public key portion can be deleted here:
# https://github.com/Automattic/vip-go-mu-plugins/settings/keys
# The private key is in Travis settings here:
# https://travis-ci.org/Automattic/vip-go-mu-plugins/settings
# Turn off echo for the private key
set +x
echo -e $VIP_GITHUB_DEPLOY_KEY > /tmp/vip_deploy_key
chmod 600 /tmp/vip_deploy_key
set -x

# Ensure we use our deploy key when connecting to GitHub,
# this allows us to write (as the deploy key has write perms)
echo -e "\nHost github.com \n  IdentityFile /tmp/vip_deploy_key \n" >> ~/.ssh/config

git clone "git@github.com:${TRAVIS_REPO_SLUG}.git" ${VIP_DOCS_DIR}
cd ${VIP_DOCS_DIR}
git fetch --all
git checkout gh-pages
ls -alh

# Composer runs faster without Xdebug, and we don't need Xdebug any more
phpenv config-rm xdebug.ini

mkdir -p $VIP_PHPDOC_DIR
cd $VIP_PHPDOC_DIR
pwd

composer require phpdocumentor/phpdocumentor
# PHPDoc is really verbose, more than Travis can cope with,
# so we send it to /dev/null
vendor/phpdocumentor/phpdocumentor/bin/phpdoc --ignore-symlinks --sourcecode --no-interaction --directory="${TRAVIS_BUILD_DIR}/vip-helpers/" --filename="${TRAVIS_BUILD_DIR}/vip-cache-manager/api.php,${TRAVIS_BUILD_DIR}/lib/proxy/ip-forward.php" --target="${VIP_DOCS_DIR}" --title="WordPress.com VIP – VIP Go Function Documentation" --template clean > /dev/null
ls -alh $VIP_DOCS_DIR

cd ${VIP_DOCS_DIR}

git config user.name "Travis CI"
git config user.email "travis@travis-ci.com"
git config push.default "current"

git add -A .

set +ex
GIT_MSG=$( printf %"s \n\n" "Built at ${TRAVIS_REPO_SLUG}@${TRAVIS_COMMIT}" "Commits included:" "$(git log ${TRAVIS_COMMIT_RANGE})"; )
set -x
git commit -am "${GIT_MSG}"
if [ 0 != $? ]; then
	echo "Nothing to push"
else
	git branch
	git push
	echo "Pushing!"
fi

