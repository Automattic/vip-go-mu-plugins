=== IntenseDebate Comments ===
Contributors: IntenseDebate, automattic, beaulebens
Tags: widget, profile, community, avatars, spam, notification, email, threaded, comments, intense debate, intensedebate, intense, debate, comment system, moderation, wpmu
Requires at least: 3.0
Tested up to: 3.3
Stable tag: 2.9.3

IntenseDebate comments enhance and encourage conversation on your blog.  Build your reader community, increase your comments, & boost pageviews.

== Description ==
IntenseDebate Comments enhance and encourage conversation on your blog or website. Custom integration with your WordPress admin panel makes moderation a piece of cake. Comment threading, reply-by-email, user accounts and reputations, comment voting, along with Twitter and friendfeed integrations enrich your readers' experience and make more of the internet aware of your blog and comments which drives traffic to you!

Full comment and account data sync between Intense Debate and WordPress ensures that you will always have your comments stored locally on your own server.

== Installation ==
*Note: As is the case when installing any new plugin, it's always a good idea to backup your blog data before installing.*

1. After downloading the IntenseDebate plugin, unpack and upload the file to the wp-content/plugins folder on your blog. Make sure to leave the directory structure of the archive intact so that all of the IntenseDebate files are located in 'wp-content/plugins/intensedebate/'
2. You will need to activate the IntenseDebate plugin in order to see your new comment system. Go to the Plugins tab and find IntenseDebate Comments in the list and click **Activate**.
3. After activating proceed to the plugin settings page (under Settings > IntenseDebate) to configure your plugin. Don't forget to visit your account at [IntenseDebate.com](http://intensedebate.com) for *additional customization options*.

== Frequently Asked Questions ==
= Having Connection Issues During Install? =
In order for our plugin to work properly, our servers need to be able to get in contact with yours. Some common causes for communication errors:

1. You're attempting to install IntenseDebate on a local development server - your site must be online on the "public" internet
2. Your site is password protected (.htaccess or similar)
3. Your server is behind a firewall or 
4. A caching plugin might be configured incorrectly

For further assistance, please contact IntenseDebate support at support@intensedebate.com

= What is data synchronization? =
Comments made in IntenseDebate are automatically backed-up to your WordPress comment system, while your existing WordPress comments are automatically imported into IntenseDebate. You always have all of your comments so uninstalling is a one-click process if you change your mind. Syncing your accounts enables auto-login so logging into WordPress automatically logs you into IntenseDebate. Profile sync also means that comments you make are synchronized to both profiles so you maintain ownership of your profile.

= What about SEO? =
IntenseDebate outputs the standard WordPress comments enabling your comments to still be indexed by search engines that ignore JavaScript, while ensuring that visitors surfing with JavaScript disabled will be able to interact with comments made in IntenseDebate. Readers with JS disabled can comment in the original WP system and those comments will be imported into IntenseDebate.

= How do I sync my accounts? = 
After installing and activating the IntenseDebate plugin you will need to update your account settings in your Plugin Settings page and either create an IntenseDebate account or login to your IntenseDebate account. Your data synchronization and comment import will start as soon as you have successfully logged in.

**Please Note:**
Your comments may take several hours to import. The time of your import is dependent on how many other import requests we are receiving in addition to how many comments are currently being imported. 

== Screenshots ==
1. An example of the comment system itself.  Notice all the extra goodies like user profile information, reputation, threaded comments, comment voting, RSS feeds for everything, and more!
2. This is the enhanced moderation screen.  Note the extra information about the commenter (same drop down menu for each user from the previous screenshot is available here).
3. The settings page for the IntenseDebate plugin.  There are many ways to customize IntenseDebate to do just as much as you want it to.
4. First step in the registration process - syncing up your IntenseDebate account with your WordPress blog.
5. Second step of the registration process - the import to convert all your existing comments into IntenseDebate goodness.
6. Final step of the registration process - a few helpful links to get you started getting the most out of your new IntenseDebate comment system.

== Changelog ==
= 2.9.3 =
* Remove mentions of $wpmu which is deprecated
* Bump requirement to WP3.0
* Get rid of our JSON library
* Remove Facebook xd receiver file
* Remove no-longer-used reference to Facebook cross-domain receiver
* Update .pot file

= 2.9.2 =
* Remove unnecessary reference to wp-admin/includes/template.php
* Import all comments not marked as spam (handles custom statuses etc)

= 2.9.1 =
* Remove user account linking/auto-login
* Fix bug with jQuery selectors for jQ 1.5.2 compatibility
* Update links to ID.com admin pages
* Enhance comment moderation experience for WordPress.com users
* Improve styling on comment moderation panel for recent WP versions

= 2.9 =
* Now *requires* WordPress 2.8+
* Reduced query load
* Optimize sync queue system for better performance
* Improved option handling
* Improve mobile device support
* Improved compatibility with WP 3.0

= 2.8 =
* Added widgets for recent comments, top commenters, site comment stats, and most commented posts

= 2.7 =
* Added ability to explicitly log in as a WordPress.com user 
* Added basic connection diagnostics during installation
* More robust detection/loading of JSON libraries

= 2.6 =
* Fixed pass-by-reference bug for PHP 5.3, props Maciek P
* Minor modification to the way JSON-compat functions are loaded to work better with WP 2.9 under certain conditions

= 2.5 =
* Adjusted sync queue handling to handle bigger queues more reliably
* Removed DB logging entirely
* Added some API functions: get comment counts per post/total, get total approved comments, remotely retrieve queue, remotely cancel an operation, ability to lock queue temporarily
* Load comment details right before sync to avoid stale information syncing
* Improve duplicate comment handling
* Improve comment status syncing
* Fix bug in handling comments being enabled/disabled on a Post/Page
* Throttling on the number of outgoing requests to reduce loads on both ends of the system
* Ability to import a single Post/Page's comments
* Added partial support for Trash feature in WP 2.9+

= 2.4.2 =
* Stopped using the onload JS event to load ID because some people had other plugins/widgets that were clobbering it
* Fixed invalid path on Loading image
* Changed Facebook xd_receiver path to be root-relative
* Fixed 2 NOTICE errors, props Anilo.

= 2.4.1 =
* Lots of optimizations on when/where to load ID resources (CSS/JS) to improve page load times
* Switched to inline JS/CSS in the admin to prevent loading the WP engine again
* Now loading ID comment UI via the script object method, rather than direct <script> inclusion
* Removed some unnecessary options and just used smart defaults
* Changed so that there is no backup comments template -- your theme MUST have something available, even if it's very simple
* Made the option to reset the plugin available before import was completed
* Added the ability to reset your import any time during the installation process if you're having problems
* Standardized on no-www for URLs
* Reversed the order of comment imports (starts oldest first now)
* Added Ping/Pong for network diagnostics
* Cleaned up translatable strings and now shipping with a POT file for translations
* Improved comment count links within wp-admin
* Updated some screenshots
* Now using a stable tag, rather than trunk, in the wp.org plugins directory
* Made syncing more flexible when handling different timezones
* Now packaging Facebook xd_receiver ready for future functionality

= 2.3 =
* Fixed bug where a blank blog title would prevent authentication with ID
* Switched to using PHP native json_* functions where available to increase performance
* Limited size of outgoing requests to speed up comms and avoid maxing out request/response sizes
* Added tag to error_log info for easier debugging

= 2.2 =
* Fixed bug where versions of WP < 2.7 would turn off comment threading when saving Discussion Settings
* Improved performance of queue system (reduced DB hits)
* Improved overall compatibility with WPMU (props to Israel S. for contributed code!)
* Improved debugging/logging options/output
* Cleaned up/standardized use of constants
* Improved initial import process
* Improved security of the options page
* Fixed WP 2.8 JavaScript compatibility bug
* Improved syncing of comment moderations
* Improved translatability

= 2.1.1 =
* Fixed bug with initial import process introduced in 2.1

= 2.1 =
* Introduced moderation/discussion settings sync
* Improved integration of moderation panel
* Optimized moderation panel to load faster
* Fixed bug where you were logged out of ID if you saved your WP profile page
* Moved to WordPress coding style