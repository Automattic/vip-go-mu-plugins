# Frontend Uploader

## Description

This plugin gives you an ability to easily accept, moderate and publish user generated content (currently, there are 3 modes: media, post, post + media). The plugin allows you to create a front end form with multiple fields (easily customizable with shortcodes). You can limit which MIME-types are supported for each field. All of the submissions are safely held for moderation in Media/Post/Custom Post Types menu under a special tab "Manage UGC". Review, moderate and publish. It's that easy!

## Installation

1. `git clone https://github.com/rinatkhaziev/wp-frontend-uploader.git` in your WP plugins directory
1. `git submodule update --init --recursive` in the plugin dir to get dependencies
1. Activate the plugin
1. Set the settings
1. Enjoy

## Upgrade instructions

1. Pull as usual
2. Do `git submodule -q foreach git pull -q origin master` to update submodules
3. ...
4. Profit

## Developers

Miss a feature? Pull requests are welcome.