#!/bin/sh

# Lints the PHP files given as arguments (lint-staged passes the staged ones), or every PHP file
# outside dependencies, dot directories and the bundled Jetpack and Parse.ly copies.
# Prints only syntax errors, and exits non-zero if any file has one.

# shellcheck disable=SC2016 # The single-quoted script expands its own variables.
if [ $# -gt 0 ]; then
	printf '%s\0' "$@"
else
	find . -type d \( -name '.?*' -o -name node_modules -o -path ./vendor -o -path './jetpack*' -o -path './wp-parsely*' -o -path ./drop-ins/wp-memcached/stubs \) -prune \
		-o -type f -name '*.php' -print0
fi | xargs -0 -n 50 -P "$(getconf _NPROCESSORS_ONLN)" sh -c '
	status=0
	for file; do
		# php -l exits 255 on a parse error, which would make xargs stop linting the remaining files.
		php -d display_errors=stderr -d log_errors=0 -l "${file}" > /dev/null || status=1
	done
	exit "${status}"
' sh
