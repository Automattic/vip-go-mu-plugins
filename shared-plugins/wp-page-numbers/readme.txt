=== WP Page Numbers ===
Tags: navigation, paging, page, numbers, archive, categories, plugin, seo
Requires at least: 2.3
Tested up to: 2.5
Stable tag: trunk

A simple paging navigation plugin for users and search engines. Instead of next and previous page it shows numbers and arrows. Settings available.

== Description ==

= User friendly navigation =
With page numbers instead of next and previous links users can easily navigate much quicker to the page they want. It is good for SEO (Search Engine Optimization) as well, because it creates a tighter inner link structure. Works with all well known browsers (Internet Explorer, Firefox, Opera and Safari).

= Updates 0.2 =
* 5 page numbers themes - See <a href="http://wordpress.org/extend/plugins/wp-page-numbers/screenshots/">screenshots</a> for a preview!
* Put some "reset"-code in the themes to make it look the same on all themes

= Settings / Options =

* Custom texts and arrows
* Maximum number of pages to show at the same time
* Custom stylesheet folder (else default is used)
* Turn stylesheet off
* Turn off page information (page 3 of 5)
* Turn off next and previous page
* Turn off the first and end page numbers
* Turn off the numbers
	
<a href="http://www.jenst.se/category/blogg">Live demo (at the bottom)!</a>

== Installation ==

1. Upload the FOLDER 'wp-page-numbers' to the /wp-content/plugins/
2. Activate the plugin 'WP Page Numbers' through the 'Plugins' menu in admin
3. Go to 'Options' or 'Settings' and then 'WP Page Numbers' to change the options

= Usage =

Replace the the 'next_posts_link()' and 'previous_posts_link()' with the code below in your theme (archive.php, index.php or search.php).<br />

<code><?php if(function_exists('wp_page_numbers')) { wp_page_numbers(); } ?></code>

== Frequently Asked Questions ==

= How do I report a bug? =

Contact me <a href="http://www.jenst.se/2008/03/29/wp-page-numbers">here</a>. Describe the problem as good as you can, your plugin version, WordPress version and possible conflicting plugins and so on.

= How can I support this plugin? =

Spread the word, report bugs and give med feedback.

== Screenshots ==

1. This is what the page numbers looks like in default mode.