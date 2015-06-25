# === thePlatform Video Manager ===
Developed By: thePlatform for Media, Inc.  
Tags: embedding, video, embed, portal, theplatform, shortcode  
Requires at least: 3.7  
Tested up to: 4.1 
Stable tag: 1.3.4

Manage your content hosted by thePlatform and embed media in WordPress posts.

# == Description ==
View your content hosted by thePlatform for Media and easily embed videos from your
library in WordPress posts, modify media metadata, and upload new media. 
  
# == Installation ==

Copy the folder "thePlatform-video-manager" with all included files into the "wp-content/plugins" folder of WordPress. Activate the plugin and set your MPX credentials in the plugin settings interface.

# == Screenshots ==

1. thePlatform's Setting screen
2. View your media library, search for videos, and sort the returned media set
3. Modify video metadata
4. Easily embed videos from MPX into your posts
5. Upload media to your MPX account

# == Changelog ==

## = 1.4.4 = 
* Clear BOMs from UTF8 strings as needed
* Add placeholders on form text fields
* Hide mediabutton if jQueryUI.dialog is not avaialble

## = 1.3.3 =
* Set default media embed type to release
* Add a tpEmbed class to our embed dialogs

## = 1.3.2 =
* Fixed uploads in Firefox
* Correctly set the preview player in the Media Browser

## = 1.3.1 =
* Fixed an issue with the update method copying Basic Metadata settings incorrectly.

## = 1.3.0 =
* Allow multiple files to be uploaded
* Complete update to the plugin UX. Fixed numerous layouting issues across all the different pages
* The video upload dialog has been completely redesigned
* Video uploads should no longer fail randomly
* Support a wider range of file formats 
* Admins can choose the where the embed button should appear - media_buttons, tinymce plugin or both
* Fixed an issue where the shortcode did not append correctly in the text editor
* Accessing the plugin settings is now about 40% faster
* Media outside the availability window will now show in the media browser
* Admins can choose to display either the username, full name or email address instead of the numerical user id.
* Fixed the autoPlay shortcode attribute
* Admins can choose the Player embed type - either full player or a single embedded player
* Disabled players no longer show up in the Players dropdown

## = 1.2.3 = 
* Fix uploads sporadically not working in HTTPS 

## = 1.2.2 =
* Changed thePlatform's menu order number
* Fix references to ajaxurl
* Fix an issue where the Wordpress bar disappears in the about page

## = 1.2.1 =
* Renamed tabs in the plugin settings
* Disabled oLark in the plugin AJAX loaded pages

## = 1.2.0 =
* Account settings are now separate from the rest of the plugin preferences - Note this will require reconfiguring the plugin
* Added an About page
* Plugin settings are now cleaned up when switching accounts or deactivating
* Plugin settings now gracefully fall back when login fails
* Added support for EU accounts
* Updated metadata and upload field settings to allow Read/Write/Hide
* Default values are now provided for player ID and upload server ID when account is selected
* Fixed a bug where publishing profiles didn't work if they existing in more than one authorized account
* Added a new setting section - Embedding options
* Removed Full Video/Embed only setting
* Categories are now sorted by title instead of fullTitle
* Moved embed and edit buttons from the media into the metadata container
* Added a feature to set the featured image from the video thumbnail
* Completely redesigned the Upload, Browse, Edit and Embed pages
* Reworked plugin settings to match the new UI
* Verified up to WordPress 3.9
* Fixed Uploading issues
* Disabled unsupported Metadata fields
* Moved all MPX related functionality to it's own Menu slug
* Finer control over user capabilities:
	* 'tp_viewer_cap', 'edit_posts' - View the MPX Media Browser	
	* 'tp_embedder_cap', 'edit_posts' - Embed MPX media into a post
	* 'tp_editor_cap', 'upload_files' - Edit MPX Media
	* 'tp_uploader_cap' - 'upload_files' - Upload MPX media	
	* 'tp_admin_cap', 'manage_options' - Manage thePlatform's plugin settings
* Moved the embedding button into a TinyMCE plugin	
## = 1.1.1 =
* Fixed an issue where files would not always upload

## = 1.1.0 =
* Added an option to submit the Wordpress User ID into a custom field and filter by it
* Moved uploads to a popup window
* Added Pagination to the media views.
* Support for custom fields in editing and uploading.
* Add multiple categories during upload and editing.
* Added a filter for our embed output, tp_embed_code - The complete embed code
* Added a filter for our base embed URL, tp_base_embed_url - Just the player URL
* Added a filter for our full embed URL, tp_full_embed_url - The player URL with all parameters, applied after tp_base_embed_url
* Added filters for user capabilities:
	* 'tp_publisher_cap' - 'upload_files' - Upload MPX media
	* 'tp_editor_cap', 'upload_files' - Edit MPX Media and display the Media Manager
	* 'tp_admin_cap', 'manage_options' - Manage thePlatform's plugin settings
	* 'tp_embedder_cap', 'edit_posts' - Embed MPX media into a post
* Embed shortcode now supports arbitary parameters
* Removed Query by custom fields
* Removed MPX Namespace option
* Fixed over-zealous cap checks - This should fix the user invite workflow issues
* Fixed settings page being loaded on every adming page request
* Resized the media preview in edit mode
* Cleaned up the options page, hiding PID options
* Cleaned up some API calls
* Layout and UX enhancements
* Upload/custom fields default to Omit instead of Allow

## = 1.0.0 =
* Initial release

# == Short code parameters ==
* account	- (optional) - Account PID to use in the embed code, if omitted it will be taken from the account settings. It is highly recommended to keep this on all shortcodes
* player	- (required) - Player PID to use in the embed code
* media		- (required) - Release PID to load in the player
* width		- (optional) - Player width, if omitted the default value will be taken from the embedding preferences
* height	- (optional) - Player height, if omitted the default value will be taken from the embedding preferences
* mute		- (optional) - Force the player to be muted
* autoplay	- (optional) - Force autoplay on /embed/ players, if omitted the default value will be taken from the embedding preferences
* loop		- (optional) - Loop the release
* tag		- (optional) - iframe/script, if omitted the value will be taken from the embedding preferences
* embedded  - (optional) - true/false, if true the player will have /embed in the URI
* params	- (optional) - Custom string that will be appended to the embed URL

# == Configuration ==

This plugin requires an account with thePlatform's MPX. Please contact your Account Manager for additional information.

## = MPX Account Options =
* MPX Username - The MPX username to use for all of the plugin capabilities
* MPX Password - The password for the entered MPX username
* MPX Region - The region for the MPX account
* MPX Account - The MPX account to upload and retrieve media from

## = Embedding Preferences =
* Default Player - The default player used for embedding and in the Media Browser
* Embed Tag Type - IFrame or Script embed
* Player Embed Type - Video Only (/embed/) or Full Player
* RSS Embed Type - In an RSS feed, provide a link back to the Article, or an iframe/script tag
* Force Autoplay - Pass the autoplay parameter to embedded players
* Default Player Width - Initially based on the current theme content width
* Default Player Height - a 1.78 aspect ration value based on the content width

## = General Preferences =
* Filter Users Own Video - Filter by the User ID custom field, ignored if the User ID is blank
* User ID Custom Field - Name of the Custom Field to store the Wordpress User ID, (None) to disable
* Show User ID as - If the User ID Custom Field is visible to editors, we will substitute it by either the user Full Name, Email, Nickname or Username
* Plugin Embed button type - Determine if thePlatform button should appear as a media_button, a TinyMCE button or both
* MPX Upload Server - Default MPX server to upload new media to, Default Server will attempt to intelligently pick a server
* Default Publish Profile - If set, uploaded media will automatically publish to the selected profile. 

## = Filters =
* tp_base_embed_url - Just the player URL
* tp_full_embed_url - The player URL with all parameters, applied after tp_base_embed_url
* tp_embed_code - The complete embed code, with surrounding HTML, applied after tp_full_embed_url
* tp_rss_embed_code - The full embed code used for a RSS feed
* tp_viewer_cap, default - 'edit_posts' - View the MPX Media Browser	
* tp_embedder_cap, default - 'edit_posts' - Embed MPX media into a post
* tp_editor_cap, default - 'upload_files' - Edit MPX Media
* tp_uploader_cap - default - 'upload_files' - Upload MPX media	
* tp_admin_cap, default - 'manage_options' - Manage thePlatform's plugin settings