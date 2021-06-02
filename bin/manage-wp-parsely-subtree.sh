#!/bin/sh

set -e

if [ $# -lt 2 ]; then
  echo "Syntax: manage-wp-parsely-subtree.sh <tree_version> <parsely_tag>"
  echo
  echo "Example: manage-wp-parsely-subtree.sh 2.4 2.4.1"
  echo
  echo "If subtree already exists:"
  echo "  This command will update the wp-parsely-2.4 subtree to the tag 2.4.1"
  echo "If the subtree does not already exist:"
  echo "  This command will create a new subtree wp-parsely-2.4 using the tag 2.4.1"
  exit 1
fi

tree_version=$1
parsely_tag=$2

tree_dir="wp-parsely-${tree_version}"
if [ -e "$tree_dir" ]; then
  echo "Updating existing wp-parsely subtree $tree_dir to the tag $parsely_tag"

  git subtree pull --squash -P $tree_dir https://github.com/Parsely/wp-parsely $parsely_tag -m "Update wp-parsely $tree_version subtree to tag $parsely_tag"
  exit
fi

echo "Creating new wp-parsely subtree $tree_dir using tag: $parsely_tag"

git subtree add --squash -P $tree_dir https://github.com/Parsely/wp-parsely $parsely_tag -m "Add wp-parsely $tree_version subtree with tag $parsely_tag"
