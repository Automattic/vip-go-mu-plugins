=== Parse.ly ===
Contributors: parsely_mike
Tags: analytics, post, page
Requires at least: 3.0
Tested up to: 3.9
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Parse.ly partners with digital publishers by providing clear audience insights through an intuitive analytics platform.  The plugin simplifies integration for Wordpress users.

== Description ==

See your audience clearly.

Parse.ly partners with digital publishers to provide clear audience insights through an intuitive analytics platform.

Thousands of writers, editors, site managers, and technologists already use Parse.ly to understand what content draws in website visitors, and why. Using our powerful dashboards and APIs, customers build successful digital strategies that allow them to grow and engage a loyal audience.

Join industry leaders -- like Mashable, Slate, News Corp, and Conde Nast -- who already use Parse.ly to bring clarity to content, audience, and analytics.

**Features**

* Inserts the required parsely-page <meta> tag as well as JavaScript on all your published pages and posts.
* Powerful customization features to provide rich article data to Parse.ly without any additional coding

Feedback, suggestions, questions or concerns? E-mail us at [support@parsely.com](mailto:support@parsely.com) we always want to hear from you.

== Installation ==

1. If you haven't already done so, [sign up for a trial of Parse.ly](http://www.parsely.com/trial.html?utm_medium=referral&utm_source=wordpress.org&utm_content=wp-parsely)
1. Download the plugin
1. Upload the entire `wp-parsely` folder to your `/wp-content/plugins` directory
1. Activate the plugin through the 'Plugins' menu in WordPress (look for "Parse.ly")
1. Head to the settings page for the plugin (should be /wp-admin/options-general.php?page=parsely)
1. Set your Site ID which can be found in [your settings screen](http://dash.parsely.com/to/settings/api?highlight=apikey)
1. Save your changes and enjoy your data!

Feedback, suggestions, questions or concerns? E-mail us at [support@parsely.com](mailto:support@parsely.com) we always want to hear from you.

== Frequently Asked Questions ==

= Where do I find my Site ID? =

Head to [your settings screen](http://dash.parsely.com/to/settings/api?highlight=apikey) and copy the value for Site ID.

= Why can't I see Dash code on my post when I preview? =

Dash code will only be placed on pages and posts which have been published in WordPress to ensure we don't track traffic generated while you're still writing a post/page.

== Screenshots ==

1. The main settings screen of the wp-parsely plugin
2. The standard JavaScript include being inserted before </body>
3. A sample `parsely-page` meta tag for a home page
4. A sample `parsely-page` meta tag for an article or post

== Changelog ==

= 1.6 =
* Maintenance release with multiple changes needed for WordPress VIP inclusion
* Migrated to WP Settings API
* Various syntax changes in line with Automattic's guidelines
* Removed the tracker_implementation option, plugin now uses Standard implementation for all installs
* Updated much of the copy in settings page
* Updated screenshots

= 1.5 =
* Added support for new option - "Use Categories as Tags"
* Fixed bug that caused wp-admin bar to be hidden when "Do not track authenticated in users" was selected
* Fixed WP category logic bug that failed on users with custom post types

= 1.4 =
* Added early support for post tags
* Fixed permalink errors on category/author/tag pages
* Added version output to both templates and settings pages
* Renamed API key to Site ID to avoid confusion

= 1.3 =
* Added option to not track or not track authenticated users (default is to not track authenticated users)
* Removed async implementation option
* Updated API key retrieval instructions
* Added activation/deactivation hooks
* null categories are now set to "Uncategorized"

= 1.2 =
* Support for using top-level categories for posts instead of the first active post the plugin finds
* parsely-page meta tag now outputs it's value using 'content' attribute instead of 'value'
* Minor fixes to outputting to use proper WordPress functions

= 1.1 =
* Added ability to add prefix to content IDs
* Ensured plugin only uses long tags `<?php` instead of `<?`
* Security updates to prevent HTML/JavaScript injection attacks (values are now sanitized)
* Better error checking of values for API key / implementation method
* Bug fixes

= 1.0 =
* Initial version
* Support for parsely-page and JavaScript on home page and published pages and posts as well as archive pages (date/author/category/tag)

== Upgrade Notice ==

= 1.5 =
This version adds:
* Support for new option - "Use Categories as Tags" which allows you to track your other categories assigned to posts as tags (since you can only assign one section to a post, but many tags)
* Fixed bug that caused wp-admin bar to be hidden when "Do not track authenticated in users" was selected
* Fixed WP category logic bug that failed on users with custom post types

= 1.4 =
This version adds:

* Added early support for post tags
* Fixed permalink errors on category/author/tag pages
* Added version output to both templates and settings pages
* Renamed API key to Site ID to avoid confusion

= 1.2 =
This version adds the ability to use the top-level category for posts instead of the first active post found.  It also outputs attributes using a more HTML-valid way. Please upgrade.

= 1.1 =
This version adds the ability to add a prefix to content IDs and fixes a number of security issues, please upgrade immediately.

= 1.0 =
Initial version.
