=== WordPress.com VIP Support ===
Contributors: automattic
Tags: support, user
Requires at least: 4.8
Tested up to: 4.9
Requires PHP: 7.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Manages the WordPress.com Support Users on your site.

== Description ==

Manages the WordPress.com Support Users on your site.

== Installation ==

1. Upload the `vip-support` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Changelog ==

= 3.0 =
* Released Tuesday 15 March 2016
* CLI commands have changed
    * Create user command has different parameters
    * Added remove user command
* Makes verified VIP Support users super admin
* Bugfix: Fixed logic issue around logging
* Bugfix: Removed unnecessary `use` statements in CLI command

= 2.0.2 =
* Released Thursday 26 November 2015
* Bugfix: Refactor the role check to actually check the user's roles, rather than rely on Core `user_can` functions/methods

= 2.0.1 =
* Remove stray error_log call

= 2.0 =
* Allow users with Automattic email addresses to not be a support user
* Add a CLI command to force verify a user's email address
* Provide `is_valid_automattician` static method on `WPCOM_VIP_Support_User`
* Auto-verify an Automattician email address when they reset their password successfully via email

= 1.0 =
* Initial release
