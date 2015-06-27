=== Chartbeat ===
Contributors: chartbeat
Tags: chartbeat, analytics
Requires at least: 2.8
Tested up to: 3.5.1
Stable tag: 2.0.3

The Chartbeat plugin automatically adds real-time data and a top pages widget to your blog. See who’s on your site, what they’re doing - right now

== Description ==

[Chartbeat](http://chartbeat.com) is a real-time data service for your website, social streams, and iOS apps. Once you [become a Chartbeat member](http://chartbeat.com/signup), you can use this plugin to automatically add the necessary JavaScript to your WordPress blog. After installing the plugin, you’ll instantly see your site’s traffic, and audience behaviors. 

== Installation ==

1. Upload `chartbeat.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Under Settings > Chartbeat, add your User ID
4. You may also add your API key and configure the widget (API key must have "all" persmissions).
5. Select Newsbeat if you're using it
6. If you want to use the widget, drag it into your sidebar under Appearance > Widgets

*Note that you must have your timezone set correctly for events to work properly in
the historical chart.

== Frequently Asked Questions ==

= What is Chartbeat? =

We’re a real-time data service used by everyone from Foursquare to FoxNews to your mom’s blog (well, someone’s mom’s blog, if not yours.) 

Use Chartbeat (and this plugin) to see how many people are on your site and what they’re up to while they’re there, so you can take smarter real-time actions. Check it out for yourself by playing with our [demo](http://chartbeat.com/demo/) and sign up for [a free trial](https://chartbeat.com/signup/)!

= What does this plugin do? =

It’s an easy way for you to install the Chartbeat code you need in order to see what's happening on your WordPress site in real time. Make sure you [sign up for Chartbeat](http://chartbeat.com/signup) and have an active account first, or this plugin won’t work for your site.

= Will this plugin slow down my site? =

Nope. Chartbeat code is completely asynchronous, meaning it doesn't begin to run until everything else on your page has already loaded.

== Screenshots ==

1. Chartbeat Overview
1. Content View
3. Social View
4. Traffic Sources View
5. Geo View

== Changelog ==

= 1.0 =
* First verison. Please provide feedback.

= 1.2 =
* stable version

= 1.3 =
* 'trackadmin' option added by Jesse S. McDougall, jesse@catalystwebworks.com

= 1.4 =
* Security enhancements from automatic *

= 1.4.1 =
* Fix widget error in logs *

= 2.0 =
* Added Dashboard Widget, Active Visits in Post Board and Embedded Console

= 2.0.2 =
* Updated handling of window load event to ensure Chartbeat is always loaded

= 2.0.3 =
* Fixed issure where the Chartbeat console's iframe was too short to be usable