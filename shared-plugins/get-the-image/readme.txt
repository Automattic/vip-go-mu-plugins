=== Get the Image ===
Contributors: greenshady
Donate link: http://themehybrid.com/donate
Tags: image, images, thumbnail
Requires at least: 3.2
Tested up to: 3.5
Stable tag: 0.8.1

An easy-to-use image script for adding things such as thumbnails and feature images.

== Description ==

*Get the Image* is a plugin that grabs images for you.  It was designed to make the process of things such as adding thumbnails, feature images, and/or other images to your blog much easier, but it's so much more than that.  It is an image-based representation of your WordPress posts.

This is a highly intuitive script that can grab an image by custom field input, WP's post image feature, post attachment, or extracting it from the post's content.

Support for this plugin is handled on the Theme Hybrid <a href="http://themehybrid.com/support">support forums</a>.

== Installation ==

1. Upload `get-the-image` to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Add the appropriate code to your template files as outlined in the `readme.html` file.

More detailed instructions are included in the plugin's `readme.html` file.  It is important to read through that file to properly understand all of the options and how the plugin works.

== Frequently Asked Questions ==

= Why was this plugin created? =

Many magazine-type themes require a lot of work when inputting images to make them look good.  This plugin was developed to make that process much easier for the end user.  But, at the same time, it needed to be flexible enough to handle anything.

Other scripts are bloated and offer odd solutions.  This plugin uses the built-in methods of WordPress to create things such as feature images, thumbnails, galleries, or whatever.

This plugin was created to be a lightweight solution to handle a very powerful need in the WordPress community.

= How does it pull images? =

1.  Looks for an image by custom field (one of your choosing).
1. If no image is added by custom field, check for an image using `the_post_thumbnail()` (WordPress featured image).
1. If no image is found, it grabs an image attached to your post.
1. If no image is attached, it can extract an image from your post content (off by default).
1. If no image is found at this point, it will default to an image you set (not set by default).

= How do I add it to my theme? =

There are several methods, but in general, you would use this call:

`
<?php if ( function_exists( 'get_the_image' ) ) get_the_image(); ?>
`

To see all methods and options, refer to the `readme.html` file included with the theme download.

== Screenshots ==

You can view this plugin in action on my <a href="http://justintadlock.com" title="Justin Tadlock's blog">personal blog</a> (note the thumbnails).

== Changelog ==

**Version 0.8.1**

* Use correct `$attachment_id` variable instead of `$id`.
* Pass full `$image` array to the `get_the_image_meta_key_save()` function so that it saves correctly.
* Only use `before` and `after` arguments if an image is found.
* General code formatting updated.

**Version 0.8**

* Inline docs updates.
* Added the `before` argument to output HTML before the image.
* Added the `after` argument to output HTML after the image.
* Added the `thumbnail_id_save` argument to allow the attached image to be saved as the thumbnail/featured image.
* Get the post ID via `get_the_ID()` rather than the global `$post` object.
* Fixed debug notice with `$image_html`.
* Moved the `*_fetch_post_thumbnail_html` hooks into the main function and only fire them if displaying to the screen.
* Simplified the `meta_key` logic.
* Completely rewrote the `attachment` logic.
* Sanitize classes with `sanitize_html_class()`.

**Version 0.7**

* Deprecated and replaced functions that lacked the `get_the_image_` prefix.
* New cache delete functions that delete when a post or post meta is updated.
* Fixed notice when `image_scan` was used.

**Version 0.6.2**

* Updated the cache to save by post ID instead of a single object.
* Minor code adjustments.

**Version 0.6.1**

* Updated inline documentation of the code.
* Smarter `meta_key` logic, which allows a single meta key or an array of keys to be used.
* Set `custom_key` and `default_size` to `null` by default since they're deprecated.

**Version 0.6**

* Deprecated `custom_key` in favor of `meta_key`.
* Added the `meta_key_save` argument to allow users to save the image as a meta key/value pair.
* Added a `callback` argument to allow developers to create a custom callback function.
* Added a `cache` argument, which allows users to turn off caching.

**Version 0.5**

* Added support for persistent-caching plugins.
* Switched the `default_size` argument to `size` to be more in line with the WordPress post thumbnail arguments, but `default_size` will still work.
* Now using `wp_kses_hair()` to extract image attributes when using the `array` value for `format`.
* Image `alt` text will now use the attachment description if one has been given rather than the post title.
* Updated the `readme.html` instructions for using the plugin.

**Version 0.4**

* Dropped support for older versions of WordPress. Now only compatible with 2.9+.
* Added support for `the_post_thumbnail()`, which is WordPress 2.9's new image functionality.
* New function: `image_by_the_post_thumbnail()`.
* Documented more of the code, so the inline PHP doc is updated.
* Cleaned up some of the old legacy code that's no longer needed.

**Version 0.3.3**

* General code cleanup
* Added the `get_the_image` filter hook.

**Version 0.3.2**

* General code cleanup.
* More efficient and logical code.
* Beefed up the inline documentation so developers can better understand the code.
* Added a GPL license.txt file.

**Version 0.3.1**

* Fixed the default image and image scan features.

**Version 0.3**

* Changed methods of calling the image script.
* Added more parameters.