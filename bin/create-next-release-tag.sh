#!/bin/sh

### This script is run by GitHub Actions CI

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

echo $tag