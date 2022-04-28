=== Debug Bar Cron ===
Contributors: tollmanz, helen, 10up
Donate Link: http://wordpress.org
Tags: debug bar, cron
Requires at least: 3.3
Tested up to: trunk
Stable tag: 0.1.3
Requires PHP: 5.2.4
License: GPLv2 or later

Debug Bar Cron adds a new panel to Debug Bar that displays information about WP scheduled events.

== Description ==

Debug Bar Cron adds information about WP scheduled events to a new panel in the Debug Bar. This plugin is an extension for
Debug Bar and thus is dependent upon Debug Bar being installed for it to work properly.

Once installed, you will have access to the following information:

* Number of scheduled events
* If cron is currently running
* Time of next event
* Current time
* List of custom scheduled events
* List of core scheduled events
* List of schedules

== Installation ==

1. Install Debug Bar if not already installed (http://wordpress.org/extend/plugins/debug-bar/)
2. Upload the `debug-bar-cron` folder to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. View the WP schedule events information in the "Cron" panel in Debug Bar

== Frequently Asked Questions ==

= Is debugging cron easier with this plugin? =

Yes

== Screenshots ==

1. The Debug Bar Cron panel.
1. Indicator for events without associated actions.

== Changelog ==

= Trunk =
* Fix compatibility with the [Plugin Dependencies](http://wordpress.org/plugins/plugin-dependencies/) plugin
* Defer to just in time loading of translations for WP > 4.5.

= 0.1.3 =

* Fixed 'Array to string conversion' error when Cron job arguments are in a multi-dimensional array - props [Jrf](http://profiles.wordpress.org/jrf), [ethitter](http://profiles.wordpress.org/ethitter), and [mintindeed](http://profiles.wordpress.org/mintindeed).
* Fixed a number of HTML validation errors - props [Jrf](http://profiles.wordpress.org/jrf).

= 0.1.2 =
* Added indicators for missed events

= 0.1.1 =
* Readme updates

= 0.1 =
* Initial release

== Upgrade Notice ==

= 0.1.2 =
Adds indicators for missed events

= 0.1 =
Initial Release

