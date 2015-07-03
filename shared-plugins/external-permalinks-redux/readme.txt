=== External Permalinks Redux ===
Contributors: ethitter, thinkoomph
Donate link: http://www.thinkoomph.com/plugins-modules/external-permalinks-redux/
Tags: link, redirect, external link, permalink
Requires at least: 3.0
Tested up to: 3.6
Stable tag: 1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Allows you to point WordPress objects (posts, pages, custom post types) to a URL of your choosing.

== Description ==

Allows users to point WordPress objects (posts, pages, custom post types) to a URL of their choosing, which is particularly useful for injecting non-WordPress content into loops. The object appears normally in any loop output, but visitors to the object will be redirected to the specified URL. The plugin also allows you to choose the type of redirect, either temporary (302), or permanent (301).

Through a filter, the External Permalinks Redux meta box can easily be added to custom post types. There is also a function available for use with WordPress' `add_meta_box` function.

This plugin was originally written for use on WordPress.com VIP. It is inspired by and backwards-compatible with Mark Jaquith's Page Links To plugin, meaning users can switch between plugins without risk of losing any existing external links.

This plugin is translation-ready.

== Installation ==

1. Upload external-permalinks-redux.php to /wp-content/plugins/.
2. Activate plugin through the WordPress Plugins menu.

== Frequently Asked Questions ==

= How can I add support for my custom post type? =
Using the `epr_post_types` filter, one can modify the default array of object types (`post` and `page`) to include additional custom post types or remove the plugin from one of the default post types.

= What other filters does this plugin include? =
* `epr_meta_key_target` - modify the meta key associated with the external URL
* `epr_meta_key_type` - modify the meta key associated with the redirect type
* `epr_status_codes` - modify array of available status codes used when redirect is issued

== Changelog ==

= 1.1 =
* Introduce a filter to change the metabox title for clarity. Does break translation for that string if used.
* Add additional HTML classes in the metabox to aid customization.
* Coding standards and PHPDoc cleanup.

= 1.0.4 =
* Implement singleton pattern for instantiation. Thanks batmoo.

= 1.0.3 =
* Increase priority of `init` action to ensure that the filters it contains are available to other plugins. Thanks batmoo.

= 1.0.2 =
* Add status codes filter. Thanks [danielbachhuber](http://wordpress.org/support/topic/plugin-external-permalinks-redux-support-custom-status-codes).
* Correct translation string implementation, removing variable name.
* Miscellaneous cleanup, such as whitespace removal.

= 1.0.1 =
* Add shortcut function for registering meta box on custom post types. This is included as an alternative to the `epr_post_types` filter discussed in the FAQ.

= 1.0 =
* Initial release in WordPress.org repository.
* Rewrote original WordPress.com VIP plugin into a class and added support for custom post types.

== Upgrade Notice ==

= 1.0.4 =
Implements singleton pattern for instantiation. No functional changes are included in this release.

= 1.0.3 =
Ensures that filters are available to plugins and themes. Recommended for anyone trying to hook to those filters.