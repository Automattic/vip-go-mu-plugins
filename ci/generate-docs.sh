#!/bin/bash

# called by Travis CI

set -ex

# Path to the documentation directory
VIP_DOCS_DIR="${TRAVIS_BUILD_DIR}/phpdoc"
# Path to the PHPDoc application
VIP_PHPDOC_DIR="/tmp/phpdoc"

cp ./ci/known_hosts ~/.ssh/known_hosts

# Clone the repo at the `gh-pages` branch, getting only the commits for that branch
git clone --branch gh-pages --single-branch "git@github.com:vip-go-mu-plugins.git" phpdoc

# Composer runs faster without Xdebug, and we don't need Xdebug any more
phpenv config-rm xdebug.ini

mkdir -p $VIP_PHPDOC_DIR
cd $VIP_PHPDOC_DIR
# Using Composer to install PHPDoc is slower than other methods, but installs
# a more up to date version.
composer --quiet require jms/serializer:1.7.*
composer --quiet require phpdocumentor/phpdocumentor:^2.9
PATH="$PATH:${VIP_PHPDOC_DIR}/vendor/phpdocumentor/phpdocumentor/bin/"
echo $PATH

cd

ls -alh

# See phpdoc.dist.xml for the majority of the configuration. You can override
# phpdoc.dist.xml in it's entirety by providing a file named phpdoc.xml.
# The command switches here appear to have no equivalent in phpdoc(.dist).xml.
# make phpdoc

# cd ${VIP_DOCS_DIR}

# git config user.name "Travis CI"
# git config user.email "travis@travis-ci.com"
# git config push.default "current"

# git add -A .

# set +ex
# # Make a commit message for GitHub Pages which concatenates all
# # the commit messages from the commit ranges that we just processed
# # in this Travis run.
# GIT_MSG=$( printf %"s \n\n" "Built at ${TRAVIS_REPO_SLUG}@${TRAVIS_COMMIT}" "Commits included:" "$(git log ${TRAVIS_COMMIT_RANGE})"; )
# set -x
# git commit -am "${GIT_MSG}"
# if [ 0 != $? ]; then
# 	echo "Nothing to push"
# else
# 	git branch
# 	git push
# 	echo "Pushing!"
# fi
