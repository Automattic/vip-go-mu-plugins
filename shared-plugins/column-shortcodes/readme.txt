=== Column Shortcodes ===
Contributors: codepress, tschutter, davidmosterd
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=ZDZRSYLQ4Z76J
Tags: columns, column, shortcodes, shortcode, divider, layout, posts, editor, wp-admin, admin, codepress, wordpress
Requires at least: 3.1
Tested up to: 3.5
Stable tag: 0.4

Adds shortcodes to easily create columns in your posts or pages.

== Description ==

Adds shortcodes to easily create columns in your posts or pages.

Sometimes you just need to divide your page into different columns. With this plugin you just select a column shortcode and it will add the column to the page.

There are 9 different column widths available from which you can make all combinations:

* half (1/2)
* one third (1/3)
* two third (2/3)
* one fourth (1/4)
* three fourth (3/4)
* one fifth (1/5)
* two fifth (2/5)
* three fifth (3/5)
* four fifth (4/5)
* one sixth (1/6)

A preset stylesheet is included, which you can also overwrite to you liking in your theme's stylesheet.

**Related Links:**

* http://www.codepress.nl/

== Installation ==

1. Upload column-shortcodes to the /wp-content/plugins/ directory
2. Activate Column Shortcodes through the 'Plugins' menu in WordPress
3. A shortcode icon is added to the top of the WYSIWYG editor.
4. Click the added icon and it will open a popup window from which you can pick your column shortcode.

== Frequently Asked Questions ==

= How do I add a column shortcode? =

**Standard**
The easiest way is to use the added icon on the top of your editor ( right next to the media icon ). This will open a popup window from which you can select a column icon by clicking on one of the shortcodes.

**Manual**
You could also type in the shortcode yourself inside the editor. The following shortcodes are available:

`
[one_half][/one_half]
[one_half_last][/one_half_last]
[one_third][/one_third]
[one_third_last][/one_third_last]
[two_third][/two_third]
[two_third_last][/two_third_last]
[one_fourth][/one_fourth]
[one_fourth_last][/one_fourth_last]
[three_fourth][/three_fourth]
[three_fourth_last][/three_fourth_last]
[one_fifth][/one_fifth]
[one_fifth_last][/one_fifth_last]
[two_fifth][/two_fifth]
[two_fifth_last][/two_fifth_last]
[three_fifth][/three_fifth]
[three_fifth_last][/three_fifth_last]
[four_fifth][/four_fifth]
[four_fifth_last][/four_fifth_last]
[one_sixth][/one_sixth]
[one_sixth_last][/one_sixth_last]
`

**Another option**
Another way to add shortcodes is to switch to HTML-view. On the top of editor you will now see all the shortcodes listed.
By default these buttons are hidden. If you'd like to use them you can add this to your theme's functions.php:

`
add_filter('add_shortcode_html_buttons', '__return_true' );
`

= Where do I add my content? =

When you have selected a shorcode it will be placed in you editor. You will see something like this:

`
[one_half][/one_half]
`

Make sure to place your content (text/images etc. ) between the two shortcodes, like so:

`
[one_half]My content goes here...[/one_half]
`

= How can I replace the default Shortcode CSS stylesheet? =

You can easily overwrite the existing stylesheet. For example you would like to add a margin between your columns, you just place the following in your theme's style.css:

`
.one_half  {
	width: 48%;
	margin-right: 2%;
}
.one_third  {
	width: 32%;
	margin-right: 1.3%;
}
.two_third  {
	width: 64%;
	margin-right: 2.6%;
}
.one_fourth  {
	width: 24%;
	margin-right: 1%;
}
.three_fourth  {
	width: 72%;
	margin-right: 3%;
}
.one_fifth  {
	width: 19%;
	margin-right: 1%;
}
.two_fifth  {
	width: 38%;
	margin-right: 2%;
}
.three_fifth  {
	width: 57%;
	margin-right: 3%;
}
.four_fifth  {
	width: 76%;
	margin-right: 3%;
}
.one_sixth  {
	width: 16%;
	margin-right: 0.6%;
}
`

= Will you be adding more shortcodes? =

We would love to hear your feedback and suggestions on this. Just send an email to <a href="mailto:info@codepress.nl">info@codepress.nl</a>.

= How can I contribute a translation? =

You will find a .po file in the languages folder which you can use. You can send the translation to <a href="mailto:info@codepress.nl">info@codepress.nl</a>.


== Screenshots ==

1. Editor with shortcode icon
2. Shortcode popup with shortcode selector
3. Editor with shortcodes
4. Example post with the use of column shortcodes

== Changelog ==

= 0.4 =

* added improvements made for inclusion on WordPress.com VIP ( thanks to danielbachhuber )
* added WordPress coding conventions ( see http://codex.wordpress.org/WordPress_Coding_Standards )
* added fix for script and style loading

= 0.3 =

* added RTL support and Hebrew language ( thanks to Rami Yushuvaey )
* added Slovak translation ( thanks to Branco from WebHostingGeeks )
* added column 4/5

= 0.2 =

* added french and spanish language ( thanks to Mikel Aralar )
* improved script loading
* shortcodes HTML-view buttons hidden by default. Enable them by adding this to your functions.php: `add_filter('add_shortcode_html_buttons', '__return_true' );`

= 0.1 =

* Initial release.