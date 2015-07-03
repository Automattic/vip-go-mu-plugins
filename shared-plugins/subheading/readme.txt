=== SubHeading ===
Contributors: stvwhtly
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=MTEDNQFNQVYLS
Tags: sub, heading, title, admin, template, page, post, byline, rss, custom, h2, headline, intro, text
Requires at least: 3.2.1
Tested up to: 3.6
Stable tag: 1.7.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Adds the ability to easily add and display a sub title/heading on any public post type.

== Description ==

This plugin uses a custom field to allow sub titles/headings to be added to any post type, including pages, posts and any public custom post type.

The custom subheading field is re-positioned so it is directly below the main title when editing.

Updates to your theme templates may be required in order for you to output the subheading values, please refer to the Installation instructions.

By default subheadings are also appended to RSS feeds and the admin edit post/page lists, these options and more can be modified via the settings page.

Following a plugin review by Alison Barrett (WordPress.com VIP) a number of improvements were introduced in version 1.7.

== Installation ==

Here we go:

1. Upload the `subheading` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Place `<?php if (function_exists('the_subheading')) { the_subheading('<p>', '</p>'); } ?>` in your template files where you want it to appear, or enable the `Automatically display subheadings before post content` option on the settings page.
4. Add the subheading content using the standard WordPress edit page.

The settings for this plugin are found by navigating to the `Settings` menu and selecting `Reading`, with the options displayed towards the bottom of the page.

If you are not within `the_loop`, you can use `get_the_subheading($postID);` to fetch the value for a particular page or post.

== Frequently Asked Questions ==

= How do I enable subheadings on posts and custom post types? =

By default subheadings are only enabled for pages, you can enable them for posts or any public custom post type via the `Settings > Reading` page.

Just check the box that says `Enable on Posts.` or the required post type.

= What custom field name does it use? =

The field name used is `_subheading`, the underscore prefix prevents it from being displayed in the list of custom fields.

= How can I append the subheading to my RSS feed? =

Check the RSS option on the settings page `Plugins > SubHeading > Append to RSS feeds`.

= What if I want to include shortcodes in my subheading? =

Check the apply shortcode filters option on the settings page `Plugins > SubHeading > Apply shortcode filters`.

This will apply any existing shortcode filters to the subheading value you have set.

= How can I prevent the subheading input moving to the top of the edit page? =

Some plugins will hide the element containing the post title, which is this element that the subheading input is appended to.

You can prevent the repositioning of the input via the options page.

= What are the `Before` and `After` inputs used for? =

If you are using the option to automatically wrap the subheading content, you can include custom content before and after the subheading is displayed.

For example, setting Before to `<h3>` and after to `</h3>` will wrap the subheading in a h3 tag.

= How can I stop subheadings appearing in places I don't want them to? =

Using the "Automatically display subheadings before post content." setting will prepend any subheading value before outputting any post content.

The output can be customised slightly using the "Before" and "After" fields, however if you prefer more customisation and control it is probably best to disable this setting and edit the output within your theme templates.

To display subheadings, place `<?php if (function_exists('the_subheading')) { the_subheading('<p>', '</p>'); } ?>` in your template files where you want the subheading to appear.

= Why do tags some tags disappear from my subheadings? =

By default the plugin uses the default list of allowed tags, which can result in certain tags such as `<br />` and `<p>` being removed from subheadings or settings.

This can be resolved by adding valid tags to the allowed list using either the `subheading_tags` or `subheading_settings_tags` filters.

If, for example, you wanted to enable the `<br />` tag in subheadings, include the following function to your theme functions.php file.

`add_filter( 'subheading_tags', function( $tags ) {
	$tags['br'] = array();
	return $tags;
} );`

Note here that the array key `'br'` is the tag name and the values array should be a list of valid attributes for that tag, for example `$tags['span'] = array('class' => array(), 'id' => array());`.

The list of default allowed tags for both subheadings and settings (before and after) is as follows:

`$allowedtags = array(
	'a' => array(
		'href' => array(),
		'title' => array()),
	'abbr' => array(
		'title' => array()),
	'acronym' => array(
		'title' => array()),
	'b' => array(),
	'blockquote' => array(
		'cite' => array ()),
	'cite' => array(),
	'code' => array(),
	'del' => array(
		'datetime' => array()),
	'em' => array(),
	'i' => array(),
	'q' => array(
		'cite' => array()),
	'strike' => array(),
	'strong' => array(),
);`

The settings validation additioanlly allows the following tags.

`...
	'h1' => array(
		'class' => array(),
		'id' => array()),
	'h2' => array(
		'class' => array(),
		'id' => array()),
	'h3' => array(
		'class' => array(),
		'id' => array()),
	'h4' => array(
		'class' => array(),
		'id' => array()),
	'h5' => array(
		'class' => array(),
		'id' => array()),
	'h6' => array(
		'class' => array(),
		'id' => array()),
	'p' => array(
		'class' => array(),
		'id' => array()),
...`

If you require the use of an additional tags or attributes, they will need to be added using the filters.

= How can I limit the length of the subheading? =

As of version 1.7.1 you can make use of the 'subheading' filter to manipulate the final output.

For example, limiting the subheading to 5 words can be done by adding the following to your theme functions.php file.

`add_filter( 'subheading', function( $value ) {
	return wp_trim_words( $value, 5 );
} );`

This example makes use of the [wp_trim_words](http://codex.wordpress.org/Function_Reference/wp_trim_words "WordPress Codex") function introduced in WordPress 3.3.

= Why do the code snippets / examples from the readme not work? =

If you are using older versions of PHP (< 5.3.0), you will not be able to make use of anonymous functions used in some of these examples.

Further information on the use of anonymous functions and WordPress can be read in the [WordPress Codex](http://codex.wordpress.org/Function_Reference/add_filter#Beware WordPress Codex Reference).

== Upgrade Notice ==

= 1.7 =
Stricter validation rules were added to the before and after settings values, please refer to the FAQs if your values are being stripped.

== Screenshots ==

1. The subheading option is displayed directly below the main title.
2. Settings are managed via the Settings > Reading page.

== Changelog ==

= 1.7.3 =
* Fixed a bug with the JavaScript used by the settings page, which would incorrectly toggle the auto append value.

= 1.7.2 =
* Markup tags are now removed from the subheadings displayed on the admin post list columns.
* Added FAQ to explain some information on the use of anonymous functions used in examples.

= 1.7.1 =
* Added new `subheading` filter to allow output to be manipulated.
* Modified upgrade process to resolve some upgrade issues.
* Added some basic inline documentation to the main plugin file.

= 1.7 =
* Replaced WP_PLUGIN_URL with plugins_url function call.
* Added validation to settings post types and sanitisation of before and after values.
* Adjusted information relating to the addition of allowed tags in the FAQs.
* Changed capability checking on save to post type capabilities rather than edit_post and edit_page capabilities.
* Renamed index.php to subheading.php for compatability with the WordPress VIP plugins directory. 
* Changed default options value to array to prevent in_array error notices.
* Fixed warning in settings field output.

= 1.6.8 =
* Fixed undefined variable warning using the get_the_subheading() function.

= 1.6.7 =
* Added backwards compatibility for is_main_query for WP version prior to 3.3.

= 1.6.6 =
* Fixed incorrect FAQ heading.
* Modified readme changelog.
* Fixed error during uninstall processs.

= 1.6.5 =
* Updated wrong information in readme file relating to the location of the settings page which moved in version 1.6.
* Added missing/unclosed heading tag for last FAQ in the readme file.
* Escaped tags in readme file changelog.

= 1.6.4 =
* Fixed readme file syntax relating to new FAQ added in version 1.6.3.

= 1.6.3 =
* Added `is_main_query()` check to `the_content` filter to ensure subheadings are only appended when cycling through the primary loop.
* Added valid tags filter to allow additional tags to be used in subheadings. See "Why do tags such as `<br />` and `<p>` disappear from my subheadings?" FAQ for more information.

= 1.6.2 =
* Renamed the "Wrap the SubHeading content." setting to "Automatically display subheadings before post content.".
* Modified activate function.
* Renamed some plugin class methods.
* Added FAQ regarding customisation of the subheading output.

= 1.6.1 =
* Corrected location of assets directory.

= 1.6 =
* Created uninstall.php to fix incorrectly referenced uninstall hook.
* Moved plugin settings to the `Settings > Reading` section.
* Added ability to enable subheadings on all public post types, including custom post types.
* Modified donate link.
* Updated screenshots and added plugin directory banner.
* Minor code reformatting.

= 1.5 =
* Added ability to allow subheading to be searched.
* Bug fixed where multiple subheadings could be stored for a single post.

= 1.4.2 =
* Replaced all remaining PHP short tags.

= 1.4.1 =
* Fixed issue where subheading was appended to multiple columns on admin edit pages.

= 1.4 =
* Added auto inclusion option of the subheading.
* Removed tidy option, all data is now removed during the uninstall process.

= 1.3.1 =
* Missed error reporting on nonce check.

= 1.3 =
* Fixed errors when error reporting is set to all.
* Fixed admin post/pages list display conflicting with other custom columns.
* Tested the plugin in WordPress 2.9.

= 1.2.2 =
* Enabled subheadings on posts by default.

= 1.2.1 =
* Fixed plugin settings link on plugins page.

= 1.2 =
* Added auto shortcode parsing option.
* Appended link to settings on plugins overview page.
* Modified tidy setting so that options are not reset when updating the plugin.

= 1.1 =
* Added option to allow headings to be completely removed when deactivating the plugin.
* Inclusion of Donate link ;)

= 1.0 =
* Converted plugin to a class based structure.
* Added new plugin settings pages with default actions.

= 0.3.3 =
* Added ability to prevent repositioning of the subheading input on edit page.
* Subheadings are now displayed on admin edit posts / pages lists.

= 0.3.2 =
* Fixed `get_the_subheading` function to return correctly.

= 0.3.1 =
* Fixed character encoding issue.

= 0.3 =
* Appended subheading to RSS feed post title.

= 0.2.4 =
* Double encoding bug fix.

= 0.2.3 =
* Fixed / added escaping to admin output (via achellios) and ability to use HTML tags.

= 0.2.2 =
* Bug fix nonce checking.

= 0.2.1 =
* Bug fix to prevent output of before and after text with no subheading value.

= 0.2 =
* Tested up to 2.8.5 and began optimisation of the included files.

= 0.1 =
* This is the very first version.