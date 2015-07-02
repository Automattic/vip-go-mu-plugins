# Blimply
[![Build Status](https://travis-ci.org/rinatkhaziev/blimply.png?branch=master)](https://travis-ci.org/rinatkhaziev/blimply)


## Description

Blimply is a plugin that will allow you to send push notifications to your mobile users utilizing Urban Airship API. [Urban Airship](http://urbanairship.com/) account in order to be able to use this plugin. The plugin features the ability to make a push for posts/pages/custom post types, and a handy Dashboard widget.

##  Initial installation

1. `git clone https://github.com/rinatkhaziev/blimply.git` in your WP plugins directory
1. `git submodule update --init --recursive` in the plugin dir to get dependencies
1. Activate the plugin
1. Set the settings
1. Enjoy

## Upgrade instructions

1. Pull as usual
1. Do `git submodule -q foreach git pull -q origin master` to update submodules
1. ...
1. Profit

## Developers

Miss a feature? Pull requests are welcome.

## Future improvements
* Multiple Airship apps support
* Rich Push
* Scheduled pushes
* Geolocated pushes