#!/bin/sh

set -e

if [ "$(id -u || true)" -ne 0 ]; then
    echo 'Script must be run as root. Use sudo, su, or add "USER root" to your Dockerfile before running this script.'
    exit 1
fi

if [ -n "${_REMOTE_USER}" ] && [ "${_REMOTE_USER}" != "root" ]; then
    echo "(*) Adding ${_REMOTE_USER} to sudoers"
    echo "${_REMOTE_USER} ALL=(ALL) NOPASSWD:ALL" > "/etc/sudoers.d/${_REMOTE_USER}"
    chmod 0440 "/etc/sudoers.d/${_REMOTE_USER}"
    echo "Done!"
fi
