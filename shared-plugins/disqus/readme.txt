=== Disqus Comment System ===
Contributors: disqus, alexkingorg, crowdfavorite
Tags: comments, threaded, email, notification, spam, avatars, community, profile, widget
Requires at least: 2.8
Tested up to: 3.0
Stable tag: 2.40.11550

The Disqus comment system replaces your WordPress comment system with your comments hosted and powered by Disqus.

== Description ==

Disqus, pronounced "discuss", is a service and tool for web comments and
discussions. Disqus makes commenting easier and more interactive,
while connecting websites and commenters across a thriving discussion
community.

The Disqus for WordPress plugin seamlessly integrates using the Disqus API and by syncing with WordPress comments.

= Disqus for WordPress =

* Uses the Disqus API
* Comments indexable by search engines (SEO-friendly)
* Support for importing existing comments
* Auto-sync (backup) of comments with Disqus and WordPress database

= Disqus Features =

* Threaded comments and replies
* Notifications and reply by email
* Subscribe and RSS options
* Aggregated comments and social mentions
* Powerful moderation and admin tools
* Full spam filtering, blacklists and whitelists
* Support for Disqus community widgets
* Connected with a large discussion community
* Increased exposure and readership

== Installation ==

**NOTE: It is recommended that you backup your database before installing the plugin.**

1. Unpack archive to this archive to the 'wp-content/plugins/' directory inside
   of WordPress

  * Maintain the directory structure of the archive (all extracted files
    should exist in 'wp-content/plugins/disqus/'

2. From your blog administration, click on Comments to change settings
   (WordPress 2.0 users can find the settings under Options > Disqus.)

= More documentation =

Go to [http://disqus.com/help/wordpress](http://disqus.com/help/wordpress)

== Upgrading ==

(If you were using legacy mode you will need to re-install the plugin completely)

Replace the old plugin with the new plugin (the plugin must stay in
the disqus directory). If the old plugin directory was 'disqus-comment-system'
you should remove it, and the new plugin should be stored in 'disqus'.

== Changes ==

2.40

* Comments are now synced with Disqus as a delayed asynchronous cron event.
* Comment count code has been updated to use the new widget. (Comment counts
  must be linked to get tracked within "the loop" now).
* API bindings have been migrated to the generic 1.1 Disqus API.
* Pages will now properly update their permalink with Disqus when it changes. This is
  done within the sync event above.
* There is now a Debug Information pane under Advanced to assist with support requests.
* When Disqus is unreachable it will fallback to the theme's built-in comment display.
* Legacy mode is no longer available.
* The plugin management interface can now be localized.

== Support ==

* Visit http://disqus.com/help/wordpress for help documentation.

* Visit http://help.disqus.com for help from our support team.

* Disqus also recommends the [WordPress HelpCenter](http://wphelpcenter.com/) for extended help. Disqus is not associated with the WordPress HelpCenter in any way.
