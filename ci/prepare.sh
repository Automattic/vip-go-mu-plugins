#!/bin/bash

# Called by Travis CI

# Exit if anything fails AND echo each command before executing
# http://www.peterbe.com/plog/set-ex
set -ex

cd $TRAVIS_BUILD_DIR

# Convert the URLs in the superproject .gitmodules file,
# then init those submodules
sed -i -e "s|git@\([^:]*\):|https://\1/|" .gitmodules
git submodule update --init
# Now recurse over all the contained submodules,
# sub-submodules, etc, to do the same
date; git submodule foreach --recursive 'if [ -w .gitmodules ]; then sed -i -e "s|git@\([^:]*\):|https://\1/|" "$toplevel/$path/.gitmodules"; fi; git submodule update --init "$toplevel/$path";'; date;
