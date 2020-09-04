#!/bin/bash
#
# Usage: $0 <image-name>
#
# build and tag the container image as <image-name>:latest
#
set -euxo pipefail

image_base="${1:-mu-plugins}"
image_sha="${image_base}:${SHA}"

# update all submodules
function prepare {
  # git checkout master
  # git pull
  git submodule deinit -f .
  git submodule update --recursive --init --jobs 8
}

# build the image
function build {
  docker build --pull -t "${image_sha}" .
}

prepare
build
