=== WP Options Importer ===
Contributors: mboynes,alleyinteractive
Tags: options, importer, exporter, export, import, migrate, settings, wp_options
Requires at least: 3.8
Tested up to: 3.9
Stable tag: 5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Export and import WordPress Options.

== Description ==

WordPress can presently export all of its content via WXR, and then import that
through the WordPress Importer plugin. That process includes all posts, terms,
menus, comments, and users, but it doesn't touch options. In addition to
general settings, options can include widget configurations, plugin settings,
theme settings, and lots more. This can be very time-consuming to migrate
manually. WP Options Importer aims to fill that void and save us all a lot of
time.

WP Options Importer allows you to export all options to a JSON file, and then
you can selectively import them into another WordPress installation. The import
process is very transparent, and it even shows you what data you'll be
importing. Lastly, it gives you the option to override existing settings or to
skip options that already exist.


== Installation ==

1. Upload the plugin to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Tools &rarr; Export** and choose "Settings" to export options,
or navigate to **Tools &rarr; Import** and choose "Settings" to import options.

== Frequently Asked Questions ==

= When I import the default options, [some plugin]'s settings don't transfer. What gives? =

The default options are core options, or those which a plugin has indicated
are safe to import. You can choose "Specific Options" when importing to
manually select those which you need to import.

= I'm the author of [some plugin]. Can you add my settings to the default list? =

No, but you can! We provide a filter, `options_import_whitelist` for you to add
your options to the default list. Here's an example one might add to their
plugin:

	function my_awesome_plugin_options( $options ) {
		$options[] = 'my_awesome_plugin';
		return $options;
	}
	add_filter( 'options_import_whitelist', 'my_awesome_plugin_options' );

Similarly, if you don't want someone to ever import an option, you can add it
to the blacklist using the `options_import_blacklist` filter. As above, it
would look something like this:

	function my_awesome_plugin_blacklist_options( $options ) {
		$options[] = 'my_awesome_plugin_edit_lock';
		return $options;
	}
	add_filter( 'options_import_blacklist', 'my_awesome_plugin_blacklist_options' );

= I operate a multisite network and some options should *never* be able to be exported or imported by the site owner. Can I prevent that? =

You have two options for both exports and imports.

**Imports**

First, you can use the `options_import_blacklist` filter
and add any options to that array (which is empty by default). If your users
have access to theme or plugin code, this isn't 100% safe, because they could
override your blacklist using the same filter. In those cases, there's an
emergency ripcord where you can disable options from ever being imported. To
use this, define the constant `WP_OPTION_IMPORT_BLACKLIST_REGEX` (you'll
probably want to do this in an mu-plugin) and set it to a regular expression.
Anything matching this expression will be skipped. For example:

	define( 'WP_OPTION_IMPORT_BLACKLIST_REGEX', '/^(home|siteurl)$/' );

**Exports**

Exactly the same as with imports. The filter is `options_export_blacklist`,
and the constant is `WP_OPTION_EXPORT_BLACKLIST_REGEX`.


== Screenshots ==

1. "Options" is seamlessly integrated as a choice when exporting.
2. "Options" is seamlessly included in the list of importers.
3. Once you upload the JSON file, you're presented with a choice of which
options you want to import and if you want to override existing options.
4. If you choose to import "Specific Options", you're provided with a list of
everything in the JSON file. Check the box next to those you want included, or
uncheck those which you don't want to include.

== Changelog ==

= 5 =
* Added WP_OPTION_EXPORT_BLACKLIST_REGEX
* Breaking: Changed the `options_export_exclude` filter to `options_export_blacklist` to be consistent with imports.

= 4 =
* After file upload, store data in transient and immediately delete the file so it doesn't linger on the server.

= 3 =
* Added blacklists
* Fixing bug where plugin wouldn't show in multisite when WP Importer wasn't active.
* Misc bug fixes

= 2 =
* Spit & polish
* Improved error handling
* Added file cleanup on completion
* Misc bug fixes

= 1 =
* Brand new!

== Upgrade Notice ==

= 5 =
**Breaking:** Changed the `options_export_exclude` filter to `options_export_blacklist` to be consistent with imports.