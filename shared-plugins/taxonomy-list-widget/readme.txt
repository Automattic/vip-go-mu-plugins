=== Taxonomy List Widget ===
Contributors: ethitter
Donate link: https://ethitter.com/plugins/taxonomy-list-widget/
Tags: tag, tags, taxonomy, sidebar, widget, widgets, list
Requires at least: 2.8
Tested up to: 4.2
Stable tag: 1.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Creates a list (bulleted, number, or custom) of non-hierarchical taxonomies as an alternative to the term (tag) cloud. Formerly known as Tag List Widget.

== Description ==

Creates lists of non-hierarchical taxonomies (such as `post tags`) as an alternative to term (tag) clouds. Multiple widgets can be used, each with its own set of options.

Numerous formatting options are provided, including maximum numbers of terms, term order, truncating of term names, and more. List styles are fully customizable, with built-in support for bulleted lists and numbered lists.

Using the `taxonomy_list_widget` function, users can generate lists for use outside of the included widget.

**Only use version 1.2 or higher with WordPress 4.2 and later releases.** WordPress 4.2 changed how taxonomy information is stored in the database, which directly impacts this plugin's include/exclude term functionality.

This plugin was formerly known as the `Tag List Widget`. It was completely rewritten for version 1.0.

== Installation ==

1. Upload taxonomy-list-widget.php to /wp-content/plugins/.
2. Activate plugin through the WordPress Plugins menu.
3. Activate widget from the Appearance > Widgets menu in WordPress.
4. Set display options from the widget's administration panel.

== Frequently Asked Questions ==

= What happened to the Tag List Widget plugin? =

Since I first wrote the Tag Dropdown Widget plugin upon which this plugin is based (in November 2009), WordPress introduced custom taxonomies and, as more-fully discussed below, saw a new widgets API overtake its predecessor. As part of the widgets-API-related rewrite, I expanded the plugin to support non-hierarchical custom taxonomies, which necessitated a new name for the plugin.

= Why did you rewrite the plugin? =

When I first wrote the Tag Dropdown Widget plugin, which I later forked to create the Tag List Widget plugin, WordPress was amidst a change in how widgets were managed. I decided to utilize the old widget methods to ensure the greatest compatibility at the time. In the nearly two years since I released the first version of this plugin, the new widget system has been widely adopted, putting this plugin at a disadvantage. So, I rewrote the plugin to use the new widget API and added support for non-hierarchical taxonomies other than just post tags.

= I upgraded to version 1.0 and all of my widgets disappeared. What happened? =

As discussed above, WordPress' widget system has changed drastically since I first released this plugin. To facilitate multiple uses of the same widget while allowing each to maintain its own set of options, the manner for storing widget options changed. As a result, there is no practical way to transition a widget's options from version 0.3.1 to 1.0.

= If my theme does not support widgets, or I would like to include the list outside of the sidebar, can I still use the plugin? =

Insert the function `<?php if( function_exists( 'taxonomy_list_widget' ) ) echo taxonomy_list_widget( $args, $id ); ?>` where the list should appear, specifying `$args` as an array of arguments and, optionally, `$id` as a string uniquely identifying this list.

* taxonomy - slug of taxonomy for list. Defaults to `post_tag`.
* select_name - name of first (default) option in the list. Defaults to `Select Tag`.
* max_name_length - integer representing maximum length of term name to display. Set to `0` to show full names. Defaults to `0`.
* cutoff - string indicating that a term name has been cutoff based on the `max_name_length` setting. Defaults to an ellipsis (`&hellip;`).
* limit - integer specifying maximum number of terms to retrieve. Set to `0` for no limit. Defaults to `0`.
* orderby - either `name` to order by term name or `count` to order by the number of posts associated with the given term. Defaults to `name`.
* order - either `ASC` for ascending order or `DESC` for descending order. Defaults to `ASC`.
* threshold - integer specifying the minimum number of posts to which a term must be assigned to be included in the list. Set to `0` for now threshold. Defaults to `0`.
* incexc - `include` or `exclude` to either include or exclude the terms whose IDs are included in `incexc_ids`. By default, this restriction is not enabled.
* incexc_ids - comma-separated list of term IDs to either include or exclude based on the `incexc` setting.
* hide_empty - set to `false` to include in the list any terms that haven't been assigned to any objects (i.e. unused tags). Defaults to `true`.
* post_counts - set to `true` to include post counts after term names. Defaults to `false`.
* delimiter - sets list style. Native options are `ul`, `ol`, and `nl` for bulleted list, numbered list, and line breaks, respectively. By passing an array with keys `before_list`, `after_list`, `before_item`, and `after_item`, you can completely customize the list style.
* rel - either `dofollow` or `nofollow`. Can still use `taxonomy_list_widget_link_rel` filter to specify link relationship.

= Why is the TLW_direct() function deprecated? =

Version 1.0 represents a complete rewrite of the original Tag List Widget plugin. As part of the rewrite, all prior functions for generating tag lists were deprecated, or marked as obsolete, because they are unable to access the full complement of features introduced in version 1.0. While the functions still exist, their capabilities are extremely limited and they should not be replaced with `taxonomy_list_widget()`.

= Where do I obtain a term's ID for use with the inclusion or exclusion options? =

Term IDs can be obtained in a variety of ways. The easiest is to visit the taxonomy term editor (Post Tags, found under Posts, for example) and, while hovering over the term's name, looking at your browser's status bar. At the very end of the address shown in the status bar, the term ID will follow the text "tag_ID."

You can also obtain the term ID by clicking the edit link below any term's name in the Post Tags page. Next, look at your browser's address bar. At the very end of the address, the term ID will follow the text "tag_ID."

= I'd like more control over the tags shown in the list. Is this possible? =

This plugin relies on WordPress' `get_terms` function (http://codex.wordpress.org/Function_Reference/get_terms). To modify the arguments passed to this function, use the `taxonomy_list_widget_options` filter to specify any of the arguments discussed in the Codex page for `get_terms`.

To make targeting a specific filter reference possible should you use multiple instances of the list (multiple widgets, use of the `taxonomy_list_widget` function, or some combination thereof), the filter provides a second argument, `$id`, that is either the numeric ID of the widget's instance or the string provided as the second argument to `taxonomy_list_widget`.

== Changelog ==

= 1.2 =
* Update for WordPress 4.2 to handle term splitting in the plugin's include/exclude functionality. Details at https://make.wordpress.org/core/2015/02/16/taxonomy-term-splitting-in-4-2-a-developer-guide/.

= 1.1.2 =
* Correct problem in WordPress 3.3 and higher that resulted in an empty taxonomy dropdown.
* Remove all uses of PHP short tags.

= 1.1.1 =
* Allow empty title in widget options. If empty, the `taxonomy_list_widget_title` filter isn't run.

= 1.1 =
* Provide control over link relationship (`dofollow` and `nofollow`) in widget. This capability is still available via the `taxonomy_list_widget_link_rel` filter.

= 1.0.1 =
* Fix fatal error in older WordPress versions resulting from PHP4 and PHP5 constructors existing in widget class.

= 1.0.0.2 =
* Fix bug in post count threshold that resulted in no terms being listed.

= 1.0.0.1 =
* Fix fatal error

= 1.0 =
* Completely rewritten plugin to use WordPress' newer Widgets API.
* Drop support for WordPress 2.7 and earlier.
* Add support for all public, non-hierarchical custom taxonomies, in addition to Post Tags.
* Introduce new, more flexible function for manually generating lists.
* Fixed persistent bugs in the include/exclude functionality.
* Widget admin is translation-ready.

= 0.3.1 =
* Replace id on list items with class.

= 0.3 =
* Reduced variables stored in database to two.

= 0.2 =
* Added function `TLW_direct`

== Upgrade Notice ==

= 1.2 =
Updated for WordPress 4.2. Only version 2.2 or higher should be used with WordPress 4.2 or higher, otherwise included/excluded terms may reappear in dropdowns. This is due to WordPress splitting shared terms, as detailed at https://make.wordpress.org/core/2015/02/16/taxonomy-term-splitting-in-4-2-a-developer-guide/.

= 1.1.2 =
Corrects a problem in WordPress 3.3 and higher that resulted in an empty taxonomy dropdown. Also removes all uses of PHP short tags.

= 1.1.1 =
Allows empty title in widget options. If empty, the `taxonomy_list_widget_title` filter isn't run.

= 1.1 =
Adds control over link relationship (`dofollow` and `nofollow`) in the widgets' options. This capability is still available via the `taxonomy_list_widget_link_rel` filter.

= 1.0.1 =
Fixes a backwards-compatibility problem in the widget class that generated fatal errors in WordPress 3.0 and earlier.

= 1.0.0.2 =
Fixes a minor bug in the post count threshold setting.

= 1.0.0.1 =
Corrects fatal error in plugin.

= 1.0 =
The plugin was renamed, completely rewritten, and drops support for WordPress 2.7 and earlier. Upgrading will delete all of your existing widgets; see the FAQ for an explanation. Review the changelog and FAQ for more information.
