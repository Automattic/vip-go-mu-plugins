#!/bin/bash

# called by Travis CI

# Exit if anything fails AND echo each command before executing
# http://www.peterbe.com/plog/set-ex
set -ex

# Parallel for running PHPLint quicker
sudo apt-get install parallel
