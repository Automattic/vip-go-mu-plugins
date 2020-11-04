#!/bin/sh

usage() {
  echo "Syntax: $0 -v 5.5 -r b9e54c55bbadcb21fb606fa185146f314262b3c8"
  echo
  echo "  -v  Target version of WordPress."
  echo "  -r  OPTIONAL: Revision from source. Defaults to 'trunk'"
  echo
  exit 1
}

print_heading() {
  echo "\n* $1"
}

revision="trunk"

while getopts ":v:r:" opt; do
  case ${opt} in
    v )
      version=$OPTARG
      ;;
    r )
      revision=$OPTARG
      ;;
    \? )
      echo "Invalid option: $OPTARG\n\n"
      usage
      ;;
    : )
      echo "Invalid option: $OPTARG requires an argument"
      usage
      ;;
  esac
done

if [ -z "$version" ]; then
  echo "Version (-v) needs to be set\n\n";
  usage
fi

directory="debug/test-jquery-updates/$version"

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

echo "\n\nDONE\n"