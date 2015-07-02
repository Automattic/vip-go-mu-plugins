=== Blimply ===
Contributors: rinatkhaziev
Tags: push, urban airship, notifications, widget, iphone, android, blackberry, ios
Requires at least: 3.3
Tested up to: 4.0
Stable tag: 0.5.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Blimply will allow you to send push notifications to your mobile users utilizing Urban Airship API.

== Description ==

You will need an [Urban Airship](http://urbanairship.com/) account in order to be able to use this plugin. The plugin features the ability to send  push notifications for posts/pages/custom post types, and a handy Dashboard widget.


== Installation ==

1. Upload `blimply` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Set application key and application MASTER secret in 'Settings' -> 'Blimply Settings'
1. Add Urban Airship tags (optional)
1. Keep in mind that tags names will be slugified when added to Urban Airship App. So for proper tag handling in your apps use tag->slug not tag->name when you register tags for device
1. Set sounds for your tags
1. Enjoy responsibly

== Screenshots ==

1. Per-post Push notification
2. Dashboard widget
3. Blimply Settings

== Translations ==

There's English .pot file in lib/languages. Translations are welcome.

== Contributors ==

Plugin is maintained on [Github](https://github.com/rinatkhaziev/blimply). Comments, issues, and pull requests are welcome.

== Changelog ==

= 0.5.1 =
* Implemented character limit for post meta box

= 0.5 =
* Added 'blimply_enabled_post_types' filter to enable/disable meta box per post type

= 0.4 =
* Added Blackberry support
* Option to limit Dashboard Widget Push to a certain amount of characters. Default is 140.

= 0.3.1 =
* Fixed an issue with html entities being displayed not properly.
* Fixed an issue where instead of post permalink, revision permalink was being sent

= 0.3 =
* Now include Android payload as well as iOS
* Various bugfixes (props @voceconnect, @danielbachhuber )


= 0.2.4 =

* Fix potential Fatal Error with dependencies

= 0.2.3 =

* Pushes that are sent from a post will now include permalink of that post. That gives an ability to open the link in your app
* Pushes use default sound. Also, you can specify a sound per tag (see Push Sounds section)

= 0.2.2 =

* Register taxonomy on init instead of admin_init

= 0.2.1 =

* Fixed a bug that resulted in double escaping of characters that should be properly escaped
* Added an option to turn on/off broadcast push notifications

= 0.2 =

* No need for PEAR dependencies anymore

= 0.1 =

* Initial release

== Frequently Asked Questions ==
