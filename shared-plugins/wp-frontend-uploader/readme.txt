=== Frontend Uploader ===
Contributors: rinatkhaziev, danielbachhuber, jtrees
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=rinat%2ekhaziev%40gmail%2ecom
Tags: frontend, image, images, media, uploader, upload, video, audio, photo, photos, picture, pictures, file, user generated content, ugc, frontend upload
Requires at least: 3.3
Tested up to: 4.1
Stable tag: 0.9.3
License: GPLv2 or later

This plugin allows your visitors to upload User Generated Content (media and posts/custom-post-types with media).

== Description ==

**What is Frontend Uploader?**

This plugin is a simple way for users to submit content to your site. The plugin uses a set of shortcodes to let you create highly customizable submission forms to your posts and pages. Once the content is submitted, it is held for moderation until you approve it. It’s that easy!

**5 Steps to using Frontend Uploader**

1. Install the plugin.
1. Insert the Frontend Uploader shortcode into a post. The basic form can be inserted using [fu-upload-form], please visit the FAQs for more complex forms.
1. Users submit content using the form.
1. Go to “Manage UGC” under the “Media” tab. Find the media item you’d like to approve, and click “Approve.”
1. Your user generated media is live.

**Exploring Customizations**

* You can modify the submission form as needed, and have users submit posts. Please visit the FAQ page for more information.
* This plugin can be applied to Posts, Pages, and Custom Post Types. You can enable this via Settings > Frontend Uploader Settings.
* In addition to the WordPress whitelisted file types, this also supports uploading of Microsoft Office and Adobe files, as well as various video and audio files. You can enable these file types via Settings > Frontend Uploader Settings.
* The plugin allows you to upload all filetypes that are whitelisted in WordPress. If you’d like to add more file types and are comfortable with theme development, there's a filter that'll allow you to add some exotic MIME-type.

You can also manage UGC for selected custom post types (Please refer to the plugin's settings page). By default, UGC is enabled for posts and attachments. If you want to be able to get any other post types UGC submissions just select desired post types at the plugin's settings page, and pass post_type='my_post_type' to the [fu-upload-form] shortcode.

== Translations ==

* Se habla español (Spanish) (props Rafael Calzada, gastonbesada)
* Мы говорим по-русски (Russian)
* Nous parlons français (French) (props dapickboy)
* Nous parlons français (Canadian French) (props rfzappala)
* Vi snakker norsk (Norwegian) (props André Langseth)
* Wir sprechen Deutsch (German) (props Joshua Trees)
* We spreken Nederlands (Dutch) (props Jaap van der Veen)
* ما فارسی صحبت می کنند (Persian) (props mojtabashahi)
* Falamos Português (Brazilian Portuguese) (props Murilo Pinto Pereira)

[Fork the plugin on Github](https://github.com/rinatkhaziev/wp-frontend-uploader/)

== Installation ==

1. Upload `frontend-uploader` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Tweak the plugin's settings in: Settings -> Frontend Uploader Settings
1. Use the following shortcode in post or page: [fu-upload-form]
1. Moderate uploaded files in Media -> Manage UGC menu
1. Moderate user posts in Posts -> Manage UGC

== Screenshots ==

1. Screenshot of plugin's UI (It's looks like standard media list table, with slightly better Parent column and additional row action: "Approve")
1. Default media upload form
1. Example of customized form
1. WYSIWYG editor in upload form

== Frequently Asked Questions ==

= I get a white screen or "0" when trying to upload a file =
The major cause of this is either request timeout or request exceeding maximum request size. This  means that either the file was uploading for too long or it was too big. Two PHP settings to look at are:
[max_execution_time](http://us1.php.net/manual/en/info.configuration.php#ini.max-execution-time) and [upload_max_filesize](http://us3.php.net/manual/en/ini.core.php#ini.upload-max-filesize). If you don't have any ability to modify these settings, please contact your hosting company's support.

= Shortcode parameters =

**Customizing Your Form with Shortcode Parameters**

Frontend Uploader uses shortcodes to insert a form into a page or post. The default form is [fu-upload-form], which will upload a media file.

If you would like to build a custom form, it must begin with [fu-upload-form] and end with [/fu-upload-form].

The [fu-upload-form] shortcode has several parameters that can modify its behavior:

1. `form_layout` => This determines whether the form is saved as a post (‘post’), as a media file (‘image’), or as a post with images (‘post_image’). The default is ‘image.’  Example: [fu-upload-form class="your-class" title="Upload your media" form_layout=”post”]
1. `title` => Add this [fu-upload-form] shortcode, and this will be the Headline that will be displayed before the form. Example: [fu-upload-form class="your-class" title="Upload your media"]
1. `class` => HTML class of the form, defaults to 'validate'. If you want your form being validated - do not remove validate class. If you would like to item to be required before a user can submit, you can set it to ‘required.’ Example: [input type="text" name="post_title" id="title" class="required"]
1. `success_page` => URL to redirect on successful submission, defaults to the URL where the form is being displayed. For security reasons this should be an URL on your site (no external links). You can use [fu-upload-response] shortcode to display success/error messages on the given page.
1. `category` => ID of category the post should be attached (only in post or post+image mode).
1. `post_id` => ID of the post the image should be attached to. Defaults to the post ID of the post the shortcode is on.
1. `post_type` => Any registered whitelisted post type. Defaults to 'post'. Works only in post and post+image modes.
1. `suppress_default_fields` => Override global setting for supressing default form fields (true or false).

Within [fu-upload-form], you can add various fields with shortcodes like these:

1. `[input type="text" name="post_title"]` => A text box for one line of text
1. `[textarea name="post_content"]` => A text box for multiple lines of text
1. `[input type="file" name="photo"]` => A file uploader
1. `[checkboxes name="foo" class="checkboxes" description="Pick a fruit" values="value:Description,124:Banana,cherry:Cherry"]`
1. `[radio name="foo" class="checkboxes" description="Pick a fruit" values="value:Description,124:Banana,cherry:Cherry"]`
1. `[select name="foo" class="select" description="Pick a fruit" values="apple:Apple,banana:Banana,cherry:Cherry"]`
1. `[input type="submit" class="btn" value="Submit"]` => A submit button

**Caveats**

You can modify the form as you'd like but you have to make sure that 'post_title' field is present, otherwise upload might fail

= Example of default media upload form =

Here's example of default form (*you don't need to enter all that if you want to use default form, just use `[fu-upload-form]`*):

`[fu-upload-form class="your-class" title="Upload your media"]
[input type="text" name="post_title" id="title" class="required" description="Title"]
[textarea name="post_content" class="textarea" id="ug_caption" description="Description (optional)"]
[input type="file" name="photo" id="ug_photo" class="required" description="Your Photo" multiple="multiple"]
[input type="submit" class="btn" value="Submit"]
[/fu-upload-form]`

= Where are the plugin's settings? =
You can find Frontend Uploader's settings under Settings > Frontend Uploader Settings.

= Can I get email notifications? =
Yes you can enable this in Settings > Frontend Uploader settings. By default the site admin will receive email notifications. If you’d like to change that to another email, you can also change that in settings.

= How are authors determined? =
If someone is logged in, their user profile is automatically linked to the post. Otherwise, you can enable an “Author Field” under Settings > Frontend Uploader that allows the users to write in their name.

= Are other filetypes supported? =
In addition to the WordPress whitelisted file types, Frontend Uploader also supports uploading of Microsoft Office and Adobe files, as well as various video and audio files. You can enable these file types via Settings > Frontend Uploader Settings.

= Where does the user submitted content go? =
If you used the default form, the uploaded file will go into Media > Manage UGC. You can have the submitted content go into Post > Manage UGC by setting the parameter: form_layout="post".

= Help, I pasted in the above shortcode and now my fields are duplicated =
As stated in previous answer, you don't need to put inner contents if you only need default form fields.
E.g. `[fu-upload-form class="your-class" title="Upload your media"]` will be enough to render the default form.
You can suppress rendering of default form fields with "Suppress default fields" checkbox in settings

= I want to customize my form =
You can include additional elements with a set of shortcodes
[input type="text" name="post_title" id="title" class="required" description="Title" multiple=""]
[select name="foo" class="select" id="ug_select" description="Pick a fruit" values="Apple,Banana,Cherry"]
[textarea name="post_content" class="textarea" id="ug_caption" description="Description (optional)"]
[checkboxes name="foo" class="checkboxes" description="Pick a fruit" values="value:Description,124:Banana,cherry:Cherry"]

= I want to be allow users to upload mp3, psd, or any other file restricted by default. =
You are able to do that within Frontend Uploader Settings admin page. The settings there cover the most popular extensions/MIME-types.
The trick is that the same file might have several different mime-types based on setup of server/client.
If you're experiencing any issues, you can set WP_DEBUG to true in your wp-config.php or put
`add_filter( 'fu_is_debug', '__return_true' );` in your theme's functions.php to see what MIME-types you are having troubles with.

[FileExt](http://filext.com/) is a good place to find MIME-types for specific file extension.

Let's say we want to be able to upload 3gp media files.

First we look up all MIME-types for 3gp: http://filext.com/file-extension/3gp

Now that we have all possible MIME-types for .3gp, we can allow the files to be uploaded.

Following code whitelists 3gp files, if it makes sense to you, you can modify it for other extensions/mime-types.
If it confuses you, please don't hesitate to post on support forum.
Put this in your theme's functions.php
`add_filter( 'fu_allowed_mime_types', 'my_fu_allowed_mime_types' );
function my_fu_allowed_mime_types( $mime_types ) {
	// Array of 3gp mime types
	// From http://filext.com (there might be more)
	$mimes = array( 'audio/3gpp', 'video/3gpp' );
	// Iterate through all mime types and add this specific mime to allow it
	foreach( $mimes as $mime ) {
		// Preserve the mime_type
		$orig_mime = $mime;
		// Leave only alphanumeric characters (needed for unique array key)
		preg_replace("/[^0-9a-zA-Z ]/", "", $mime );
		// Workaround for unique array keys
		// If you-re going to modify it for your files
		// Don't forget to change extension in array key
		// E.g. $mime_types['pdf|pdf_' . $mime ] = $orig_mime
		$mime_types['3gp|3gp_' . $mime ] = $orig_mime;
	}
	return $mime_types;
}`

= There's no captcha! =
The plugin uses Akismet as spam protection (you have to have Akismet installed and configured). Just enable Akismet support in the plugin's settings and voila.

= Configuration Filters =

= fu_manage_permissions =

By default Frontend Uploader could be managed with 'edit_posts' capability, if you want to change permissions, this is the right filter
`add_filter( 'fu_manage_permissions', create_function( '$cap', 'return "edit_others_posts"; ) );`

= fu_allowed_mime_types =

Allows you to add your custom MIME-types. Please note that there might be multiple MIME types per file extension.

`add_filter( 'fu_allowed_mime_types', 'my_fu_allowed_mime_types' );
function my_fu_allowed_mime_types( $mime_types ) {
	$mp3_mimes = array( 'audio/mpeg', 'audio/x-mpeg', 'audio/mp3', 'audio/x-mp3', 'audio/mpeg3', 'audio/x-mpeg3', 'audio/mpg', 'audio/x-mpg', 'audio/x-mpegaudio' );
	foreach( $mp3_mimes as $mp3_mime ) {
		$mime = $mp3_mime;
		preg_replace("/[^0-9a-zA-Z ]/", "", $mp3_mime );
		$mime_types['mp3|mp3_' . $mp3_mime ] = $mime;
	}
	return $mime_types;
}`

= fu_after_upload =

`add_action( 'fu_after_upload', 'my_fu_after_upload', 10, 3 );

function my_fu_after_upload( $attachment_ids, $success, $post_id ) {
	// do something with freshly uploaded files
	// This happens on POST request, so $_POST will also be available for you
}`

= fu_additional_html =

Allows you to add additional HTML to form

`add_action('fu_additional_html', 'my_fu_additional_html' );

function my_fu_additional_html() {
?>
<input type="hidden" name="my_custom_param" value="something" />
<?php
}`

= fu_is_debug =

If you're experiencing issues with upload it might be due to server misconfiguration, enabling debug mode will give you more detailed error messages

`add_filter( 'fu_is_debug', '__return_true' );`

= fu_upload_result =

This action runs after form was uploaded. Arguments are: (string) $layout (form layout), (array) $result - result of the upload.
`add_action('fu_upload_result', 'my_fu_upload_result', 10, 2 );

function my_fu_upload_result( $layout, $result ) {
	// do something
}`

== Changelog ==

= 0.9.2 (Nov 22, 2014) =
* PHP 5.2 compat for 0.9.1

= 0.9.1 (Nov 21, 2014) =
* Bugfix: don't texturize [fu-upload-form] shortcode's inner content (needed due to 4.01 default behavior changed)
* Translation: added pt_BR translation (props Murilo Pinto Pereira)

= 0.9 (Oct 22, 2014) =
* Feature: Akismet integration! Protects your site from spam submissions
* Added fu_upload_result_query_args filter
* A bunch of minor bugfixes and code refactoring

= 0.8.1 (Jul 24, 2014) =
* Bugfix: Don't try to include media script anywhere except "Manage UGC" screen. Otherwise it produces JS errors, potentially breaking some post edit screen features

= 0.8 (Jul 24, 2014) =
* Bugfix: re-attach media file to posts is working as expected now
* Bugfix: file inputs accept multiple files by default now
* Translation: added nl_NL translation

= 0.7.7 (Jul 9, 2014) =
* Feature: allow overriding default form fields (like category, post_id, etc) with customized inputs in the form


= 0.7.6 (Jul 9, 2014) =
* Bugfix: issues with success_page redirecting to wrong url in subfolder multisite install

= 0.7.5 (Apr 25, 2014) =
* Bugfix: make sure that result of upload of post_media is success when uploading post but no files /props petsuka

= 0.7.4 (Apr 24, 2014) =
* Bugfix: fix inconsistencies of nonces in admin views. /props EamonMcCambridg

= 0.7.3 =
* Bugfix: some potential php notices
* Feature: added fu_post_approved and fu_media_approved actions

= 0.7.2 =
* Updated Russian translation
* Fixed an issue where categories of uploaded post/media weren't properly saved

= 0.7.1 =
* Fixed fatal error being produced when trying to upload with iOS device
* Fixed issue with malformed query arguments when redirecting to upload result

= 0.7 =
* Meta fields get saved automatically
* Bugfix: title param of fu-upload-form now actually changes the title
* Better readme (props Steph Yiu)

= 0.6 (Oct 29, 2013) =
* Updated German translation
* Updated Spanish translation
* Hidden inputs are no longer getting wrapped in label and div
* Added an option to set a default file name
* Fixed category attribute of shortcode

= 0.5.9 (Aug 28th, 2013) =
* Introduced setting to disable default fields
* Fixed bug with inability to uncheck all extra file types

= 0.5.8 (July 25th, 2013) =
* Fixed bug with failing nonce check upon single item deletion
* Introduced 'fu_manage_permissions' filter to alter default permissions for managing UGC

= 0.5.7 (July 5th, 2013) =
* Determine if post type of uploaded post is allowed in the plugin's settings rather than than in all registered post types
* If uploadeded post has author set and it's one of the registered users of the blog, post_author is set to that user, otherwise saved as meta
* Set success value to true if no files were uploaded but post was uplaoded succesfully
* Add nested shortcodes after default fields, instead of replacing them

= 0.5.6 (June 26, 2013) =
* Prevent plugin activation if WP is older than 3.3

= 0.5.5 (June 5, 2013 ) =
* Added German translation

= 0.5.4 (May 19, 2013) =
* Fixed bugs with form layouts
* Better readme and FAQ section

= 0.5.3 (Apr 17, 2013) =

* Fixed potential fatal error *

= 0.5.1 (Apr 11, 2013) =

* Ability to autoapprove files( See settings )
* Bugfix: ensure that there's no PHP errors in some certain cases

= 0.5 (Apr 10, 2013) =

* Ability to pick files allowed for uploading from the plugin's settings
* Bugfix: admins won't get any notifications on unsuccessful upload any more

= 0.4.2 (Apr 3, 2013) =

* Minor updates
* Better readme on how to allow various media files

= 0.4 (Mar 30, 2013) =

* Ability to submit posts+files via [fu-upload-form form_layout="post_image|post|image"] where form_layout might be "post_image", "post", or "image". Defaults to "image". /props rfzappala
* Ability to submit and manage custom post types
* Ability to use visual editor for textareas
* Bugfixes /props danielbachhuber
* Under the hood improvements

= 0.3.1 (Jan 3, 2013) =

* Remove closure as it produces Fatal Error in PHP < 5.3

= 0.3 (Jan 2, 2013) =

* Fully compatible with 3.5 Media Manager: automatically adds id of approved picture to the gallery.
* Fix IE upload issue, props mcnasby
* fu_allowed_mime_types filter is working now

= 0.2.5 (Oct 18, 2012) =

* Fix potential Fatal Error on activation

= 0.2.4 (Oct 10, 2012) =

* Fix compatibility issue for upcoming WP 3.5

= 0.2.3 (Oct 5, 2012) =

* Massive UI Cleanup: added minimal css, and pretty notices
* Plugin settings: ability to notify site admins of new file uploads
* Added French translation. Props dapickboy

= 0.2.2 (Sep 2, 2012) =

* Hardened security. Even if user for some reason will allow PHP file uploads, they won't be uploaded.
* Added Russian translation
* Added translations for jquery.validate plugin

= 0.2.1.1 (August 30, 2021) =

* Added missing localization strings

= 0.2.1 (August 30, 2012) =

* Added l10n support, added Spanish translation. Props gastonbesada

= 0.2 (August 15, 2012) =

* Utilized support of "multiple" file tag attribute in modern browsers, that allows multiple files upload at once ( no IE )

= 0.1.2 (June 6, 2012) =

* Added localization strings

= 0.1.1 (May 23, 2012) =

* Feature: allow form customization
* Feature: re-attach attachment to different post

= 0.1 (May 21, 2012) =

* Initial release and poorly written readme
