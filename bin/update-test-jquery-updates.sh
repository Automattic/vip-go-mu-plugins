#!/bin/sh

if [ $# -gt 1 ]; then
  echo "Syntax: $0 <revision/branch>"
  echo
  echo "Revision can be left blank, which will update to the top of trunk branch"
  echo
  echo "Example: $0"
  echo "This will update the test-jquery-updates plugin to latest code"
  exit 1
fi

print_heading()
{
  echo "\n* $1"
}

revision="trunk"
if [ "$1" ]; then
  revision=$1
fi

directory="test-jquery-updates"

if [ -d "$directory" ]; then
  print_heading "Removing $directory directory before update"
  rm -rf $directory
  git commit -avm "Removing $directory directory before update"
fi

print_heading "Adding test-jquery-updates to $directory from revision $revision"
git subtree add --squash -P $directory git@github.com:WordPress/wp-jquery-update-test.git "$revision"


print_heading "Change capability required from install_plugins (not allowed on VIP platform) to activate_plugins"
sed -i 's/install_plugins/activate_plugins/g' "$directory/class_wp_jquery_update_test.php"
git commit -avm "Updates capability needed for test-jquery-updates"

echo "\n\nDONE  --- Don't forget to update the loader's version\n"