#!/usr/bin/env bash

set -o errexit   # exit on error

if [ $# -lt 1 ]; then
	echo "usage: $0 <plugin>"
	exit 1
fi

PLUGIN_SLUG=$1

update_wporg_plugin() {
	URL=https://downloads.wordpress.org/plugin/${PLUGIN_SLUG}.zip

	echo "Removing current dir: ${PLUGIN_SLUG}"
	rm -rf ${PLUGIN_SLUG}

	echo "Downloading latest release: ${URL}"
	wget $URL

	echo "Unzipping"
	unzip ${PLUGIN_SLUG}.zip
	rm ${PLUGIN_SLUG}.zip

	echo 
	echo "- Don't forget to update $PLUGIN_SLUG.php with the version number."
}

case $PLUGIN_SLUG in
	akismet | debug-bar | query-monitor)
		update_wporg_plugin
		;;
	
	*)
		echo "Unknown plugin; skipping"
		;;
esac
