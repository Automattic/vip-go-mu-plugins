=== Sticky Custom Post Types ===
Contributors: superann
Donate link: http://superann.com/donate/?id=WP+Sticky+Custom+Post+Types+plugin
Tags: custom post types, sticky
Requires at least: 3.0
Tested up to: 3.2.1
Stable tag: trunk

Enables support for sticky custom post types.

== Description ==

This plugin adds a "Stick this to the front page" checkbox on the admin add/edit entry page of selected custom post types.

Set options to enable in Settings → Reading. Unless you're using custom queries to display your sticky posts, you probably want to check the option to add selected post types to the blog home.

Note: Sticky custom posts are stored in the global 'sticky_posts' option field, just like regular posts.

== Installation ==

1. Upload `sticky-custom-post-types.php` to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to 'Settings → Reading' and set your options.

== Frequently Asked Questions ==

None.

== Screenshots ==

None.

== Changelog ==

= 1.2.2 =
* Added custom post types to paged blog home.

= 1.2.1 =
* Fixed filter method (only applies when suppress_filters is false).

= 1.2 =
* Modified filter method to control display of selected custom post types on the blog home, and added an option to allow the user to enable/disable the filter.
* Moved plugin settings from 'Settings → Writing' to 'Settings → Reading'.

= 1.1 =
* Moved plugin settings from 'Settings → General' to 'Settings → Writing'.

= 1.0 =
* Initial version.