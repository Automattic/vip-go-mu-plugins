=== LivePress ===
Requires at least: 3.5
Tested up to: 4.2.2
Tags: LivePress, live, live blogging, liveblogging, realtime, collaboration, Twitter
Stable tag: 1.3

LivePress is a hosted live blogging solution that integrates seamlessly with your WordPress blog.

== Description ==

LivePress converts your blog into a fully real-time information resource.  Your visitors see posts update in real-time.  Comments are pushed out immediately as well.

Take advantage of an enhanced mode for the WordPress editor featuring live comment moderation, streaming Twitter search and more.  Or, live blog entirely via Twitter.

To use LivePress, you must register for an authorization key at https://livepress.com

== Installation ==

1. Upload `livepress-wp` folder into `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Register for a LivePress API key from https://livepress.com
4. Go to Settings >> LivePress, enter your authorization key into field, and press "Check".
5. Configure settings as you wish and press save. You can use all the power of LivePress now!

== Frequently Asked Questions ==

= How do I add a header with an avatar image, time and author's name on updates? =
Use the shortcode [livepress_metainfo].  There is a button to automatic add it to the TinyMCE editor. You can use it in 2 ways:

* Without any attributes. Then the plugin will add the info based on the settings you choose.
* With the attributes already filled in.  This allows you to specify only the elements you would like to appear.

= My theme best looks if LivePress tags are placed at some other place =
You can disable automatic livepress tags injection, by adding into theme's functions.php this line:
    define("LIVEPRESS_THEME", true);
After that, you must edit theme files and add at places you think best this tags:
    <?php do_action('livepress_update_box') ?> -- on index and single page, where notification about new posts arrived should appears
    <?php do_action('livepress_widget') ?> -- on single page, where livepress widget should appears
To fine-tune options, add in CSS file required overrides for #livepress{} selector. Please note, that styles for theme are loaded before styles of plugin, so your overrides should be marked as !important.

= How to set different live update background color =
Define filter "livepress_background_color", that should return color you need.

= Live post title update not working! =
Define filter "livepress_title_css_selector", it should return CSS celector, that should be used to get title to update.
Filter must be exact for current post, so include post id if need. Default are #post-{post_id} (where {post_id} are current post id).

= Javascript files are loaded from strange looking paths with result 404 =
It seems that you have symlinked plugin directory instead of copying it.
To fix this behavior rename config.sample to config and check PLUGIN_SYMLINK value.

== Other notes ==

== Hooks and Filters ==

LivePress is fully extensible by third-party applications using WordPress-style action hooks and filters.

= Add a Tab to the Live Blogging Tools Palette =

The "livepress_setup_tabs" hook will pass an instance of the LivePress_Blogging_Tools class to your function.  You can
add a tab by calling the `add_tab()` method of that class and passing in the title of the tab, its ID, and either content
for the tab or a reference to a callback function that generates output.

Example:

    add_action( 'livepress_setup_tabs', 'my_setup_tabs' );
    function my_setup_tabs( $tools ) {
        $tools->add_tab( array(
            'id'      => 'my_custom_tab',
            'title'   => __( 'My Custom Tab' ),
            'content' => '<div><p>This is some custom content.</p></div>'
        ) );
    }

= Remove a Tab to the Live Blogging Tools Palette =

All of the default tabs in the toole palette can be removed by name using the "livepress_setup_tabs" action hook and calling
the `remove_tab()` method of the passed class.

Example:

    add_action( 'livepress_setup_tabs', 'remove_author_notes' );
    function remove_author_notes( $tools ) {
        $tools->remove_tab( 'live-notes' );
    }

The default tab IDs used in LivePress are:

* Comments               => 'live-comments'
* Twitter Search         => 'live-twitter-search'
* Manage Remove Authors  => 'live-remote-authors'
* Author Notes           => 'live-notes'

= Custom post types =

In order to live blog on a custom post type, you need to add a filter. You can add it in your theme's functions.php file:

```
// In this case, add the 'books' post type
function add_livepress_post_types( $post_types ) {
         array_push( $post_types, 'books' );
         return $post_types;
}
add_filter( 'livepress_post_types', 'add_livepress_post_types' );
```

= Control insertion of live stream =

You can use the 'livepress_the_content_filter_disabled' to turn off LivePress's 'the_content' filter.

```
apply_filters( 'livepress_the_content_filter_disabled', '__return_true' );
```

== Screenshots ==

1. Just create a new post with livepress enabled.  Anyone who has the main blog page open will see the notification.
2. New update sent -- it appears for all readers of this post at the same time.

== Changelog ==

= 1.3 =
* sync with WordPress VIP Version
* code tidy and various undefined vars props: Paul Schreiber from FiveThirtyEight.com
* fix to handle '%' in titles and message body
* fix to handle sounds URL on WordPress VIP
* fix to handle popup blocking for Twitter share links
* fix to share links on pinned post
* optimized all images
* added language support for select2
* fix to stop getting undefined authors when editing an update
* fix to ensure that editing an update does not update its timestamp
* fix to ensure an update edited in the plain text editor saves correctly
* fix to ensure that all of the paragraphs of a multi-paragraph update are shown in the editor

= 1.2.4 =
* Added the ability to have drafts for updates
* Added filter to suppress live updates from showing in the CoSchedule by Todaymade plugin needs Version 2.3.0 of the CoSchedule plugin
* moved the pinned update about the status bar
* bug fixes around linefeeds being stripped when changing editor modes

= 1.2.3 =
* removed comment block that was creating and install error
* Added text domain detail to plugin Comment block

= 1.2.2 =
* WP CLI commands to manage posts
* removed JS console.log calls
* Increased the timeout for vip_safe_wp_remote_get calls from 1 to 10
* Did some work to allow the twitter popup to still work if the calling Ajax call is slow
* Other small bug fixes
* test on WordPress 4.1

= 1.2.1 =
* removed src files from plugin
* Issue with raw code being displayed after converting post to "non-live" fixed
* other bug fixes

= 1.2.0 =
* Back port of the code changes from the WordPress VIP version of livepress
* Fixed: remote authors and posting from Twitter
* Added sharing links for individual updates
* Added Headlines for individual updates (edited)
* Added Tags for individual updates
* Added Avatars for individual
* General bug squashing and cleanup

= 1.1.5 =
* Adds filter to disable LivePress's 'the_content' filter. See more in [Other notes](https://wordpress.org/plugins/livepress/other_notes/)
* Adds support for custom post types. In order to enable live blogging on a custom post type, you need to add a filter. See more in [Other notes](https://wordpress.org/plugins/livepress/other_notes/)
* Adds support for per update headers.
* Adds suport for tags for updates.
* Several bug fixes: timezone offset in live updates, clear comment textarea after sending comment, sound notifications setting being ignored, first update not editable.

= 1.1.4 =
* Merge in WordPress VIP branch
* Fixes formatting issues after going 'non-live' with a post
* Fix twitter embeds
* Fix deleting updates
* Fixes in pinned post
* General bug squashing and cleanup

= 1.1.3 =
* Bugfix release to include new fonts.

= 1.1.2 =
* Remove Soundmanager, replace it with SoundJS, enable sound.
* Add translation for timeago().
* Add embedded media preview on live posts in the admin dashboard.
* Optimize update engine with 'incremental api'
* Style refinements, better match for new WordPress look and feel
* Bug fixes: embeds for Facebook using Facebook's official WP Plugin, incompatibility with WordPress' embedded audio, comment tab on live post editor, display issue for images in old live posts, other small bug fixes.

= 1.1.1 =
* Continue refining design elements to match new WordPress look
* WordPress 4.0 compatibility fixes
* Incremental updates for faster, more reliable live updates
* Bug fixes: clear console errors, correct settings page error

= 1.1.0 =
* Bug squashing, media conflicts.

= 1.0.9 =
* Switch to timeago() for live update time calculations
* Add pinned post feature to pin the first update as a header for live posts
* Small bug fixes, cleanup, better compatibility with WordPress 3.9

= 1.0.8 =
* Correct an issue where tweets are not embedded correctly in editor

= 1.0.7 =
* Add translations for all strings, including in Javascript
* Escape all outputs for security
* Remove all stored terms/guest bloggers when taking post not live
* Correct category post counts
* Remove depricated/unused code
* Address PHP compatibility/warnings
* Address jQuery migrate warnings
* Hide 'full screen' button in mce editor when in live mode
* Improve twitter oEmbed rendering speed when large number of tweets are embedded in live post
* Remove config file/use of fopen
* Design cleanup to match new design in WordPress 3.8+
* Improved error messages when adding remote author fails
* Improved plugin deactivation and uninstall routines
* Individual nonces for each action, remove check_ajax_referer pluggable function override
* Only apply the LivePress Status column to posts
* Remove .swf files from SoundManager code
* Added inline documentation throughout plugin
* Address compatibility issues for TinyMCE 4 (WordPress 3.9+)
* Use timeago.js to keep update times updated
* Fix Twitter oEmbeds to address introduction of https only Twitter API requirement
* Regenerate translation file and add to build process

= 1.0.6 =
* Add post-activation workflow, API signup link
* Correct category/list post counts - use 3.7 filter when available
* Imnprove count of unviewed Twitter results and Comments
* Refine Twitter component initialization
* Miscellaneous bug fixes, remove depreciated warnings

= 1.0.5 =
* New live blogging via SMS
* Improve live blogging via twitter
* Improve switching live on and off
* Small bug fixes

= 1.0.4 =
* Miscellaneous bug fixes

= 1.0.3 =
* Update connection to LivePress api to use port 80
* Display post live or not live status on post list page
* Make post status live or not live more visible in post editor
* Fix issue where a large number of comments would cause live blogging tools tab to grow too large
* Better notifications when adding new Twitter handle
* Fix Facebook embedding issue

= 1.0.2 =
* Reduce Twitter search history from 500 to 200 items
* Attempt to fix discrepancy between autoscroll/chime settings
* Rename 'Post' to 'Send to Editor' in live Twitter search
* Improve JS binding on live update editor
* Miscellaneous UI refinements

= 1.0.1 =
* Patch remote author count returning invalid data
* Pluralize HUD counts (remote authors, comments, visitors)
* Allow Twitter feed to pause on click of appropriate button or hover

= 1.0 =
* Initial public release

= 0.7.4 =
* Update API server references for staging

= 0.7.3 =
* Debug remote, automatic updates

= 0.7.2 =
* Update translation file

= 0.7.1 =
* Various UI tweaks to rectify user test errors and misses

= 0.7 =
* Implement post "regions" as post formats of "Aside"
* Modify HTMLPurifier to use a custom post type for storing the definition cache

= 0.6 =
* Fix a JS inclusion bug causing issues on the admin screen

== Upgrade Notice ==

= 1.0 =
None
