#!/bin/bash
#
# Usage: $0 <source-image> <target-image>
#
# tag the <source-image>:<sha> as <target-image>:latest
# and <target-image>:<sha>, and push both image tags
#
# e.g.$0 my-app hub.docker.com/my-app
# will tag and push my-app:<sha> as:
#   - hub.docker.com/my-app:latest
#   - hub.docker.com/my-app:<sha>
#
set -euxo pipefail

if [[ $# != 2 ]]; then
  echo "Usage: $(basename "$0") <source-image-base> <target-image-base>" >&2
  exit 1
fi

source="$1:${SHA}"
image_sha="$2:${SHA}"
image_latest="$2:latest"

# tag and push latest image
docker tag "${source}" "${image_latest}"
docker push "${image_latest}"

# @@@ TODO: also push sha-tagged image
docker tag "${source}" "${image_sha}"
#docker push "${image_sha}"
