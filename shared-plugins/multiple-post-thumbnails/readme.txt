=== Plugin Name ===
Contributors: chrisscott, voceplatforms
Tags: thumbnails, image, featured image
Requires at least: 2.9.2
Tested up to: 3.9.1
Stable tag: 1.6

Adds multiple post thumbnails to a post type. If you've ever wanted more than one Featured Image on a post, this plugin is for you.

== Installation ==

1. Upload the `multi-post-thumbnails` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. In your theme's `functions.php` register a new thumbnail for the post type you want it active for. If `post_type` is not set it defaults to `post`.

            if (class_exists('MultiPostThumbnails')) {
                new MultiPostThumbnails(
                    array(
                        'label' => 'Secondary Image',
                        'id' => 'secondary-image',
                        'post_type' => 'post'
                    )
                );
            }
4. Display the thumbnail in your theme. e.g. for loop templates (outside of the loop, the first argument to `MultiPostThumbnails::the_post_thumbnail()` will need to be the post type):

            <?php if (class_exists('MultiPostThumbnails')) : MultiPostThumbnails::the_post_thumbnail(get_post_type(), 'secondary-image'); endif; ?>

For more, read the full documentation: http://voceconnect.github.io/multi-post-thumbnails/

== Frequently Asked Questions ==

See the full documentation for more: http://voceconnect.github.io/multi-post-thumbnails/

= I'm trying to upgrade to a new versions of WordPress and get an error about `MultiPostThumbnails` =

This is caused by using the example in previous readmes that didn't do a check for the `MultiPostThumbnails` class existing first. This has been corrected in the Installation section.

= How do I register the same thumbnail for multiple post types? =

You can loop through an array of the post types:

            if (class_exists('MultiPostThumbnails')) {
                $types = array('post', 'page', 'my_post_type');
                foreach($types as $type) {
                    new MultiPostThumbnails(array(
                        'label' => 'Secondary Image',
                        'id' => 'secondary-image',
                        'post_type' => $type
                        )
                    );
                }
            }

= How do I use a custom thumbnail size in my theme? =

After you have registered a new post thumbnail, register a new image size for it. e.g if your post thumbnail `id` is `secondary-image` and it is for a `post`, it probably makes sense to use something like:

        add_image_size('post-secondary-image-thumbnail', 250, 150);

This will register a new image size of 250x150 px. Then, when you display the thumbnail in your theme, update the call to `MultiPostThumbnails::the_post_thumbnail()` to pass in the image size:

        MultiPostThumbnails::the_post_thumbnail(get_post_type(), 'secondary-image', NULL,  'post-secondary-image-thumbnail');

You can register multiple image sizes for a given thumbnail if desired.

= How can I get the thumbnail without automatically echoing it? =

Use `MultiPostThumbnails::get_the_post_thumbnail()` in place of `MultiPostThumbnails::the_post_thumbnail()`.

= How do I get just the URL of a thumbnail without the wrapping HTML? =

Use `MultiPostThumbnails::get_post_thumbnail_url()` passing in the following arguments:

* `$post_type` - the post type the thumbnail was registered for
* `$id` - the ID used to register the thumbnail (not the post ID)
* `$post_id` - optional and only needs to be passed in when outside the loop but should be passed in if available when called

For example, for a thumbnail registered with an `id` of `secondary-image` and `post_type` of `post` the following would retrieve the thumbnail URL:

        MultiPostThumbnails::get_post_thumbnail_url(get_post_type(), 'secondary-image');

= When I use the sample code the thumbnail doesn't show up. What's wrong? =

* Make sure you are using the same ID you registered the thumbnail with as the second argument to `MultiPostThumbnails::the_post_thumbnail()`.
* If you are trying to get the thumbnail outside of the loop or a single template, you will need to replace `get_post_type()` with the post type you are trying to get the thumbnail for. This is common when trying to use the code in headers/footers/sidebars.

= I see the meta box in the admin when editing a post but when I click on 'Set as [label] image' in the media manager, nothing happens and I get a JavaScript console error =

If you are using a symlink to include the plugin directory in your project, the admin js file will not load and cause this. Unfortunately, the solution is to not use symlinks due to the behavior of PHP's `__FILE__`

= Is there a way to show the post meta where the thumbnail IDs are stored in the Custom Fields metabox?

Since version 1.5 these are hidden by default. To unhide them, add `add_filter('mpt_unprotect_meta', '__return_true');` to your theme's `functions.php`

= Is there a github repo? I love me some submodules! =

Yes. https://github.com/voceconnect/multi-post-thumbnails

= Pancakes or waffles? =

Pancakes.

== Screenshots ==

1. Admin meta box showing a new thumbnail named 'Secondary Image'.
2. Media modal showing images attached to the post and a 'Secondary Image' selected.
3. Admin meta box with the 'Secondary Image' selected.

== Changelog ==

= 1.6 =

* Use medial modal instead of thickbox for WordPress 3.5+ (props mparolisi).
* Fix getting plugin directory name for il8n (props pixeltechnologies).

= 1.5 =

* Add a `size` parameter to `MultiPostThumbnails::get_post_thumbnail_url` to allow getting any registered size.
* Add `context` option to the args accepted when instantiating a new `MultiPostThumbnails` to specify the metabox context. Defaults to `side` (which it was previously hard coded to).
* Filter `is_protected_meta` to hide meta from the Custom Fields metabox by default (props willroy). To unhide them, add `add_filter('mpt_unprotect_meta', '__return_true');` to your theme's `functions.php`.
* il8n courtesy Horttcore

= 1.4 =

* Add a context parameter to the thickbox opener to narrow down the selection in the media upload tabs to the one being set/viewed (props kevinlangleyjr) which reduces clutter when many thumbnails are registered. Refactor js to use an object (props markparolisi). Hide attachment fields on 3.5 media sidebar.

= 1.3 =

* Don't show set as links in media screens when not in context (props prettyboymp). Add voceplatforms as an author. Updated FAQ.

= 1.2 =

* Only enqueue admin scripts on needed pages (props johnjamesjacoby) and make sure thickbox is loaded (props prettyboymp). Add media-upload script to dependencies for post types that don't already require it (props kevinlangleyjr).

= 1.1 =

* Update FAQ. Clean up `readme`. Don't yell `null`. Don't output link to original if there is no image. 

= 1.0 =

* Use `get_the_ID()` in `get_the_post_thumbnail`. Props helgatheviking.

= 0.9 =
* Increment version only to attempt to get plugin versions back in sync.

= 0.8 =
* Revert init action changes from 0.7. Fixes admin metaboxes not showing when the MultiPostThumbnails class is instantiated in an action instead of `functions.php`

= 0.7 =
* Add actions/filters on init action. Should fix admin metaboxes not showing or showing out of order. props arizzitano.

= 0.6 =
* Update `get_the_post_thumbnail` return filter to use format `{$post_type}_{$thumb_id}_thumbnail_html` which allows filtering by post type and thumbnail id which was the intent. Props gordonbrander.
* Update plugin URL to point to Plugin Directory

= 0.5 =
* Update readme to check for `MultiPostThumbnails` class before calling.

= 0.4 =
* Added: optional argument `$link_to_original` to *_the_post_thumbnails template tags. Thanks to gfors for the suggestion.
* Fixed: PHP warning in media manager due to non-existent object

= 0.3 =
* Fixed: when displaying the insert link in the media library, check the post_type so it only shows for the registered type.

= 0.2 =
* Update docs and screenshots. Update tested through to 3.0 release.

= 0.1 =
* Initial release.

== Upgrade Notice ==

= 0.6 =
`get_the_post_thumbnail` return filter changed to use the format `{$post_type}_{$thumb_id}_thumbnail_html` which allows filtering by post type and thumbnail id which was the intent.
