#!/bin/bash

set -ex

# Convert the URLs in the superproject .gitmodules file,
# then init those submodules
sed -i -e "s|git@\([^:]*\):|https://\1/|" .gitmodules
git submodule update --init
# Now recurse over all the contained submodules,
# sub-submodules, etc, to do the same
date; git submodule foreach --recursive 'if [ -w .gitmodules ]; then sed -i -e "s|git@\([^:]*\):|https://\1/|" "$toplevel/$path/.gitmodules"; fi; git submodule update --init "$toplevel/$path";'; date;

DEPLOY_BUILD_DIR="/tmp/deploy_build/"

cp ./ci/known_hosts ~/.ssh/known_hosts

git clone git@github.com:Automattic/vip-go-mu-plugins-built.git /tmp/target

mkdir -p ${DEPLOY_BUILD_DIR}

# Copy the files into the build dir
cp -pr ./* ${DEPLOY_BUILD_DIR}
cp -prv ./.[a-zA-Z0-9]* ${DEPLOY_BUILD_DIR}

# Some of the commands below may fail
set +e

# Remove VCS and CI config
find ${DEPLOY_BUILD_DIR} -name ".svn" -exec rm -rfv {} \; 2> /dev/null
find ${DEPLOY_BUILD_DIR} -name ".git*" -not -name ".github" -exec rm -rfv {} \; 2> /dev/null
find ${DEPLOY_BUILD_DIR} -name ".travis.yml" -exec rm -rfv {} \; 2> /dev/null

# Remove everything unnecessary to running this (tests, deploy scripts, etc)
rm -v ${DEPLOY_BUILD_DIR}/README.md
mv -v ${DEPLOY_BUILD_DIR}/README-PUBLIC.md ${DEPLOY_BUILD_DIR}/README.md

# Update the composer file to the distribution version
rm -v ${DEPLOY_BUILD_DIR}/composer.json
mv -v ${DEPLOY_BUILD_DIR}/ci/templates/composer.json ${DEPLOY_BUILD_DIR}/composer.json

# @FIXME: We will need to replace this ci dir with one which can run tests on the public repo
# BUT this public ci dir cannot include our insecure key (and does not need to)
rm -rfv ${DEPLOY_BUILD_DIR}/ci/
rm -v ${DEPLOY_BUILD_DIR}/.travis.yml

# We've finished with commands which may fail, so return to exiting the script
# if any command exits with a non-zero
set -e

# Copy the Git config into place
mv /tmp/target/.git ${DEPLOY_BUILD_DIR}

cd ${DEPLOY_BUILD_DIR}

git config user.name "CircleCI"
git config user.email "builds@circleci.com"
git config push.default "current"

git add -A .
# If you get an SSH prompt to enter a passphrase, you likely encrypted then
# key against the wrong repository
git commit -am "Built from vip-go-mu-plugins@${GIT_REVISION}"
git push
