=== Ooyala Video ===
Ported By: Dave Searle
Contributors: ooyala, dsearle, automattic, PeteMall, Range
Tags: embedding, video, embed, portal, ooyala, shortcode
Requires at least: 3.0
Tested up to: 3.9
Stable tag: 1.7.5

Easy embedding of videos for the Ooyala Video Platform.
Browse your Ooyala videos, and easily insert them into your posts and page.


== Description ==
Easy embedding of videos for the Ooyala Video Platform.  
Browse and search your Ooyala's videos, and easily insert them into your posts and page.  
Upload videos to your Ooyala account directly from within WordPress.  

== Installation ==

Copy the subfolder "ooyala-video" with all included files into the "wp-content/plugins" folder of WordPress. Activate the plugin and setup your Backlot pcode and secret code in the Ooyala Settings screen. You will find your pcode and secret code under Account -> Developers in Backlot.

== Screenshots ==

1. A new media upload button is available to launch the Ooyala Search and Insertion GUI.
2. The Ooyala GUI allows you to search and insert videos from your Ooyala account. You can search by keyword or choose the last 8 videos uploaded to the account.

== Changelog ==

= 1.7.5 =
* Set uploaded status for an asset

= 1.7.4 =
* Fix issues with uploading new videos

= 1.7.3 =
* Fix PHP 5.2.x compatibility issue

= 1.7.2 =
* Set default platform to html5-fallback
* Error checking for API responses

= 1.7.1 =
* Error checking for API credentials

= 1.7 =
* Auto populate the default player_id for V3 embeds
# Support for player_id when embedding a video
* Support for hosted_at

= 1.6 =
* Support for V3 Embeds
* Support for Backlot API v2
* Added Range as contributor

= 1.5 =
* Added PeteMall as contributor
* Fixed localization for menu strings
* Deprecated config.php - API code should be entered through the settings screen.

= 1.4.3 =
* Added Automattic as contributor

= 1.4.2 = 
* Provided more robustness around the API calls being made.
* Updated documentation to direct users to the V1 API keys.

= 1.4.1 = 
* Fixed a deprecated function call
* Changed the default timeout for the remote request to 10 seconds. If you need to further extend the timeout for any reason, you can also use the 'ooyala_http_request_timeout' filter.


= 1.4 =

**New Features:**

* You can now set a video thumbnail as your post featured image (if your theme supports featured images)
* The API codes can now be entered directly in the settings screen. If you already have config.php file set, the plugin will read the values from there.

**Bugfixes and Improvements:**

* The plugin uses a media screen instead of a tinmyce button, and the popup look more like WP's own media screens
* The shortcode has been converted to use WP's shortcode API. Old version compatibility is maintained.
* Use WordPress's content_width as default if it is set for the video width.
* JavaScript can now be localized as well.
* Switched OoyalaBacklotAPI to use WP's HTTP class

= 1.3 =
* Pulled config into config.php
* Added Upload functionality to second tab with example label config

= 1.2 =
* Added screenshots to package for SVN. No other changes made.

== Upgrade Notice ==

= 1.4 =
The plugin has been updated for WordPress 3.0 and newer.

== Configuration ==

The configuration options are simple and do not need explanation. See the administration panel of the plugin in your WP admin frontend.
