#!/bin/sh

set -e

if [ $# -lt 2 ]; then
  echo "Syntax: add-jetpack-subtree.sh <tree_version> <jetpack_tag>"
  echo
  echo "Example: add-jetpack-subtree.sh 8.9 8.9.0"
  echo "This will create a new subtree jetpack-8.9 using the tag 8.9.0"
  exit 1
fi

tree_version=$1
jetpack_tag=$2

tree_dir="jetpack-${tree_version}"
if [ -e "$tree_dir" ]; then
  echo "Subtree directory $tree_dir already exists, cannot add it again"
  exit 1
fi

echo "Creating new jetpack subtree $tree_dir using jetpack tag $jetpack_tag"

git subtree add --squash -P $tree_dir https://github.com/Automattic/jetpack $jetpack_tag -m "Add jetpack $tree_version subtree with tag $jetpack_tag"

echo "Removing unneeded bin/ and tools/ folders"

git rm -r $tree_dir/bin $tree_dir/tools
git commit --amend --no-edit
