=== Storify ===
Contributors: Storify
Tags: storify, social media, embed, twitter, Facebook, YouTube, Flickr,  instagram, soundcloud, stocktwits, breakingnews, Post, posts, images, links, disqus, tumblr, rss 
Requires at least: 3.2
Tested up to: 3.9
Stable tag: 1.0.7

Brings the power of Storify, the popular social media storytelling platform to your WordPress site

== Description ==

Turn what people post on social networks into compelling stories. With [Storify](https://storify.com), you collect the best photos, video, Tweets and more from around the web and publish them as simple, beautiful stories embedded into your WordPress posts and pages. It's the best way to chronicle an event through what people share, whether it's a conference, wedding, election or natural disaster.

**Features:**

* Embed stories with ease &mdash; simply paste the URL to the story, and WordPress takes care of the rest.
* Easily add your stories to posts &mdash; just click the Storify button in the standard WordPress editor, and select from a list of your most recent stories.
* Create new stories and edit existing ones right from your WordPress dashboard.
* Adds SEO-friendly versions of your stories to each post, ensuring that your stories get properly index by search engines 
* Allows users with filtered html restrictions to embed stories.
* Extensive API to customize the plugin's functionality to meet your needs

== Installation ==

Simply search for the plugin via the plugins -> add new dialog and click install, or download and extract the plugin, and copy the the Storify plugin folder into your wp-content/plugins directory and activate.

== Frequently Asked Questions ==

= How do I add a Storify Story to a post? =

There are three ways: 

1. To create and publish a new story, simply click the Storify menu icon on the left side of your WordPress dashboard 
2. To add an existing story from your account, while editing or creating a post, click the Storify icon in the rich editor toolbar (the same toolbar where you select bold, italic, etc.), and select the story from your account you'd like to insert 
3. You can always add any existing story to a post, simply by including the story's full link on it's own line, e.g., https://storify.com/username/story-title

= Do I need a Storify account to use this plugin? =

You'll need a Storify account to use all of the plugin's features. If you don't already have an account you can [sign up now](https://storify.com) using your Facebook or Twitter account, otherwise, you will be prompted to create an account prior to publishing your first story.

= I am a developer, can I customize the functionality of the plugin? =

Yes. There are more than 20 API endpoints for developers to hook into using WordPress's standard `add_action()` and `add_filter()` architecture. Each is individually documented within the code itself, but virtually all the plugin's functionality can be customized to fit your needs.

= Can I extend the plugin's functionality or integrate it with my own, existing plugin? =

Yes. There are several public methods available to help developers quickly and easily leverage the Storify API such as `get_story()` and `get_user_data()`. Each is documented more thoroughly within the code itself, and can be called, for example, as `$WP_Storify->get_story( 'https://storify.com/url-to/a-story' );` to return a story object containing the story's embed code and associated metadata.

= Are requests to the Storify API cached at all? =

Yes. All requests to the Storify API (for user and story metadata) are cached using the standard WordPress transients API. By default, this will cache the requests to the database for up to an hour, but can be customized and extended through many popular caching plugins such as W3 Total Cache. There is also an API hook to modify the default TTL (`storify_ttl`);

= Is the Storify plugin available in my language? =

Yes and no. The Storify plugin is ready to be translated, but has not been translated yet. If you would like to translate the Storify plugin into your language, see the [Translating WordPress Page](http://codex.wordpress.org/Translating_WordPress).

== Screenshots ==

1. Create, edit, and publish stories directly from your WordPress dashboard
2. Storify menu icon in Admin sidebar
3. Easy access to "Add New Storify" button via WordPress 3.3 Admin Bar
4. Quickly add your existing stories to a post with the Storify browser integrating into the rich editor
5. Quickly add your existing stories to a post with the Storify browser integrating into the rich editor
6. Link to edit existing stories in the WordPress 3.3 Admin Bar
7. Link to edit existing stories on the edit post screen

== Changelog ==
= 1.0.7 =
* Bug Fixes for Dialog.php error as well as editor button issues in some environments.

= 1.0.6 =
* Support for SSL references

= 1.0.5 =
* Fix for non-javascript alternative link not properly closing the `noscript` tag and breaking some themes

= 1.0.4 =
* Story description now automatically prepended to post body when story URL is added to the post directly
* Fix for story not embedding when Storify username contained an underscore character
* Fix for plugin breaking page titles in administrative dashboard

= 1.0.3 =
* Fix for warning level error on installs running WordPress 3.2 or earlier

= 1.0.2 =
* Add/edit Storify screen now features responsive design
* Hashtags automatically added as post tags when publishing stories as posts
* Javascript and CSS files only load on administrative pages when needed
* Fixed bug where add/edit Storify screen would not fill entire height of window in Firefox
* Fixed bug where cache would not immediately invalidate when a story is edited
* Fixed bug which would break 404 errors when improper callback was passed as a URL parameter

= 1.0.1 =
* French translation support
* Better internationalization handling
* Fix for E_WARNING level error on story posts when WP_DEBUG was enabled resulting in non-javascript alternative link to story not appearing

= 1.0 =
* Initial Release
