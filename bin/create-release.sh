#!/bin/sh

### This script is run by GitHub Actions CI

tag=$1

notes_file="/tmp/$tag.md"
gh pr list --state closed --label "[Status] Deployed to staging" > "$notes_file"
sed -i -e 's/^/- #/' "$notes_file"

gh release create "$tag" --draft --title "$tag" --notes-file "$notes_file" --target master

echo ""
echo "New release $tag created successfully!"
