=== Akismet Spam Protection ===
Contributors: matt, ryan, andy, mdawaffe, tellyworth, josephscott, lessbloat, eoigal, cfinke, automattic, jgs, procifer, stephdau
Tags: comments, spam, antispam, anti-spam, contact form, anti spam, comment moderation, comment spam, contact form spam, spam comments
Requires at least: 4.6
Tested up to: 5.8
Stable tag: 4.1.10
License: GPLv2 or later

The best anti-spam protection to block spam comments and spam in a contact form. The most trusted antispam solution for WordPress and WooCommerce.

== Description ==

Akismet checks your comments and contact form submissions against our global database of spam to prevent your site from publishing malicious content. You can review the comment spam it catches on your blog's "Comments" admin screen.

Major features in Akismet include:

* Automatically checks all comments and filters out the ones that look like spam.
* Each comment has a status history, so you can easily see which comments were caught or cleared by Akismet and which were spammed or unspammed by a moderator.
* URLs are shown in the comment body to reveal hidden or misleading links.
* Moderators can see the number of approved comments for each user.
* A discard feature that outright blocks the worst spam, saving you disk space and speeding up your site.

PS: You'll be prompted to get an Akismet.com API key to use it, once activated. Keys are free for personal blogs; paid subscriptions are available for businesses and commercial sites.

== Installation ==

Upload the Akismet plugin to your blog, activate it, and then enter your Akismet.com API key.

1, 2, 3: You're done!

== Changelog ==

= 4.1.10 =
*Release Date - 6 July 2021*

* Simplified the code around checking comments in REST API and XML-RPC requests.
* Updated Plus plan terminology in notices to match current subscription names.
* Added `rel="noopener"` to the widget link to avoid warnings in Google Lighthouse.
* Set the Akismet JavaScript as deferred instead of async to improve responsiveness.
* Improved the preloading of screenshot popups on the edit comments admin page.

= 4.1.9 =
*Release Date - 2 March 2021*

* Improved handling of pingbacks in XML-RPC multicalls

= 4.1.8 =
*Release Date - 6 January 2021*

* Fixed missing fields in submit-spam and submit-ham calls that could lead to reduced accuracy.
* Fixed usage of deprecated jQuery function.

= 4.1.7 =
*Release Date - 22 October 2020*

* Show the "Set up your Akismet account" banner on the comments admin screen, where it's relevant to mention if Akismet hasn't been configured.
* Don't use wp_blacklist_check when the new wp_check_comment_disallowed_list function is available.

= 4.1.6 =
*Release Date - 4 June 2020*

* Disable "Check for Spam" button until the page is loaded to avoid errors with clicking through to queue recheck endpoint directly.
* Add filter "akismet_enable_mshots" to allow disabling screenshot popups on the edit comments admin page.

For older changelog entries, please see the [additional changelog.txt file](https://plugins.svn.wordpress.org/akismet/trunk/changelog.txt) delivered with the plugin.
