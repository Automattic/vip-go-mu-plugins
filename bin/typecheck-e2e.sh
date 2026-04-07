#!/bin/sh

set -eu

if [ ! -x "__tests__/e2e/node_modules/.bin/tsc" ]; then
	echo "Missing __tests__/e2e dependencies."
	echo "Run: npm --prefix __tests__/e2e ci"
	exit 1
fi

npm --prefix __tests__/e2e run typecheck
