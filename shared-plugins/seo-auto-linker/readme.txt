=== SEO Auto Linker ===
Contributors: chrisguitarguy, agencypmg
Donate link: http://www.pwsausa.org/give.htm
Tags: seo, links, internal links, automatic linking
Requires at least: 3.2
Tested up to: 3.5.1
Stable tag: 0.9.1

SEO Auto Linker allows you to automagically add links into your content. Great for internal linking!

== Description ==

SEO Auto Linker is an update to the much loved [SEO Smart Links](http://wordpress.org/extend/plugins/seo-automatic-links/ "SEO Smart Links") plugin.

The plugin automatically links words and phrases in your post, page or custom post type content.

The difference is that you no longer have to try and guess what links will appear.  Specify keywords in a comma separated list, type in the URL to which those keywords will link, specify how many links to the specified URL per post, and then specify the post type. SEO Auto Linker does the rest.

Bugs?  Problems?  [Get in touch](http://pmg.co/contact).

== Installation ==

1. Download the `seo-auto-linker.zip` file and unzip it
2. Upload the `seo-auto-linker` folder to your `wp-content/plugins` directory
3. In the WordPress admin area, click "Plugins" on the menu and activate SEO Auto Linker
4. Set up your keywords and sit back!

== Frequently Asked Questions ==

= I just upgraded to 0.7, where did my keywords go? =

Due to some changes that make SEO Auto Linker much more usable (an maintainable), keywords from versions before 0.7 must be migrated.  There is a second plugin included called SEO Auto Linker Migrator that will do this for you.  Just activate it and your done.

= When I specify keywords, will they all get linked? =

Sort of.  If you keyword list is `lorem, ipsum`, the word `lorem` OR the word `ipsum` will be linked to the specified URL.  If the content contains both `lorem` and `ipsum, they will only both be linked if you set the number of links per post to more than one for that keyword list.

= Will this slow my site down? =

If you add hundreds of keywords, the answer is probably yes.  However, SEO auto linker makes use of several wp_cache functions which, when combined with a persistent caching plugin, should help speed things up.  If you're running a large scale WordPress install, you should probably be using a caching plugin anyway.

= This is breaking my HTML! What gives? =

In order to keep things simple, SEO Auto Linker searches for some common elements in your HTML (headings, images, inputs, etc) and removes them before adding links, adding them back later. It can't predict every bit of HTML, unfortunately, so sometimes text in attributes or other text gets linked where it shouldn't.

= Does this automatically link custom fields too? =

Nope. Because custom fields (aka `wp_postmeta`) can be used for so many different things, it doesn't make sense to automatically link that content.

= Content inside of shortcodes isn't linked, what gives? =

SEO Auto Linker ignores content inside of shortcodes. If you find yourself using shortcodes inside a theme to do things like columns, etc, you may be [doing it wrong](http://justintadlock.com/archives/2011/05/02/dealing-with-shortcode-madness).

This was a deliberate decision and isn't likely to change.

= Can I disable links for a single post/page/whatever? =

Yes. Two ways:

1. Add the post's permalink to the site-wide blacklist on the SEO Auto Linker options page
2. Put `<!--nolinks-->` somewhere in the content of the post where you don't want links

== Screenshots ==

1. A look at the admin list of links

2. Editing a link

3. SEO Auto Linker options

== Changelog ==

= 0.9.1 =
* Fixes a bug that caused the sitewide blacklist to not save
* Add a css class to links (`auto-link`).

= 0.9 =
* Add support for nofollowing links
* Add the option to use unicode word boundaries `((?<!\pL))` and `((?!\pL))`
* Also adds a few new filters to force the use (or change) those word boundaries.

= 0.8.4 =
* Introduce the `seoal_only_single` filter to allow users to add links on archives, etc.

= 0.8.3 =
* Small bugfix on saving the options page. Killing a PHP warning.

= 0.8.2 =
* Adds a few filters. Nothing to be terribly excited about.

= 0.8 =
* A few small bugfixes
* Removing the migration plugin

= 0.7.2 =
* Added filters throughout the plugin

= 0.7.1 =
* Fixed versioning issue on migration plugin
* Fixed "leave page" warning on the link edit screen
* Cleaned up some sloppy saving functionality that was causign seo auto linker custom fields to be saved on all post types

= 0.7 =
* New and improved admin area
* Completed refactored codebase (all new bugs!)

= 0.6.3 =
* Use `preg_quote`

= 0.6.2 =
* Fix for some image errors folks were having

= 0.6.1 =
* Fixes bug that caused headers not to display

= 0.6 =
* Unicode support in keywords
* Sitewide and keyword specific blacklists. Props to [James](http://jamesb.biz/) for this feature!
* Shortcodes are now ignored by the plugin, so image captions should no longer break

= 0.5 =
* Headers with attributes now get caught by the regular expression to prevent linking within them
* Posts can no longer link to themselves

= 0.4 =
* caching removed (caused issues with content no showing up)

= 0.3 =
* Fixed a bug that allowed substrings within words to be linked.

= 0.2 =
* Fixed the replacement so it doesn't break images or inputs
* Fixed the post type selection for each keyword set

= 0.1 =
* The very first version.
* Support for automatic linking added

== Upgrade Notice ==

= 0.9.1 =
* Fixes the site wide blacklist saving issue.

= 0.9 =
* Upgrade if you need nofollow support
* Or if you're having trouble with unicode + not matching words.

= 0.8.4 =
* Introduce a new filter. No changes to functionality

= 0.8.3 =
* Versy small bugfix. Upgrade to make sure saving the options doesn't cause errors.

= 0.8.2 =
* Adds a few additional filters see [the docs](https://github.com/AgencyPMG/SEO-Auto-Linker/wiki/Filters)

= 0.8.1 =
* Fixes a bug that caused images within links to disappear
* Introduces the `<!--nolinks-->` disabler

= 0.8 =
* A few bug fixes causing errors with shortcodes
* Removing the SEO Auto Linker Migraton plugin.
* If you're upgrading from less than 0.7, you'll need to get 0.7.x first, then upgrade to 0.8. Please see the upgrade notice for 0.7 for more info.

= 0.7.2 =
* Nothing major -- if you're a developer interested in extending SEO Auto Linker, you should upgrade

= 0.7.1 =
* Fixes annoying versioning issue on the plugin screen
* Some other small updates to improve functionality

= 0.7 =
* Backwards incompatible upgrade, you must use another plugin (included) to migrate old keywords
* Much more usable admin area

= 0.6.3 =
* certain characters no longer cause errors on the front end

= 0.6.2 =
* Yet another images fix

= 0.6.1 =
* Upgrade to fix <h> tag errors

= 0.6 =
* Unicode support, no shortcode errors and URI blacklists.

= 0.3 =
Fixes the bug that allowed substrings of words to be linked.

= 0.1 =
SEO Auto Linker works pretty alright, so maybe you should use it.
