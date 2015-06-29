=== Simple Page Ordering ===
Contributors: jakemgold, 10up, thinkoomph
Donate link: http://10up.com/plugins/simple-page-ordering-wordpress/
Tags: order, re-order, ordering, pages, page, manage, menu_order, hierarchical, ajax, drag-and-drop, admin
Requires at least: 3.4
Tested up to: 3.6
Stable tag: 2.1.2

Order your pages and other hierarchical post types with simple drag and drop right from the standard page list.

== Description ==

Order your pages, hierarchical custom post types, or custom post types with "page-attributes" with simple drag and drop right from the built in page list. 

Simply drag and drop the page into the desired position. It's that simple. No new admin menus pages, no clunky, bolted on user interfaces. Just drag and drop on the page or post-type screen.

The plug-in is "capabilities aware" - only users with the ability to edit others' pages (editors and administrators) will be able to reorder content.

Integrated help is included: just click the "help" tab at the top right of the screen.

Please note that the plug-in is not compatible with Internet Explorer 7 and earlier, due to limitations within those browsers.


== Installation ==

1. Install either via the WordPress.org plugin directory, or by uploading the files to your server.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Get to work reordering your content!


== Frequently Asked Questions ==

= Why can't I reorder my posts? =

Generic posts are not displayed by menu order - they're displayed by chronology. You can theoretically add menu ordering to posts in your code (theme functions.php, plug-in) by using:

`add_post_type_support( 'post', 'page-attributes' );`

= Can I make my custom post type take advantage of this plug-in? =

Yep. There are two ways to turn on support for Simple Page Ordering.

Ideally, when you register the post type, set `hierarchical` to `true` - hierarchical post types natively order by menu order.

Alternatively, when you define the features the post type supports, include `page-attributes`. This will add a `Sort by Order` option to the filter links above the drop downs. Once you sort by order, you can drag and drop the content.

Finally, you can take advantage of the `simple_page_ordering_is_sortable` filter, which passes the result of the default check and the post type name, to override default behavior.

= I want my non-hierarchical post type to be sortable. Help! =

See the previous two answers - just add `page-attributes` to the list of supported post type features.

= I reordered my posts, but the order didn't change on the front end of my site! =

This plug-in doesn't change any *behavior* on the front end, it simply changes the menu order stored in WordPress.

If you want a list of pages or custom post types to display in that defined order, you must change the post query's `orderby` parameter to `menu_order` (if it's not already).

= I reordered my content, it seemed to work, but when I refreshed, it went back to the old order! = 

This most likely means the AJAX request - the server side code - failed after you dropped the content into the new position. Some shared hosts aggressively time out and limit AJAX requests. Version 2.0 batches these requests so you can try reducing the number of items it updates on each request using a filter in your theme's functions.php or a custom plug-in:

`add_filter( 'simple_page_ordering_limit', function($number) { return 5; } );`

Where 5 is the number of items to batch on each request (the default is 50). Note that this example uses PHP 5.3+ callback functions, so if you're still on PHP 5.2, you'll need to add a traditional callback. 

= What happened to the drop down box that let me change the number of items on each page in the admin?? =

This feature is already built into WordPress natively, but a bit tucked away. If you pull down the "Screen Options" tab up top (on the list of post objects) there's a field where you can specify the number of items to show per page. I decided it was not a very good practice to duplicate this.


== Screenshots ==

1. Dragging the page to its new position
1. Processing indicator


== Changelog ==

= 2.1.2 =
* Bug fix: hierarchical custom post types without page-attributes was still broken - doh!
* Bug fix: extreme edge case where post columns did not include the post title now supported

= 2.1.1 =
* Bug fix: custom post types with page-attributes or hierarchical properties, but not both, breaking ordering

= 2.1 =
* UI refinements: Better "spinner" positioning (and HiDPI), translucent row when moving, improved appearance of "drop" placeholder, wait till row dragged by at least 5px to start sorting
* Major JavaScript refactoring and simplification (combined with new stylesheet) for better performance
* Awareness of custom user capabilities for post types, in addition to a filter (`simple_page_ordering_edit_rights`) for overriding reordering rights (previously used `edit_others_pages` globally)
* Awareness of custom post statuses (so they are not skipped during backend ordering operation)

= 2.0 =
* Drag pages into any part of the page hierarchy! No longer limited to same branch of tree!
* Big performance improvements under the hood: leaner queries, batched requests, less processing
* Scales much more reliably in situations with very high page counts due to batching of requests
* Order of the first page is now set to "1" instead of "0", so pages added after ordering are added at the top (instead of second)
* Removed "number of pages" drop down, which is repetitive of a field accessible under Screen Options
* New filters and hooks to extend / override default functionality
* Improved compatibility with newer versions of WordPress

= 1.0 =
* Fix unexpected page ordering results when pages have not been explictly ordered yet (sorts by menu_order, then title, not just menu_order)
* Support for ordering non-hierarchical post types that have "page-attributes" support
* New filter link for "Sort by Order" to restore (hierarchical) or set (non-hierarchical, page attributes support) post list sort to menu order
* Fix "per page" drop down filter selection not saving between page loads (was broken in 3.1)
* Users are now forced to wait for current sort operation to finish before they can sort another item
* Smarter about "not sortable" view states
* Localization ready! Rough Spanish translation included.
* Items are always ordered with positive integers (potential negative sort orders had some performance benefits in last version, but sometimes caused issues)
* Assorted other performance and code improvements

= 0.9.6 =
* Fix for broken inline editing (quick edit) fields in Firefox

= 0.9.5 =
* Smarter awareness of "sorted" modes in WordPress 3.1 (can only use when sorted by menu order)
* Smarter awareness of "quick edit" mode (can't drag)
* Generally simplified / better organized code

= 0.9 =
* Fix page count display always showing "0" on non-hierarchical post types (Showing 1-X of X)
* Fix hidden menu order not updating after sort (causing Quick Edit to reset order when used right after sorting)
* "Move" cursor only set if JavaScript enabled
* Added further directions in the plug-in description (some users were confused about how to use it)
* Basic compatibility with 3.1 RC (prevent clashes with post list sorting)

= 0.8.4 =
* Loosened constraints on drag and drop to ease dropping into top and bottom position
* Fixed row background staying "white" after dropping into a new position
* Fixed double border on the bottom of the row while dragging
* Improved some terminology (with custom post types in mind)

= 0.8.2 =
* Simplified code - consolidated hooks
* Updated version requirements