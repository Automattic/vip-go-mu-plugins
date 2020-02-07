#!/usr/bin/env bash

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
DIFF_FILE=$(mktemp)
PHPCS_FILE=$(mktemp)

echo "== Generating diff against master: ${DIFF_FILE}"
git remote set-branches --add origin master
git fetch
git diff origin/master > $DIFF_FILE

echo "== Generating phpcs report: ${PHPCS_FILE}"
$DIR/../vendor/bin/phpcs -q --extensions=php --standard=phpcs.xml.dist --report=json > $PHPCS_FILE || true

echo "== Running diff"
$DIR/../vendor/bin/diffFilter --phpcs $DIFF_FILE $PHPCS_FILE 0
