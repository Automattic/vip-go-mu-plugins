#!/bin/bash

usage () {
	echo "Usage $0 <OUTPUT_DIRECTORY> <OUTPUT_FORMAT=ZIP>"
	echo "Supported values for <OUTPUT_FORMAT> are: ZIP, DIR"
	echo "IMPORTANT! Files in the output directory will be OVERWRITTEN!"
}

OUTPUT_DIRECTORY="$1"
OUTPUT_FORMAT="${2:-ZIP}"
OUTPUT_FORMAT=$(echo "$OUTPUT_FORMAT" | tr '[:lower:]' '[:upper:]')

if [ ! -d "$OUTPUT_DIRECTORY" ]; then
	usage
	echo "<OUTPUT_DIRECTORY> is not a dir. Your input was: \"$OUTPUT_DIRECTORY\""
	exit 1
fi

if [ ! -w "$OUTPUT_DIRECTORY" ]; then
	usage
	echo "Cannot write to <OUTPUT_DIRECTORY>: $OUTPUT_DIRECTORY"
	exit 2
fi

BRANCH=$(git branch --show-current)
REMOTE="origin"
REMOTEBRANCH="$REMOTE/$BRANCH"
LOCALHASH=$(git rev-parse --short $BRANCH)

echo "Attempting to package..."
echo "  branch:     $BRANCH"
echo "  hash:       $LOCALHASH"
echo "  format:     $OUTPUT_FORMAT"
echo "  output dir: $OUTPUT_DIRECTORY";

if [[ "$(git status -sb | wc -l | xargs)" != "1" && -z "$DEBUG" ]]; then
	echo "ERROR: Local branch contains uncommitted changes"
	exit 10
fi

echo "Fetching remote branch: $REMOTE $BRANCH..."
git fetch "$REMOTE" "$BRANCH"

if [[ "$?" != "0" && -z "$DEBUG" ]]; then
	echo "Unable to continue without remote branch: $REMOTEBRANCH"
	exit 11
fi

REMOTEHASH=$(git rev-parse --short $REMOTEBRANCH)

echo "Local Branch \`$BRANCH\` is at hash: \`$LOCALHASH\`"
echo "Remote Branch \`$REMOTE $BRANCH\` is at hash: \`$REMOTEHASH\`"

if [[ "$LOCALHASH" != "$REMOTEHASH" && -z "$DEBUG" ]]; then
	echo "ERROR: Local hash does not match remote"
	exit 12
fi

git diff --exit-code --quiet "$REMOTEBRANCH"..HEAD 2>/dev/null
if [[ "$?" != "0" && -z "$DEBUG" ]]; then
	echo "ERROR: Local branch called $REMOTEBRANCH & actual remote branch do not match"
	exit 13
fi

git diff --exit-code --quiet "$REMOTEBRANCH" 2>/dev/null
if [[ "$?" != "0" && -z "$DEBUG" ]]; then
	echo "ERROR: Local files don't match branch: $REMOTEBRANCH"
	exit 14
fi

confirm() {
	read -p "Are you sure? (y/n) " -n1 -r
	echo
	if [[ ! $REPLY =~ ^[Yy]$ ]]; then
		echo "bailed"
		exit
	fi
}

writezip() {
	TIDYBRANCH=$(echo $BRANCH | sed 's/[^a-zA-Z0-9_.]/-/g')
	OUTFILE="$OUTPUT_DIRECTORY/wp-parsely-$LOCALHASH-$TIDYBRANCH.zip"
	echo "Preparing to export plugin to: $OUTFILE"

	if [[ -f $OUTFILE ]]; then
		echo "WARNING: $OUTFILE already exists and will be overwritten."
		confirm
	fi

	git archive --format zip --output "$OUTFILE" "$REMOTEBRANCH"
	if [[ "$?" != "0" ]]; then
		echo "Error running git archive command";
		exit 20
	fi
	echo "Successfully wrote plugin to: $OUTFILE"
}

# `git archive` does not support writing directly to a directory, so tar, then extract
# Inspired by: https://github.com/10up/action-wordpress-plugin-deploy/blob/b3f8b3c0d73bf0af43aea9a0c2cb398d12d46b25/entrypoint.sh#L93-L100
writedir() {
	if [ "$(ls -A "$OUTPUT_DIRECTORY")" ]; then
		echo "WARNING: $OUTPUT_DIRECTORY is NOT EMPTY!"
		echo "If you continue, its contents will be completely replaced."
		confirm
	fi

	TMPDIR=$(mktemp -d)

	echo "Preparing to export plugin to temporary dir: $TMPDIR"

	git archive HEAD | tar -x --directory "$TMPDIR"
	if [[ "$?" != "0" ]]; then
		echo "Error running git archive command";
		exit 30
	fi

	rsync -a "$TMPDIR"/ "$OUTPUT_DIRECTORY"/ --delete --delete-excluded
	if [[ "$?" != "0" ]]; then
		echo "Error running rsync command";
		exit 31
	fi

	echo "Successfully wrote plugin to dir: $OUTPUT_DIRECTORY"
}

if [[ "$OUTPUT_FORMAT" == "ZIP" ]]; then
	writezip
	exit
fi

if [[ "$OUTPUT_FORMAT" == "DIR" ]]; then
	writedir
	exit
fi

echo "Unsupported <OUTPUT_FORMAT>: $OUTPUT_FORMAT"
exit 99
