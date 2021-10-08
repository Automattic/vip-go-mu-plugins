#!/bin/sh

if ! command -v git >/dev/null 2>&1
then
  echo "Git is not installed in this computer. Please install it before proceeding."
  exit
fi

if ! command -v gh >/dev/null 2>&1
then
  echo "GitHub CLI could not be found. Please refer to https://github.com/cli/cli for install instructions."
  exit
fi

if ! command -v date >/dev/null 2>&1
then
  echo "date command not found"
  exit
fi

gh auth status -h github.com || exit 1

current_branch=$(git branch --show-current)
notes_file=
stashed=

cleanup() {
  if [ -n "$notes_file" ] && [ -f "$notes_file" ]; then
    rm -f "$notes_file"
  fi

  git checkout -q "$current_branch"
  if [ "$stashed" = 'yes' ]; then
    git stash pop --quiet
  fi
}

trap cleanup EXIT

echo "Fetching latest changes..."

if ! git diff --quiet; then
  git stash push --quiet
  stashed=yes
fi

git checkout -q master
git pull

current_date=$(date '+v%Y%m%d.')
minor_version=0

while [ $minor_version -le 8 ]
do
  tag="$current_date$minor_version"
  if git rev-parse "$tag" >/dev/null 2>&1
  then
    if [ $minor_version -ge 8 ]
    then
      echo "Could not create a release. Please attempt it manually."
      exit
    fi
    minor_version=$(( minor_version + 1 ))
  else
    break
  fi
done

notes_file="/tmp/$tag.md"
gh pr list --state closed --label "[Status] Deployed to staging" > "$notes_file"
sed -i -e 's/^/- #/' "$notes_file"

gh release create "$tag" --title "$tag" --notes-file "$notes_file" --target master

echo ""
echo "New release $tag created successfully!"
