=== Zone Manager (Zoninator) ===
Contributors: batmoo, automattic, wpcomvip, pkevan, matthumphreys, potatomaster, jblz, nickdaugherty, betzster
Tags: zones, post order, post list, posts, order, zonination, content curation, curation, content management
Requires at least: 3.5
Tested up to: 4.2
Stable tag: 0.6
License: GPLv2

Curation made easy! Create "zones" then add and order your content!

== Description ==

This plugin is designed to help you curate your content. 

Assign and order stories within zones that you create, edit, and delete. Then use the handy API functions to retrieve and display your content in your theme. Or for those who are a bit code-averse, try the handy widget.

Key features included in the plugin:

* Add/edit/delete zones
* Add/remove posts (or any custom post type) to/from zones
* Order posts in any given zone
* Limit capabilities on who can add/edit/delete zones vs add content to zones
* Locking mechanism, so only one user can edit a zone at a time (to avoid conflicts)
* Idle control, so people can't keep the zoninator locked

This plugin was built by [Mohammad Jangda](http://digitalize.ca) in conjunction with [William Davis](http://wpdavis.com/) and the [Bangor Daily News](http://www.bangordailynews.com/).

== Installation ==

1. Unzip contents and upload to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Go to Dashboard > Zones to create and manage zones.
1. Use the plugin's handy API functions to add zones to your theme.
1. Enjoy!

== Frequently Asked Questions ==

= How do I disable the locking feature? =

You can use a filter:

`add_filter( 'zoninator_zone_max_lock_period', 'z_disable_zoninator_locks' );`

= How do I change the the locking feature settings? =

Filter the following and change according to your needs:

* Number of seconds a lock is valid for, default `30`: `zoninator_zone_lock_period`
* Max idle time in seconds: `zoninator_zone_max_lock_period`


== Screenshots ==

1. Create and manage your zones and content through a fairly intuitive and familiar interface

== Changelog ==

= 0.6 =

* Support for term splitting in 4.2
* Run the init hook later so that we can allow custom post types to attach themselves to the plugin http://wordpress.org/support/topic/plugin-zone-manager-zoninator-add-specific-custom-post-types
* Better translation support
* Coding standards cleanup

= 0.5 =

* WordPress version requirements bumped to 3.5
* Support for touch events for mobile via jQuery UI Touch Punch (http://touchpunch.furf.com/)
* Filter recent posts or search-as-you-type by date (today, yesterday, all) or category for more refined results, props Paul Kevan and the Metro UK team
* New actions fired when adding/removing posts from zones
* Bits of clean-up

= 0.4 =

* New dropdown that recent posts which can be adding to zones, props metromatic and Metro UK
* New filter: zoninator_posts_per_page -- to override the default posts_per_page setting
* Use core bundled versions of jQuery UI

= 0.3 =

* Introduce z_get_zone_query: returns a WP_Query object so you can run the loop like usual.
* Disable editing and prefixing of slugs. They're just problems waiting to happen...
* Add new filter to allow filtering of search args, props imrannathani for the suggestion
* Allow scheduled posts to be added to zones so they automagically show up when they're published, props imrannathani for the idea.
* Default to published post in all zone queries in the front-end. Scheduled posts can still be added via a filter.
* Run clean_term_cache when a post is added or deleted from a zone so that the necessary caches are flushed.
* Add new filter to limit editing access on a per-zone level. props hooman and the National Post team
* Allow editor role (editor_others_posts) to manage zones (plus other capability fixes, props rinat k.)

= 0.2 = 

* Move Zones to a top-level menu so that it's easier to access. And doesn't make much sense hidden under Dashboard.
* Change the way error and success messages are handled.
* jQuery 1.6.1 compatibility.
* Bug fix: Custom Post Types not being included in search. Thanks Shawn!
* Bug fix: Custom Post Types not being included in post list. Thanks Daniel!
* Bug fix: Error thrown when removing last post in a zone. Thanks Daniel!
* Other cleanup.

= 0.1 =

* Initial Release!

== Upgrade Notice ==

= 0.3 =

* Slugs can no longer be edited. This is possibly a breaking change if you're using slugs to get zones or zone posts.

= 0.2 =

* Bunch of bug fixes and code improvements

== Usage Notes ==

= Example =

You can work with with a zone's posts either as an array or a WP_Query object.

<strong>WP_Query</strong>

`
$zone_query = z_get_zone_query( 'homepage' );
if ( $zone_query->have_posts() ) :
	while ( $zone_query->have_posts() ) : $zone_query->the_post();
		echo '<li>' . get_the_title() . '</li>';
	endwhile;
endif;
wp_reset_query();
`

<strong>Posts Array</strong>

`
$zone_posts = z_get_posts_in_zone( 'homepage' );
foreach ( $zone_posts as $zone_post ) :
	echo '<li>' . get_the_title( $zone_post->ID ) . '</li>';
endforeach;
`

= Function Reference = 

Get an array of all zones:

`z_get_zones()`

Get a single zone. Accepts either ID or slug.

`z_get_zone( $zone )`

Get an array of ordered posts in a given zone. Accepts either ID or slug.

`z_get_posts_in_zone( $zone )`

Get a WP_Query object for a given zone. Accepts either ID or slug.

`z_get_zone_query( $zone );


More functions listed in `functions.php`
