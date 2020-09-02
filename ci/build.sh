#!/bin/bash
#
# CI script to build and push the container image
#
# requires the following environment variables:
# REGISTRY: docker registry e.g. hub.docker.com
#
set -euo pipefail
set -x

if [[ -z ${REGISTRY+x} ]]; then
  echo "REGISTRY must be set to the docker registry url" >&2
  exit 1
fi

image_base="${1:-${REGISTRY}/mu-plugins}"
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
  docker push "${image}"
  docker push "${latest}"
}

prepare
build
#push
