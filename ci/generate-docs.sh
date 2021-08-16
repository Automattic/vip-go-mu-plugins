#!/bin/bash

set -ex

# Path to the documentation directory
VIP_DOCS_DIR="phpdoc"
# Path to the PHPDoc application
VIP_PHPDOC_DIR="/tmp/phpdoc"

cp ./ci/known_hosts ~/.ssh/known_hosts

# Clone the repo at the `gh-pages` branch, getting only the commits for that branch
git clone --branch gh-pages --single-branch "git@github.com:Automattic/vip-go-mu-plugins.git" ${VIP_DOCS_DIR}

mkdir -p $VIP_PHPDOC_DIR
cd $VIP_PHPDOC_DIR
# Using Composer to install PHPDoc is slower than other methods, but installs
# a more up to date version.
composer --quiet require jms/serializer:1.7.*
composer --quiet require phpdocumentor/phpdocumentor:^2.9
PATH="$PATH:${VIP_PHPDOC_DIR}/vendor/phpdocumentor/phpdocumentor/bin/"
echo $PATH

cd ~/project

# See phpdoc.dist.xml for the majority of the configuration. You can override
# phpdoc.dist.xml in it's entirety by providing a file named phpdoc.xml.
# The command switches here appear to have no equivalent in phpdoc(.dist).xml.
make phpdoc

cd ${VIP_DOCS_DIR}

git config user.name "CI Pipeline"
git config user.email "ci@pipeline.com"
git config push.default "current"

git add -A .

set +e
git commit -am "Built at vip-go-mu-plugins@${GIT_REVISION}"
if [ 0 != $? ]; then
	echo "Nothing to push"
else
	git branch
	git push
	echo "Pushing!"
fi
