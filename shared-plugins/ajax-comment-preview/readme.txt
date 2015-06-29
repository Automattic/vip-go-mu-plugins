=== Ajax Comment Preview ===
Tags: ajax, preview, comment, comments
Contributors: mdawaffe
Requires at least: 2.6
Tested up to: 2.7
Stable Tag: 2.0

Visitors to your site can preview their comments with a click of a button.

== Description ==

Other preview plugins don't know what sort of changes WordPress will make to a
visitor's comment, but this plugin uses AJAX and other buzzwords to send each
previewed comment through WordPress' inner voodoo.

The result?  With the click of a button, your site's visitors can preview their
comments *exactly* as they will appear when they submit them for realies.

== Installation ==

1. Upload the plugin (the whole folder the plugin came with) to your plugins
   folder: `wp-content/plugins/`
2. Activate the 'Ajax Comment Preview' plugin from the Plugins admin panel.
3. Go to the Options -> Ajax Comment Preview admin panel to configure the look
   of the preview.

== Frequently Asked Questions ==

= How do I change the look of the preview? =

Go to the Settings (called "Options" in WordPress 2.6 ) -> Ajax Comment Preview
admin panel.  From there you'll be able to specify the markup used to display
the comment being previewed.  The markup you enter will depend on what theme
your site is using.  If you're using Kubrick (the default theme for WordPress),
the settings that come installed with the plugin will work fine.  For other
themes, I suggest the following.

1. Go to the permalink page for a post on your site that has a few comments.
2. In your web browser, view the Page Source of that page.  You can usually do
   this by finding that option in your browsers Edit or View menu or in the menu
   that pops up when you right click on the page.
3. Find the section of code that corresponds to one of the comments.  Copy it
   into your clipboard.
4. Paste that code into the big text box in the Options -> Ajax Comment Preview
   admin panel.
5. Replace the text specific to that comment (author name, time, comment text,
   ...) with the plugin's special tags (`%author%`, `%date%`, `%content%`, ...).
6. Most themes' code has all the comments inside one big `<ol>`, `<ul>`, or `<div>`
   tag.  You'll probably need to put your preview markup inside that
   "parent" tag too.  Make sure it has the same class(es) as the tag in your
   theme's code.

= I click preview and nothing happens.  What do I do? =

Remember, you have to have WordPress version 2.6 or higher to use this plugin.  If you do:

1. Go to the plugin's Settings page.  Copy the HTML from the big text box to a *text* file
   (not a Word document) and save it.  Now you have a backup.
2. Delete everything in the big text box.
3. Type "TEST" (*without* the quote marks) in the big text box, then hit the "Update" button.
4. Go back to your blog, type in a comment and hit the preview button.
5. If you see TEST come back, there's probably a mistake in the HTML you entered in the big
   text box.  Double check it and try again.

= I didn't see TEST come back.  Now what? =

This plugin has two files: `ajax-comment-preview.php` and `ajax-comment-preview.js` .
Did you upload them both? (You did if you were a good blogger and followed the directions.)
Make sure both of those files are on your webserver and *in the same directory*.

= I saw TEST come back, but my comments template doesn't work, or only some of it shows up =

Are you serving your blog's webpages as XML documents (for example with the MIME type
`application/xhtml+xml`)?  If so (or if you don't know what that means), try putting
`xmlns="http://www.w3.org/1999/xhtml"` inside the very first HTML tag of your comment
template.  So if you had:

`<li class="comment">`

as the first line in your comment template, change it to

`<li class="comment" xmlns="http://www.w3.org/1999/xhtml">`

in the big text box.

= Still no luck.  Any more ideas? =

... Can you tell if there are any JavaScript errors when you load a post on your blog or
when you click the preview button?

Other than that, nope, I'm out of ideas.
