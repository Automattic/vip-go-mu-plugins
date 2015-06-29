=== Plugin Name ===
Contributors: danielbachhuber
Donate link: http://danielbachhuber.com/
Tags: posts, authors, metadata, post author
Requires at least: 3.0
Tested up to: 3.4.2
Stable tag: 1.4

A supremely customizable way to add information about the author at the top or bottom of a post, page, or other view.

== Description ==

Post Author Box allows you to append or prepend an informational box on any post, page, or other view without having to modify your theme. It has no opinions about what information is displayed or how it's presented. Configure the box with any of the following tokens:

* %display_name%
* %author_link%
* %author_posts_link%
* %first_name%
* %last_name%
* %description%
* %email%
* %avatar%
* %jabber%
* %aim%
* %post_date%
* %post_time%
* %post_modified_date%
* %post_modified_time%

You can use basic HTML and CSS for styling.

[Follow or contribute to this project on GitHub](https://github.com/danielbachhuber/Post-Author-Box)

== Installation ==

1. Download the plugin from WordPress.org
2. Upload it to the wp-contents/plugins directory of your website. 
3. Activate it and use tokens on the settings page to determine what information is presented in your box
4. Optionally style the Post Author Box using CSS ([WordPress.com Custom CSS](http://wordpress.org/extend/plugins/safecss/) is a neat plugin for this)
5. Optionally use `<?php post_author_box(); ?>` as a template tag within any loop in your theme. Supports custom display arguments

== Frequently Asked Questions ==

[Feel free to ask questions in the forum](http://wordpress.org/tags/post-author-box)

== Screenshots ==

== Upgrade Notice ==

= 1.4 =
Filter search values on init so any newly registered tokens appear in the admin UI as well

= 1.3 = 
Coding standards cleanup. No functional changes.

= 1.2 =
Use `<?php post_author_box(); ?>` as a template tag within any loop in your theme. Supports custom display arguments

= 1.1 = 
Display the Post Author Box on the homepage, in a feed or with other views of post content and support for three new tokens including %post_modified_date%

== Changelog ==

= 1.4 (Nov. 8, 2012) =
* Filter search values on init so any newly registered tokens appear in the admin UI as well

= 1.3 (Apr. 26, 2012) =
* Coding standards cleanup
* Plugin strings can now be properly translated

= 1.2 (May 19, 2011) =
* Added `<?php post_author_box(); ?>` as a template tag for using the Post Author Box within any loop in your theme. Supports custom display arguments ([Thanks Uche for the request](http://danielbachhuber.com/2011/03/20/post-author-box-v1-1-new-tokens-and-info-box-anywhere/#comment-4907))

= 1.1 (Mar. 20, 2011) =
* Support for %post_modified_date% and %post_modified_time% as tokens ([Thanks titush for the request](http://wordpress.org/support/topic/plugin-post-author-box-possible-to-have-post-modified-date))
* Support for %post_time% as a token
* Display the Post Author Box on the homepage, in a feed or with other views of post content ([Thanks 310ToOvertime for the request](http://wordpress.org/support/topic/plugin-post-author-box-can-author-be-displayed-on-home-page))
* Two filters ('pab_search_values' and 'pab_replace_values') for adding your own dynamic data to the Post Author Box

= 1.0.1 (Oct. 9, 2010) =
* Support for %author_link% and %author_posts_link%
* Minor bug fix

= 1.0 (Sept. 26, 2010) =
* First release!
