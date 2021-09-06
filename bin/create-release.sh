#!/bin/sh

if ! command -v git &> /dev/null
then
    echo "Git is not installed in this computer. Please install it before proceeding."
    exit
fi

if ! command -v gh &> /dev/null
then
    echo "GitHub CLI could not be found. Please refer to https://github.com/cli/cli for install instructions."
    exit
fi

if ! command -v date &> /dev/null
then
    echo "date command not found"
    exit
fi

echo "Fetching latest changes..."
git fetch

current_date=$(date '+v%Y%m%d.')
minor_version=0

while [ $minor_version -le 8 ]
do
  tag="$current_date$minor_version"
  if git rev-parse $tag >/dev/null 2>&1
  then
    if [ $minor_version -ge 7 ]
    then
      echo "Could not create a release. Please attempt it manually."
      exit
    fi
    minor_version=$(( $minor_version + 1 ))
  else
    break
  fi
done

notes_file="/tmp/$tag.md"
gh pr list --state closed --label "[Status] Deployed to staging" > "$notes_file"
sed -i -e 's/^/- #/' $notes_file

gh release create $tag --title $tag --notes-file "$notes_file"

echo ""
echo "New release $tag created successfully!"
