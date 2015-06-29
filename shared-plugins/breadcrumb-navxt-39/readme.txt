=== Breadcrumb NavXT ===
Contributors: mtekk, hakre
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=FD5XEU783BR8U&lc=US&item_name=Breadcrumb%20NavXT%20Donation&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHosted
Tags: breadcrumb, breadcrumbs, trail, navigation, menu, widget
Requires at least: 3.1
Tested up to: 3.2
Stable tag: 3.9.0
Adds breadcrumb navigation showing the visitor's path to their current location.

== Description ==

Breadcrumb NavXT, the successor to the popular WordPress plugin Breadcrumb Navigation XT, was written from the ground up to be better than its ancestor. This plugin generates locational breadcrumb trails for your WordPress powered blog or website. These breadcrumb trails are highly customizable to suit the needs of just about any website running WordPress. The Administrative interface makes setting options easy, while a direct class access is available for theme developers and more adventurous users. Do note that Breadcrumb NavXT requires PHP5.2 or newer.

= Translations =

Breadcrumb NavXT distributes with translations for the following languages:

* English - default -
* German by Tom Klingenberg
* French by Laurent Grabielle
* Spanish by Karin Sequen
* Dutch by Stan Lenssen
* Russian by Yuri Gribov
* Swedish by Patrik Spathon
* Italian by Luca Camellini
* Japanese by Kazuhiro Terada


Don't see your language on the list? Feel free to translate Breadcrumb NavXT and send John Havlik the translations.

== Installation ==

Please visit [Breadcrumb NavXT's](http://mtekk.us/code/breadcrumb-navxt/#installation "Go to Breadcrumb NavXT's project page's installation section.") project page for installation and usage instructions.

== Changelog ==
= 3.9.0 =
* Behavior change: Settings can not be saved, imported, or exported until any necessary settings updates and/or installs are completed.
* New feature: Support for WordPress 3.1 custom post type archives.
* Bug fix: Displays a warning message in the WordPress dashboard if PHP version is too old rather than trying to deactivate and dieing on all pages.
* Bug fix: Fixed a potential cause for the "options not saved" error.
* Bug fix: Fixed bug where the “Blog Breadcrumb” was not obeyed on archives.
= 3.8.1 =
* Bug fix: Root pages for custom post types should work again.
* Bug fix: The post_post_root and post_page_root not being saved warning when saving settings should be fixed.
= 3.8.0 =
* New feature: Error reporting added for some errors that may occur during a settings save.
* New feature: Custom post types may use dates as their taxonomy type.
* New feature: New display_nested function to facilitate support for Google's Breadcrumbs RDFa and Microformat.
* New feature: Paged display works for all post types now (was previously restricted to archives).
* Bug fix: Fixed a few cases where Breadcrumb NavXT may cause PHP warnings.
* Bug fix: Automatically deactivates if PHP version is tool old rather than just displaying warning message.
* Bug fix: Custom post types that are not associated with any taxonomies no longer cause PHP Notices.
* Bug fix: Various PHP Notices introduced in 3.7.0 were fixed.
* Bug fix: Fixed issue where multiple runs caused the current_item_prefix and current_item_suffix to be applied multiple times.
* Bug fix: The included display functions will behave more appropriately when database settings don't exist.
* Bug fix: Fixed multibyte UTF-8 character support for custom taxonomies.
* Bug fix: Fixed issue where the widget (Appearance > Widgets) would not load the appropriate translations.
= 3.7.0 =
* New feature: Support for “global”/network wide breadcrumb trails in networked setups of WordPress 3.0.
* New feature: Can use any hierarchical post type as a hierarchy for flat post types.
* New feature: Users are now warned if settings are out of date, allowed to do a one click settings migration.
* New feature: Users can now control if a post type uses the “posts page” in it's hierarchy or not.
* Bug fix: Breadcrumb trails for attachments work properly now for custom post types.
* Bug fix: Users can now set custom post types to have a page hierarchy through the settings page.
* Bug fix: Fixed issues where the PHP version check did not work correctly.
* Bug fix: Fixed issue where all settings would get reset on “clean” 3.6.0 installs on plugin activation.
* Bug fix: Fixed issue when a static front page is specified but the post page is not.
= 3.6.0 =
* New feature: Vastly improved support for WordPress custom post types.
* New feature: Can now restrict breadcrumb trail output for the front page in the included Widget.
* New feature: Can now undo setting saves, resets, and imports.
* New feature: Translations for Japanese now included thanks to Kazuhiro Terada.
* Bug fix: Fixed issue where the class element were not closed in a li opening tag.
* Bug fix: Safer handling of blank anchor templates.
* Bug fix: Fixed issue where the %title% tag in the current item anchor template would be trimmed.
= 3.5.1 =
* Bug fix: Fixed issue where a deactivation/activation cycle would reset all of the user specified settings.
* Bug fix: Fixed issue where the archive by date suffix field did not save.
* Bug fix: Fixed issue where custom taxonomy settings did not save.
* Bug fix: Fixed issue where xml settings files would not import.
* Bug fix: French and German translations updated for 3.5.x.
= 3.5.0 =
* New feature: Added actions `bcn_before_fill` and `bcn_after_fill`, see documentation for more information.
* New feature: Widget rewritten to use the WordPress 2.8 Widget API, now multi-widget capable.
* New feature: Widget output can be in list form, can be in reversed order, and can be unlinked.
* Bug fix: Fixed issue where the current tab was forgotten after a save in the settings page.
* Bug fix: Fixed various WP API issues with WordPress 3.0.
* Bug fix: Fixed title trimming so that it works properly with multi-byte characters.
= 3.4.1 =
* Bug fix: Fixed issue with PHP unexpected $end on line 1567 in breadcrumb_navxt_admin.php.
* Bug fix: Fixed issue where the %link% anchor tag would not be replaced with a URI for flat taxonomies (e.g. tags).
* Bug fix: Fixed issue where paged breadcrumbs would cause WP_Error objects to be thrown.
= 3.4.0 =
* New feature: Proper support of custom taxonomies. category_parents and post_tags replaced with term_parents and post_terms.
* New feature: Ability to use date as post "taxonomy".
* New feature: Translations for Italian now included thanks to Luca Camellini.
* Bug fix: Fixed permalink for day breadcrumbs.
* Bug fix: Flat taxonomy archive breadcrumbs now are surrounded by both the standard and archive prefix/suffix combination.
= 3.3.0 =
* Behavior change: The core plugin was removed, and administrative plugin renamed, direct class access still possible.
* New feature: Ability to trim the title length for all breadcrumbs in the trail.
* New feature: Ability to selectively include the "Blog" in addition to the "Home" breadcrumb in the trail (for static frontpage setups).
* New feature: Translations for Russian now included thanks to Yuri Gribov.
* New feature: Translations for Swedish now included thanks to Patrik Spathon.
* Bug fix: Minor tweaks to the settings link in the plugins listing page so that it fits better in WordPress 2.8.
* Bug fix: Now selects the first category hierarchy of a post instead of the last.
= 3.2.1 =
* New feature: Translations for Belorussian now included thanks to "Fat Cow".
* Bug fix: The `bcn_display()` and `bcn_display_list()` wrapper functions obey the
`$return parameter`.
* Bug fix: Anchors now will be valid HTML even when a page/category/post title has HTML tags in it.
* Bug fix: Revised `bcn_breadcrumb_trail::category_parents` to work around a bug in `get_category` that causes a WP_Error to be thrown.
* Bug fix: Importing settings XML files should no longer corrupt HTML entities.
* Bug fix: Can no longer import and reset options at the same time.
* Bug fix: WordPress 2.6 should be supported again.
= 3.2.0 =
* New feature: Now can output breadcrumbs in trail as list elements.
* New feature: Translations for Dutch now included thanks to Stan Lenssen.
* New feature: Now breadcrumb trails can be output in reverse order.
* New feature: Ability to reset to default option values in administrative interface.
* New feature: Ability to export settings to a XML file.
* New feature: Ability to import settings from a XML file.
* Bug fix: Anchor templates now protected against complete clearing.
* Bug fix: Administrative interface related styling and JavaScript no longer leaks to other admin pages.
* Bug fix: Calling `bcn_display()` works with the same inputs as `bcn_breadcrumb_trail::display()`.
* Bug fix: Calling `bcn_display()` multiple times will not place duplicate breadcrumbs into the trail.
= 3.1.0 =
* New feature: Tabular plugin integrated into the administrative interface/settings page plugin.
* New feature: Default options now are localized.
* New feature: Plugin uninstaller following the WordPress plugin uninstaller API.
* Bug fix: Administrative interface tweaked, hopefully more usable.
* Bug fix: Tabs work with WordPress 2.8-bleeding-edge.
* Bug fix: Translations for German, French, and Spanish are all updated.
* Bug fix: Paged archives, searches, and frontpage fixed.
= 3.0.2 =
* Bug fix: Default options are installed correctly now for most users.
* Bug fix: Now `bcn_breadcrumb_trail::fill()` is safe to call within the loop.
* Bug fix: In WPMU options now are properly separate/independent for each blog.
* Bug fix: WPMU settings page loads correctly after saving settings.
* Bug fix: Blog_anchor setting not lost on non-static frontpage blogs.
* Bug fix: Tabular add on no longer causes issues with WordPress 2.7.
* New feature: Spanish and French localization files are now included thanks to Karin Sequen and Laurent Grabielle.
= 3.0.1 =
* Bug fix: UTF-8 characters in the administrative interface now save/display correctly.
* Bug fix: Breadcrumb trails for attachments of pages no longer generate PHP errors.
* Bug fix: Administrative interface tweaks for installing default options.
* Bug fix: Changed handling of situation when Posts Page is not set and Front Page is set.
= 3.0.0 =
* New feature: Completely rewritten core and administrative interface.
* New feature: WordPress sidebar widget built in.
* New feature: Breadcrumb trail can output without links.
* New feature: Customizable anchor templates, allows things such as rel="nofollow".
* New feature: The home breadcrumb may now be excluded from the breadcrumb trail.
* Bug fix: 404 page breadcrumbs show up in static frontpage situations where the posts page is a child of the home page.
* Bug fix: Static frontpage situations involving the posts page being more than one level off of the home behave as expected.
* Bug fix: Compatible with all polyglot like plugins.
* Bug fix: Compatible with Viper007bond's Breadcrumb Titles for Pages plugin (but 3.0.0 can replace it as well)
* Bug fix: Author page support should be fixed on some setups where it did not work before.