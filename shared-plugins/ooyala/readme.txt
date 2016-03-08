=== Ooyala ===
Contributors: ooyala, thinkoomph, balbuf, bendoh
Tags: video, media, ooyala
Requires at least: 3.9
Tested up to: 4.2.3
Stable tag: 2.1.1
License: GPLv2 or later

Connect your Ooyala account to embed and upload assets directly from WordPress.

== Description ==

Ooyala harnesses the power of big data to help broadcasters, operators and media companies build more engaged audiences and monetize video with personalized, interactive experiences for every screen. We go beyond traditional online video platforms, providing software and services combining best-of-breed technologies with industry-leading video analytics to help our customers optimize and automate video programming, video streaming and video syndication. Ooyala is an independent subsidiary of Telstra.

The Ooyala WordPress plugin integrates seamlessly with your Wordpress 3.5+ Media Manager and lets you create posts with assets from your Ooyala account–-even uploading new videos–-without ever leaving your WordPress site.


Using the plugin once installed

1.  Go to a new or existing post.

2.  Select "Embed Ooyala" above the composition area, or access Ooyala from the Media tab.

3.  Search for assets by Title, Description, or Embed Code.

4.  Select your desired Player Display options and insert the embed shortcode.

5.  You can upload new videos straight to your account via the upload tab!


== Installation ==

1. Upload ooyala to /wp-content/plugins/.

2. Activate plugin through the WordPress Plugins menu.

3. Enter your Ooyala API credentials on the Media Settings page.

4. Click on "Embed Ooyala" in edit post screen to browse your asset library.


== Changelog ==

= 2.1.1 =
* Save references to Ooyala players in 'ooyalaplayers' global JS var.

= 2.1.0 =
* Add "Set Featured Image" button to video thumbnails, allowing users to set thumbnails.
* Add "Auto" sizing capability (by default) to scale videos down responsively.

= 2.0.2 =
* Use HTTPS for JavaScripts to fix security compatibility for sites served via HTTPS.

= 2.0.1 =
* Backwards compatibility fixes to honor existing settings, including default player_id, video_width, and previously entered API key and secret.

= 2.0 =
* Initial release of Ooyala plugin redesign.

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

= 2.0 =
The plugin has been completely revamped from scratch to integrate with the WordPress Media Manager.

= 1.4 =
The plugin has been updated for WordPress 3.0 and newer.
