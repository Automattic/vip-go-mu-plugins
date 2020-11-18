=== Test jQuery Updates ===
Contributors: wordpressdotorg, azaozz
Tags: jquery
Requires at least: 5.4
Tested up to: 5.5
Stable tag: 1.0.1
Requires PHP: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Test different versions of jQuery and jQuery UI.

== Description ==

Test jQuery Updates is an official plugin by the WordPress team that is intended for testing of different versions of jQuery and jQuery UI before updating them in WordPress. It is not intended for use in production.

It includes jQuery 3.5.1, jQuery Migrate 3.3.0, and jQuery UI 1.12.1. jQuery UI has been re-built for full backwards compatibility with WordPress.

To test:

1. Use the current version of jQuery in WordPress but disable jQuery Migrate.
2. Latest jQuery, currently 3.5.1, with the latest jQuery Migrate.
3. Latest jQuery with the latest jQuery Migrate and latest jQuery UI.
4. As above without jQuery Migrate.

If you find a bug in a jQuery related script [please report it](https://github.com/WordPress/wp-jquery-update-test). Instructions are available at the plugin's settings page.

= Default settings =

When activated this plugin will not replace the current jQuery but will disable jQuery Migrate. For more information about jQuery Migrate please visit: [https://github.com/jquery/jquery-migrate/](https://github.com/jquery/jquery-migrate/).

== Changelog ==

= 1.0.1 =
Update for compatibility with WordPress 5.5-beta1 and newer, allows re-enabling of jQuery Migrate 1.4.1.

= 1.0.0 =
Initial release.
