#!/bin/bash
#
# Usage: $0 <image-base-name>
#  e.g. $0 registry.example.com/mu-plugins
#
# build and push the container image
# - run this from the directory with the Dockerfile
# - supply image basename as the first argument
# - image will be tagged with the commit sha and the latest tag
#
set -euo pipefail
set -x

image_base="${1:-mu-plugins}"
tag=$(git describe --always --tags HEAD)
image="${image_base}:${tag}"
latest="${image_base}:latest"

# prepare repo
function prepare {
  # git checkout master
  # git pull
  git submodule deinit -f .
  git submodule update --recursive --init --jobs 8
}

# build the image
function build {
  docker build --pull -t "${image}" -t "${latest}" .
}

# push the image to the registry
function push {
  # as a test, push only the latest image
  # @@@ TODO: also push tagged image
  #docker push "${image}"
  docker push "${latest}"
}

prepare
build
push
