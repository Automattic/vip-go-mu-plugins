#!/bin/sh

if [ $# -lt 2 ]; then
  echo "Syntax: update-jetpack-subtree.sh <tree_version> <jetpack_tag>"
  echo
  echo "Example: update-jetpack-subtree.sh 8.9 8.9.2"
  echo "This will update the subtree jetpack-8.9 to the tag 8.9.2"
  exit 1
fi

tree_version=$1
jetpack_tag=$2

tree_dir="jetpack-${tree_version}"
if [ ! -d "$tree_dir" ]; then
  echo "Subtree directory $tree_dir doesn't exist, cannot update it"
  exit 1
fi

echo "Updating jetpack subtree $tree_dir to the jetpack tag $jetpack_tag"

git rm -r $tree_dir
git commit -m "Removing $tree_dir for subtree replacement to $jetpack_tag"
git subtree add -P $tree_dir --squash https://github.com/Automattic/jetpack-production $jetpack_tag -m "Update jetpack $tree_version subtree to tag $jetpack_tag"
