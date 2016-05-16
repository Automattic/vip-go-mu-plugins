#!/bin/bash

# Called by Travis CI

# Exit if anything fails AND echo each command before executing
# http://www.peterbe.com/plog/set-ex
set -ex

# Install unit tests
# ==================

bash bin/install-wp-tests.sh wordpress_test root '' localhost "${WP_VERSION}"
