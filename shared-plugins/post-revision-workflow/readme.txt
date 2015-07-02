=== Plugin Name ===
Contributors: cgrymala
Donate link: http://www.umw.edu/gift/
Tags: workflow, revision, editor, review
Requires at least: 3.0
Tested up to: 3.2.1
Stable tag: 0.2a

Allows anyone editing a published page or post to draft changes before those modifications go public.

== Description ==

This plugin adds a minor bit of workflow to the WordPress interface. When anyone edits a post or a page that has already been published, a few extra options will be available in the "Publish" metabox. Any time the appropriate option is chosen, the changes will be saved as a revision to the page or post, and the previous revision (the version that was already published) will remain published.

The person editing the page can choose from the following four options:

*   Publish these modifications normally - This will avoid running any of the functions in this plugin and publish the changes the way they would normally be published. This is always the default.
*   Save these changes as a revision, but don't notify anyone - This will revert the page or post to the version that was already published, saving the modifications as a post revision. This will not send out any notification emails.
*   Save these revisions as a draft and notify reviewer - This will revert the page or post to the version that was already published, saving the modifications as a post revision. This will also send out an email message to the email address entered in the options.
*   Publish these modifications, but notify a reviewer that changes were made - This will publish the changes normally, but will still dispatch a notification message to the email address provided.

If the third or fourth option is selected, a box will appear asking the user to provide an email address (or multiple addresses separated by commas) to which to send the notification message. If that box is left empty, the plugin will attempt to retrieve the value of the "dpn_reviewers" option from the individual site. If that option doesn't exist, the "dpn_reviewers" option will be retrieved from the network (if installed in a multisite environment). You can edit those options in the Settings -> Writing and Network Admin -> Settings -> Network Settings (multisite) screens within the administrative area. If neither of those options exist, the email address of the site's admin will be used.

== Installation ==

This plugin can be installed as a normal plugin, a multisite (network-active) plugin or a mu-plugin (must-use).

To install as a normal or multisite plugin manually:

1. Download the ZIP file of the current version
2. Unzip the file on your computer
3. Upload the post-revision-workflow folder to /wp-content/plugins

To install as a normal or multisite plugin automatically:

1. Visit Plugins -> Add New in your Site Admin (for normal WordPress installations) or Network Admin (for multisite WordPress installations) area
2. Search for Post Revision Workflow
3. Click the "Install" link for this plugin

To activate the plugin on a single site:

1. Go to the Plugins menu within the Site Admin area and click the "Activate" link

To network-activate the plugin on a multisite network:

1. Go to the Plugins menu within the Network Admin area and click the "Activate" link

To install as a mu-plugin:

1. Download the ZIP file of the current version
2. Unzip the file on your computer
3. Upload all of the files inside of the post-revision-workflow folder into your /wp-content/mu-plugins directory. If you upload the post-revision-workflow folder itself, you will need to move post-revision-workflow.php out of that folder so it resides directly in /wp-content/mu-plugins

== Frequently Asked Questions ==

= Where should I seek support if I find a bug or have a question? =

The best place to seek support is in [the official WordPress support forums](http://wordpress.org/tags/post-revision-workflow?forum_id=10#postform). If you don't get an answer there, you can try posting a comment on [the official plugin page](http://plugins.ten-321.com/post-revision-workflow/). Finally, you can [hit me up on Twitter](http://twitter.com/cgrymala) if you want me to take a look at something.

= How do I set the email address for the person or people that should be notified of changes? =

You can specify one or more email addresses on a site by visiting the Settings -> Writing screen, or set them at the network level (in multisite) by visiting the Settings -> Network Settings screen.

Email addresses are used with the following priority (higher items on the list overriding all instances of items lower on the list):
1. Email address(es) entered in the box within the "Publish" metabox on the post/page edit screen
2. "dpn_reviewers" option set in the options table
3. "dpn_reviewers" option set in the sitemeta table
4. Admin email address set within Site Admin -> Settings

= Will this plugin work in a multisite environment? =

Yes. It can be activated normally on each individual site or it can be network-activated.

= Will this plugin work in a multi-network environment? =

Yes. However, at this time, there are no action links to automatically activate it on all networks at once. Instead, it will have to be network-activated on each individual network (as desired).

== Screenshots ==

1. The way the "Publish" metabox looks by default with this plugin active
2. One of the options that does not dispatch a notification message is selected
3. One of the options that dispatches a notification message is selected, and the email address option box is visible

== Changelog ==

= 0.2a =
* Added ability to specify multiple reviewers in the input box (separated by commas)
* Added options interfaces to specify fallback email addresses at site and network levels
* Rewrote the way email addresses are pulled from the database
* Fixed JavaScript bug in IE
* Fixed radio button selection bug in Firefox

= 0.1a =
This is the first version of this plugin, so no changes have been made, yet.

== Upgrade Notice ==

= 0.2a =

* Multiple bugfixes and feature additions. Please update.

== To Do ==

1. Include multi-network activation options
2. Implement the ability to add this functionality to custom post types

== Known Issues ==

1. The interface to review and approve modifications (the default revision comparison built into WordPress) is not extremely user-friendly. Some training will most likely be necessary to teach reviewers how to identify and approve the appropriate revisions.
2. If multiple reviewers are notified of modifications, there is no easy way to let them all know when one of them reviews and approves (or potentially rejects) the changes.
3. There is no interface currently available to delete revisions, which means there is no way (other than taking no action at all) to actually reject any changes.
4. When a post is revised multiple times before the revisions are approved, the changes begin to cascade.