=== FeedWordPress ===
Contributors: Charles Johnson
Donate link: http://feedwordpress.radgeek.com/
Tags: syndication, aggregation, feed, atom, rss
Requires at least: 2.8
Tested up to: 3.0
Stable tag: 2010.0623

FeedWordPress syndicates content from feeds you choose into your WordPress weblog. 

== Description ==

* Author: [Charles Johnson](http://radgeek.com/contact)
* Project URI: <http://projects.radgeek.com/feedwordpress>
* License: GPL 2. See License below for copyright jots and tittles.

FeedWordPress is an Atom/RSS aggregator for WordPress. It syndicates content
from feeds that you choose into your WordPress weblog; if you syndicate several
feeds then you can use WordPress's posts database and templating engine as the
back-end of an aggregation ("planet") website. It was developed, originally,
because I needed a more flexible replacement for [Planet][]
to use at [Feminist Blogs][].

[Planet]: http://www.planetplanet.org/
[Feminist Blogs]: http://feministblogs.org/

FeedWordPress is designed with flexibility, ease of use, and ease of
configuration in mind. You'll need a working installation of WordPress or
WordPress MU (version [2.8] or later), and also FTP or SFTP access to your web
host. The ability to create cron jobs on your web host is helpful but not
required. You *don't* need to tweak any plain-text configuration files and you
*don't* need shell access to your web host to make it work. (Although, I should
point out, web hosts that *don't* offer shell access are *bad web hosts*.)

  [WordPress]: http://wordpress.org/
  [WordPress MU]: http://mu.wordpress.org/
  [2.8]: http://codex.wordpress.org/Version_2.8

== Installation ==

To use FeedWordPress, you will need:

* 	an installed and configured copy of [WordPress][] or [WordPress MU][]
	(version 2.8 or later).

*	FTP, SFTP or shell access to your web host

= New Installations =

1.	Download the FeedWordPress installation package and extract the files on
	your computer. 

2.	Create a new directory named `feedwordpress` in the `wp-content/plugins`
	directory of your WordPress installation. Use an FTP or SFTP client to
	upload the contents of your FeedWordPress archive to the new directory
	that you just created on your web host.

3.	Log in to the WordPress Dashboard and activate the FeedWordPress plugin.

4.	Once the plugin is activated, a new **Syndication** section should
	appear in your WordPress admin menu. Click here to add new syndicated
	feeds, set up configuration options, and determine how FeedWordPress
	will check for updates. For help, see the [FeedWordPress Quick Start][]
	page.
	
[FeedWordPress Quick Start]: http://feedwordpress.radgeek.com/wiki/quick-start

= Upgrades =

To *upgrade* an existing installation of FeedWordPress to the most recent
release:

1.	Download the FeedWordPress installation package and extract the files on
	your computer. 

2.	Upload the new PHP files to `wp-content/plugins/feedwordpress`,
	overwriting any existing FeedWordPress files that are there.
	
3.	Log in to your WordPress administrative interface immediately in order
	to see whether there are any further tasks that you need to perform
	to complete the upgrade.

4.	Enjoy your newer and hotter installation of FeedWordPress

== Using and Customizing FeedWordPress ==

FeedWordPress has many options which can be accessed through the WordPress
Dashboard, and a lot of functionality accessible programmatically through
WordPress templates or plugins. For further documentation of the ins and
outs, see the documentation at the [FeedWordPress project homepage][].

  [FeedWordPress project homepage]: http://feedwordpress.radgeek.com/

== Changelog ==

= 2010.0623 =

*	WORDPRESS 3.0 COMPATIBILITY / AUTHOR MAPPING INTERFACE ISSUES: I
	resolved a couple of outstanding issues with the author mapping
	interface (Syndication --> Authors), which were preventing new users
	from being created correctly and author mapping rules from being set up
	correctly. These partly had to do with new restrictions on user account
	creation introduced in WordPress 3.0; anyway, they should now be fixed.
	
*	MORE EFFICIENT SYNDICATED URL LOOKUPS: Several users noticed that the
	bug fix introduced in 2010.0528 for compatibility with post-listing
	plugins caused a lot more queries to the database in order to look up
	numerical post IDs from the URL provided to the filter. This shouldn't
	cause any major problems, but it is not as efficient as it could be; the
	code now takes advantage of a more efficient way of doing things,
	which usually will not require any additional database queries.

*	SIMPLEPIE FEED UPDATE ISSUES: If you have been having significant
	problems with getting feeds to update correctly, this may be the result
	of some bugs in the implementation of SimplePie caching that ships with
	WordPress (as of version 3.0). (You would most commonly experience this
	error if you continually saw errors such as "No feed found at <...>" in
	your updates.) Fortunately, SimplePie allows for a great deal of
	extensibility and this allows me to work around the problem; these
	error conditions should now be mostly eliminated when the underlying
	feed is valid.

*	UI: SHOW INACTIVE SOURCES: When you use the default unsubscribe option
	-- which turns off the subscription to a feed while preserving the posts
	from it and the syndication-related meta-data for the feed -- the
	unsubscribed feed can now easily be viewed in a special "Inactive"
	section of the Syndicated Sources page. (As a side benefit, if you've
	accidentally, or only temporarily, turned off the subscription to a
	feed, it is now much easier to restore the feed to being active, or to
	delete it permanently, if you prefer. 

*	UI: FEED FINDER / SWITCH FEED INTERFACE IMPROVEMENTS: changes to styling
	and options for the feed finder / switch feed, which should now make it
	easier, in some cases, to find alternative feeds, and make interface
	options more clearly visible. 

*	FILTERS: `syndicated_item_published` and `syndicated_item_updated` NOW
	PROPERLY AFFECT THE DATING OF POSTS. These filters used to affect some
	date-related settings, but not others -- and, most importantly, not the
	final date that is set for a post's publication or last-modified date
	in the WordPress database. Now, they do affect that, as they should.
	(Filters should receive, and return, a long integer, representing a Unix
	epoch relative timestamp.)

*	MAGIC URL TO CLEAR THE CACHE: Suppose that you need to clear the feed
	cache, for whatever reason; suppose, even, that you need to clear it on
	a regular basis. One way you might do this is by logging into the
	FeedWordPress administrative interface and going to Syndication -->
	Performance. Another way you might do it, now, is to simply send an
	HTTP request to a magic URL provided by FeedWordPress: if your blog is
	at example.com, the URL would be <http://example.com/?clear_cache=1>

= 2010.0602 =

*	CATEGORY BOX INTERFACE ELEMENT FIXED FOR WP 3.0: Stylesheet changes
	between WordPress 2.9.x and the WordPress 3.0 RC caused the Categories
	box under **Syndication --> Categories & Tags** to malfunction. This
	has been fixed.

*	LINK CATEGORY SELECTION BOX IN SYNDICATION ==> FEEDS FIXED FOR WP 2.8
	AND 2.9: A WP 3.0 compatibility change introduced in 2010.0531
	inadvertently broke the Syndicated Link Category selector under
	Syndication --> Feeds & Updates in WP 2.8 and WP 2.9, causing the post
	categories to be displayed in the selector rather than the link
	categories. This should now be fixed so that the selector will work
	correctly under both the current versions of WordPress and the 3.0 RC.

*	MORE PERMISSIVE HANDLING OF FEEDS WITH BAD CONTENT-TYPE HEADERS: One of
	the small advantages that MagpieRSS had over SimplePie is that it was
	more tolerant about parsing well-formed feeds that the remote web server
	happened to deliver with weird or incorrect HTTP Content-type headers.
	In feeds affected by this problem, the new SimplePie parser would simply
	fail to find a feed, due to its being led astray by the contents of the
	Content-type header. This version includes an extension to SimplePie's
	content-type sniffer that offers more permissive handling of the HTTP
	headers.

*	MORE FULL-TEXT "EXCERPTS" NOW PROPERLY SHORTENED. Version 2010.0528
	introduced code to control for cases in which elements intended for
	item summaries are (ill-advisedly) used to carry the full text of posts;
	past versions of FeedWordPress would simply include the full text of the
	post in the excerpt field, but newer versions now attempt to detect
	this condition when it arises and to head it off, by blanking out the
	excerpt field and filling it with an automatically generated short,
	plain text excerpt from the full content. This release broadens the
	test conditions that indicate when an excerpt field is treated as
	identical to the full text of the post, and should therefore improve
	the handling of some feeds (such as Google Reader feeds) where the full
	text of each post was still appearing in the excerpt field.

*	FILTERS: `syndicated_item_published` AND `syndicated_item_updated`
	FILTERS NOW ALLOW FILTER AUTHORS TO CHANGE POST TIMESTAMPS. You can now
	use the `syndicated_item_published` and `syndicated_item_updated` filter
	hooks to write filters or add-ons which directly change the post date
	and most-recently-updated timestamps on incoming syndicated posts. Props
	to niska for pointing out where the filters needed to be applied in
	order to change WordPress's internal timestamps for incoming posts. 
	
= 2010.0531 =

*	PERMALINK / CUSTOM FIELDS PROBLEM RESOLVED: An issue in 2010.0528 caused
	some posts to be imported without the proper syndication-related
	meta-data being attached (thus causing permalinks to point back to the
	aggregator website rather than to the source website, among other
	problems). This problem has been resolved (and a fix has been applied
	which will resolve the problem for any posts affected by this problem,
	if the original post is recent enough to still be available on the feed).

*	UI: The "Back End" section has been split into two separate sections --
	"Performance" (dealing with caching, database index, and other
	performance tweaks), and "Diagnostics" (dealing with debug mode,
	update logging, and a number of new diagnostic tests which I will be
	rolling out over the next few releases).

*	Several minor interface bug fixes and PHP warning notices eliminated.

= 2010.0528 =

#### Compatibility ####

*	SIMPLEPIE IS NOW USED TO PARSE FEEDS; NO MORE MAGPIERSS UPGRADES NEEDED:
	One of the biggest changes in this release is that FeedWordPress no
	longer depends on MagpieRSS to parse feeds, and has switched to the much
	more up-to-date and flexible SimplePie feed parser, which is included as
	a standard part of WordPress versions 2.8 and later. Using SimplePie will
	hopefully allow for better handling of feeds going further, and will
	allow me greater flexibility in determining how exactly the feed parser
	will operate. It also means that FeedWordPress no longer requires
	special upgrades to the WordPress core MagpieRSS files, and should
	eliminate quite a bit of complexity.

*	MAGPIERSS COMPATIBILITY LAYER FOR EXISTING FILTERS AND ADD-ONS: However,
	I have also implemented a compatibility layer to ensure that existing
	filters and add-ons for FeedWordPress which depended on the MagpieRSS
	data format *should not be broken* by the switch to SimplePie. Going
	forward, I recommend that new filters and add-ons be written to take
	advantage of the SimplePie object representations of items, feeds, etc.,
	rather than the MagpieRSS arrays, but the MagpieRSS arrays will still
	be available and older filters should continue to work as they have in
	the past.
	
*	COMPATIBILITY WITH WORDPRESS 2.9.x and 3.0: This release has been tested
	for the existing WordPress 2.9.x branch and with the upcoming release of
	WordPress 3.0. 	Changes in the user interface JavaScript between WordPress
	2.8.x and WordPress 2.9 caused the tag box interface element to break in
	the Syndication --> Categories & Tags settings page; changes in the API
	functions for adding new authors caused fatal errors under certain
	conditions in WordPress 3.0. These breakages have been fixed.

*	DROPPED LEGACY SUPPORT FOR WORDPRESS PRIOR TO 2.8: Because SimplePie is
	not included with versions of WordPress prior to 2.8, I have chosen to
	drop legacy support for WordPress versions 1.5 through 2.7. If you are
	using FeedWordPress with a version of WordPress before 2.8, you will
	have to upgrade your installation of WordPress in order to take
	advantage of this release.

*	PHP 5.3 COMPATIBILITY: A couple of compatibility issues, which were
	causing fatal errors amd ugly warnings for users of PHP 5.3,
	have been eliminated.

#### Features and Processing ####

*	INTERFACE REORGANIZATION: The interface restructuring, began with
	Version 2009.0612, has been completed. Catch-all settings pages have
	been eliminated entirely for pages that cover each aspect of handling
	a feed: Feeds & Updates, Posts & Links, Authors, Categories & Tags,
	and Back End handling of the database and diagnostic information.
	Extensive new interface hooks allow add-on modules to significantly
	change or extend the FeedWordPress admin interface and workflow. 

*	STORING INFORMATION FROM THE FEED IN CUSTOM FIELDS: Many users
	have written to request the ability to store information from elements
	in the feed in a custom field on each post. (So that, for example, if
	post includes a `itunes:duration` element, you could store the contents
	in a Custom Field called `duration` on the post (for a Theme to access
	later). The Custom Post Settings under Syndication --> Posts & Links now
	allow you to access any item or feed tag, using a syntax similar to
	a much-simplified version of XPath. See Posts & Links settings for
	details.

*	UPDATE-FREEZING ON MANUALLY EDITED POSTS: FeedWordPress now allows you
	to mark posts that have been manually edited, so that the changes you
	make will not be overwritten by later updates from the feed. If you make
	manual edits to a particular post, just check the "Manual editing"
	checkbox in order to protect your changes from being overwritten. If you
	want to block *all* posts from being updated after they are imported
	for the first time, a new "Updated Posts" setting in Posts & Links
	allows you to freeze all posts from a particular feed, or all syndicated
	posts.

*	SETTING: FEED-BY-FEED SETTINGS FOR WHERE PERMALINKS POINT TO: You've
	always been able to tell FeedWordPress whether permalinks for posts
	should point to the original source of the story or the local copy. Now
	you can choose different policies for different feeds, instead of one
	global policy for all feeds. (Of course, you can still use a global
	default if you prefer.)

*	SETTING: USER CONTROL OVER TIMING BASIS. You can now determine the
	schedule on which feeds are considered ready to poll for updates --
	by default feeds become ready for polling after about 1 hour. You can
	now increase or decrease the time window under Syndication --> Feeds &
	Updates. (However, please pay *CAREFUL ATTENTION* to the recommendations
	and DO NOT set the scheduling lower than 60 minutes unless you are
	ABSOLUTELY SURE that you have specific permission from webmaster who
	provides that specific feed to poll more frequently than that. If you
	set this too low (and about 60 minutes is the polite minimum if you
	haven't been given a different figure), most webmasters will consider
	the frequent hits on their server as rude, or even downright abusive.

*	OTHER SETTINGS: New settings also include the ability to stop FWP from
	resolving relative URLs within syndicated content, and the ability to
	choose whether FeedWordPress should indicate the comment feed from the
	original source, or the local comment feed, when providing the comment
	feed URL for a syndicated post.

#### Parsing ####

*	BETTER DATE HANDLING -- FEWER FLASHBACKS TO 1969 and 1970: FeedWordPress
	has made some bugfixes and some improvements in the logic for parsing
	dates. This should allow FeedWordPress to correctly parse more dates in
	more feeds; and, in the last resort, when FeedWordPress fails to
	correctly parse a date, to fall back to a more intelligent default. This
	should hopefully avoid most or all error conditions that have resulted
	in articles being erroneously dated to the dawn of the Unix epoch
	(31 December 1969 or 1 January 1970).
	
*	FULL-TEXT "EXCERPTS" NOW PROPERLY SHORTENED. Based on a straightforward
	reading of the existing RSS specs, it's reasonable for the
	rss:description element to be read as a plaintext summary or excerpt for
	the item containing the description -- with the full text of the item,
	if available, in another, better-suited element, such as the de facto
	standard content:encoded extension element. The problem is that uses of
	RSS rarely have much to do with anything like a straightforward reading
	of the specs. As a result, many actual RSS producers in the wild put the
	full text of the article in a description element. But since
	FeedWordPress has treated this text as a summary, this produces
	aggregated posts with lengthy "excerpts" containing the full text of the
	article. This release of FeedWordPress fixes the problem by doing a
	little digging before treating rss:description as a summary: if the
	description element is used properly as a plain text summary, then
	FeedWordPress will take the summary provided by the feed, rather than
	recreating its own excerpt from the full text; but if an RSS item has no
	full-text element other than description, FeedWordPress will treat the
	description element as the full text of the article, and generate a
	shortened excerpt automatically from that text.

#### API ####

*	TEMPLATE API: new template tags `get_local_permalink()` and
	`the_local_permalink()` allow you to access the permalink for a post on
	your aggregator site, even when FeedWordPress is rewriting permalinks to
	point to the original source site.

*	NEW HOOKS FOR ADD-ONS AND FILTERS: I have added a number of new hooks
	which allow add-on modules to filter more precisely, gather information
	at more points, and to enhance the FeedWordPress admin interface. For
	a list of new hooks and documentation, see the FeedWordPress
	documentation wiki at
	<http://feedwordpress.radgeek.com/wiki/add-ons-and-filters>

*	FILTER API: A number of new utility methods have been added to the
	SyndicatedPost 	class to make it easier for filters and add-ons to 

*	FILTER API: Globals $fwp_channel and $fwp_feedmeta DEPRECATED. These
	global variables, originally introduced to allow filters access to
	information about the source feed in `syndicated_item` filters (which
	were passed in through global 	variables rather than as parameters
	because of a bug in WP 1.5 which was then fixed in 1.5.1) have been
	DEPRECATED. If you have any filters or add-ons which still depend on
	these global variables, you should see about fixing them to access data
	about the source feed using the SyndicatedPost::link element instead.
	For documentation, see the FeedWordPress documentation wiki at
	<http://feedwordpress.radgeek.com/wiki/syndicatedpost> and
	<http://feedwordpress.radgeek.com/wiki/syndicatedlink>.

*	DIAGNOSTICS: I've included a number of new diagnostic options and
	messages, which should allow an experienced user to better investigate
	any problems that may crop up.

#### Bug Fixes ####

*	BUGFIX: & IN PERMALINKS NO LONGER CAUSING ATOM OR HTML VALIDATION
	EFFORTS: Many users reported an issue in which syndicating a feed with
	special XML characters in the URLs (& was the most common, since it is
	used to separate HTTP GET parameters) would cause the aggregator's
	feeds to produce invalid (malformed) XML. This update addresses the
	issue in Atom feeds. Unfortunately, it has not been technically possible
	to address the problem in RSS 2.0 feeds, due to limitations on
	WordPress's internal templates for RSS feeds.

*	BUGFIX: BROKEN URLS IN "POPULAR POSTS" AND SIMILAR PLUGINS SHOULD NO
	LONGER BE BROKEN. A number of users noticed an issue where plugins and
	templates that listed posts in locations outside of the post loop
	(for example, "Popular Posts"-style plugins that listed posts in the
	sidebar), often produced the wrong URL for post links. (Typically, all
	the posts listed would get the same wrong URL.) This should now be
	fixed. Thanks to Björn for sending in a quick fix!

*	MINOR BUGFIXES: This release includes a number of fixes to minor bugs
	and compatibility issues, including: silent failures of the "Syndicate"
	button, "Illegal Offset Type" error messages from MagpieRSS, and others.

= 2009.0707 =

*	BUGFIX: WORDPRESS 2.8 AJAX COMPATIBILITY ISSUES RESOLVED (blank or
	truncated "Syndicated Sites" administration page): Due to changes in the
	AJAX interface elements between WordPress 2.7 and WordPress 2.8, several
	FeedWordPress users encountered an issue where the front "Syndication"
	page in the FeedWordPress administrative interface would come up blank,
	without the normal "Syndicated Sites" list and "Update" control, or
	sometimes wth the boxes visible but one or both of them truncated, with
	only the title bar. This issue should now be resolved: with the new
	version of FeedWordPress, the compatibility issue that caused the
	disappearance should be eliminated, and if boxes are shown with only
	their handle visible, you should once again be able to drop down the
	rest of the box by clicking once on its title bar.

*	BUGFIX: TAG SETTING WIDGET FIXED. Due to changes in interface elements
	between WordPress 2.7 and WordPress 2.8, people using FeedWordPress with
	WordPress 2.8 found that the widget for setting tags to be applied to
	all syndicated posts, or all syndicated posts from a particular feed,
	no longer displayed "Add" and "Remove" buttons for individual tags. This
	issue has now been fixed, and the tagging widget should once again work
	more or less exactly like the tagging widget for individual posts in the
	normal WordPress admin interface.

= 2009.0618 =

*	BUGFIX: MYSTERY ERRORS WITH WITH WP_Http_Fsockopen HTTP TRANSPORT
	ELIMINATED: Thanks to a combination of a subtle bug in FeedWordPress,
	and changes to the HTTP transport code in WordPress, a number of users
	encountered an error in which any time they attempted to add a new feed
	through the FeedFinder interface, FeedWordPress would fail and display
	an HTTP request failure diagnostic message. The subtle bug has been
	fixed, and with it, most of these errors should now be eliminated.
	
	Be sure to upgrade your MagpieRSS to the most recent MagpieRSS version
	after you have insalled FeedWordPress 2009.0618, or this bug fix will
	not take effect.

= 2009.0613 = 

*	INTERFACE/BUGFIX: WORDPRESS 2.8 CATEGORY BOX FIX. Thanks to a subtle
	change in class names between the WordPress 2.7 and 2.8 stylesheets,
	category boxes in the FeedWordPress settings interface tended to overflow
	and have a lot of messy-looking overlapping text under WordPress 2.8.
	This has now been fixed.
	
*	FeedFinder FAILURE DIAGNOSTICS: When FWP's FeedFinder fails to find any
	feeds at a given URL (for example, when you are trying to add a
	subscription through the administrative interface and you run into an
	error message), FeedWordPress now provides more diagnostic information
	for the reasons behind the failure. If that helps you, great; if not,
	it should help me respond more intelligently to your support request..

= 2009.0612 =

*	WORDPRESS 2.8 COMPATIBILITY: FeedWordPress 2009.0612 has been tested for
	compatibility with the recent version 2.8 release of WordPress.

*	INTERFACE RESTRUCTURING: In order to avoid settings posts from becoming
	too crowded, and to modularize and better organize the user interface,
	new "Posts" and "Categories & Tags" subpages have been created under the
	"Syndication" menu. "Posts" controls settings for individal syndicated
	posts (such as publication status, comment and ping status, whether or
	not to use the original location of the post as the permalink, whether
	or not to expose posts to formatting filters, and so on). "Categories &
	Tags" controls settings for assigning new syndicated posts to categories
	and tags, such as categories or tags to apply to all syndicated posts,
	and how to handle categories that do not yet exist in the WordPress
	database. These subpages, like the Authors subpage, handle settings for
	the global default level and for individual syndicated feeds.

	Corresponding to these new subpages, the old Syndication Settings and
	Feed Settings subpages have been cleaned up and simplified, and now only
	link to the appropriate subpages for options that can be set in the
	Posts, Authors, or Categories & Tags subpages.
	
*	FEATURE: ADD CUSTOM SETTINGS TO EACH SYNDICATED POST: FeedWordPress has
	long had an interface for creating custom settings for each syndicated
	*feed* which could be retrieved in templates using the `get_feed_meta()`
	template function. But it had no feature for adding custom fields to
	each individual syndicated *post*. In response to requests from users, I
	have added the ability to apply custom fields to each individual
	syndicated post, using the new Syndication --> Posts subpage. You can
	set up custom fields to be applied to every syndicated post, or custom
	fields to be applied to syndicated posts from a particular feed.

*	FEATURE: MAGPIERSS VERSION CHECK AND UPGRADE: FeedWordPress will attempt
	to determine whether or not you are using the upgraded version of
	MagpieRSS that comes packaged with FeedWordPress. If not, it will throw
	an error on admin pages, and, if you are a site administrator, it will
	give you the option to ignore the error message, or to attempt an
	automatic upgrade (using a native file copy). If the file copy fails,
	FeedWordPress will offer some guidance on how to perform the upgrade
	manually.

*	BLANK POSTS PROBLEM NO LONGER OCCURS WITH OLD & BUSTED MAGPIERSS: Due
	to the fact that I relied on a content normalization that occurs in my
	upgraded version of MagpieRSS, but not in the old & busted version of
	MagpieRSS that ships with WordPress, until this version, if you tried to
	syndicate an Atom feed without having performed the (*strongly
	recommended*) MagpieRSS upgrade, all of the posts would come up with
	completely blank contents. That's not because MagpieRSS couldn't read
	the data, but rather because the new Magpie version puts that data in a
	location where the old version doesn't, and I was only looking in that
	newer location. Now it checks for both, meaning that posts will continue
	to display their contents even if you don't upgrade MagpieRSS. (But you
	**really should** upgrade it, anyway.)

*	BUGFIX: RELATIVE URI RESOLUTION FOR POST CONTENT RESTORED. Some time
	back, I added support for resolving relative URIs against xml:base on
	feeds that support it to the MagpieRSS upgrade in FeedWordPress. Then I
	took out code that did the same thing from the main FeedWordPress code.
	Of course, the problem is that some people, even though it is clearly
	stupid or evil to do so, still include relative URIs for images or links
	in posts on feed formats that do *not* adequately support xml:base
	(notably, RSS 2.0 feeds). In response to a user request, I have added
	this functionality back in, so that MagpieRSS will resolve any relative
	URIs that it knows how to resolve using xml:base, and then FeedWordPress
	will attempt to resolve any relative URIs that are left over afterwards.

*	BUGFIX: INTERFACE OPTION FOR SETTING SYNDICATED POST PUBLICATION STATUS
	ON A FEED-BY-FEED BASIS HAS BEEN RESTORED: Due to a version-checking
	bug, users of WordPress 2.7.x lost an option from the "Edit a syndicated
	feed" interface which allowed them to determine whether newly syndicated
	posts should be published immediately, held as "Pending Review," saved
	as drafts, or saved as private posts. (The option to change this
	setting globally remained in place, but users could no longer set it on
	a feed-by-feed basis.) The version-checking bug has been fixed, and the
	option has been restored.

*	BUGFIX: "ARE YOU SURE?" FATAL ERROR ELIMINATED AND SECURITY IMPROVED:
	Under certain circumstances (for example, when users have configured
	their browser or proxy not to send HTTP Referer headers, for privacy or
	other reasons), many features in the FeedWordPress administrative
	interface (such as adding new feeds or changing settings) would hit a
	fatal error, displaying only a cryptic message reading "Are you sure?"
	and a blank page following it. This problem has been eliminated by
	taking advantage of WordPress's nonce functions, which allow the
	security check which ran into this error to work properly even without
	receiving an HTTP Referer header. (N.B.: WordPress's nonce functions
	were first introduced in WordPress 2.0.3. If you're using FeedWordPress
	with an older version of WordPress, there's no fix for this problem:
	you'll just need to turn Referer headers back on. Sorry.)

*	BUGFIX: MANUALLY-ALTERED POST STATUS, COMMENT STATUS, AND PING STATUS NO
	LONGER REVERTED BY POST UPDATES: If you manually altered the post status,
	comment status, or ping status of a syndicated post from what it was set
	to when first syndicated -- for example, if you had a feed that was set
	to bring in new posts as "Pending Review," and you then marked some of
	the pending posts as "Published" and others as "Unpublished" -- then
	in previous versions of FeedWordPress, these manual changes to the
	status would be lost -- so that, for example, your Published or Unpublished
	articles would revert to Pending Review -- if the source feed made any
	upates to the item. This could make the Pending Review feature both
	unreliable and also extremely frustrating to work with. The good news is
	that this bug has since been fixed: if you manually update the status
	of a post, it will no longer be reverted if or when the post is updated.

*	BUGFIX: OCCASIONAL FATAL ERROR ON UPDATE ELIMINATED: Under certain
	limited conditions (specifically, when both the title and the content of
	a post to be updated are empty), an attempt to update the post would
	result in a fatal error. This has been fixed.

*	INTERFACE: "CONFIGURE SETTINGS" CONVENIENCE LINK ADDED TO CONFIRMATION
	MESSAGE WHEN A NEW FEED IS ADDED: When you add a new subscription to
	FeedWordPress, the message box that appears to confirm it now includes a
	handy link to the feed's settings subpage, so that you can quickly set
	up any special settings you may want to set up for the new feed, without
	having to hunt through the list of all your other subscriptions to pick
	out the new one.

*	INTERFACE: SIMPLIFYING AND CLARIFYING AUTOMATIC UPDATES SETTINGS. I have
	removed an interval setting for the cronless automatic updates which has
	confused many FeedWordPress users. In past versions of FWP, when you
	turned on automatic updates, you would be presented with a time interval
	setting which controlled how often FeedWordPress would check for feeds
	ready to be polled for updates. (That is, it DID NOT control how often
	feeds *would be polled*; it controlled how often FeedWordPress would
	*check* for feeds that *had become ready to poll*. The schedule on which
	feeds became ready for polling was still controlled either by requests
	encoded in elements within the feed itself, or else according to an
	internal calculation within FeedWordPress, averaging out to about 1 hour,
	if the feed did not include any scheduling request elements.) Since many
	users very often (and understandably) confused the purpose of this
	setting, and since the setting is for a feature that's actually very
	unlikely to require any manual control by the user, I have removed the
	setting; FeedWordPress now simply uses the default value of checking for
	feeds to poll every 10 minutes.

*	FEEDFINDER PERFORMANCE IMPROVEMENT: FeedWordPress's FeedFinder class
	now uses `array_unique()` to make sure that it doesn't waste time
	repeatedly iterating over and polling the same URI. Props to Camilo
	(<http://projects.radgeek.com/2008/12/14/feedwordpress-20081214/#comment-20090122160414>).

= 2008.1214 =

*	WORDPRESS 2.7 COMPATIBILITY: FeedWordPress has been tested for
	compatibility with the newly released WordPress 2.7. WordPress 2.7 has
	deprecated the Snoopy library for HTTP requests, which caused a fatal
	error for users who had not installed the MagpieRSS upgrade (or whose
	installation of the MagpieRSS upgrade was overwritten by a recent update
	of WordPress). FeedWordPress now handles things gracefully when Snoopy
	is not immediately available.

*	INTERFACE SPIFFED UP: Interface elements have been updated so that
	FeedWordPress's management interface fits in more naturally with the
	WordPress 2.7 interface (including a new logo and a number of small
	interface tweaks).

*	BUG WITH TAGS FOR SYNDICATED ARTICLES FIXED: Several users encountered a
	bug with the option to add tags to all syndicated posts under
	Syndication --> Settings -- if you told FeedWordPress to add more than
	one tag to all syndicated posts, instead of doing so correctly, it would
	add a *single* tag instead, whose name was composed of the names of all
	the tags you asked it to add. This bug was the result of nothing more
	dignified than a typographical error on my part. It has now been fixed.

*	MORE INFORMATION AVAILABLE WHEN FEEDWORDPRESS CAN'T FIND A FEED: When
	you enter a URL for a new syndication source, FeedWordPress uses a
	simple feed-finding algorithm (originally based on Mark Pilgrim's
	Universal Feed Finder) to try to determine whether the URL is the URL
	for a feed, or, if the URL points to an ordinary website rather than to
	a feed, whether there is a feed for that website. All well and good, but
	if FeedWordPress failed to find a feed, for whatever reason, it would
	typically return nothing more than a nasty little note to the effect of
	"no feed found," without any explanation of what went wrong.
	FeedWordPress now keeps track of error conditions from the HTTP
	requests that it uses in the course of looking for the feed, and so may
	be able to give you a bit more information about the nature of the
	problem if something goes wrong.


= 2008.1105 =

*	INTERFACE RESTRUCTURING AND SYNDICATION --> AUTHORS PAGE: As a first
	step towards modularizing and better organizing the user interface, a
	new "Authors" subpage has been created under the Syndication menu, which
	controls settings for syndicated authors, both at the global default
	level and at level of individual syndicated feeds.

*	BUG RELATED TO THE ATTRIBUTION OF POSTS TO THE WRONG AUTHOR FIXED: Some
	users encountered an issue in which posts by different authors on
	different blogs -- especially blogs generated by Blogger -- were
	mistakenly attributed to a single author. The problem was caused by the
	way in which FeedWordPress matches syndicated authors to user accounts
	in the WordPress database: normally, if two feeds each list an author
	with the same e-mail address, they are counted as being the same person.
	Normally this works well, but it creates an issue in cases where
	blogging software assigns a single anonymous e-mail address to users who
	do not want their real e-mail address published. This is, for example,
	what Blogger does (by giving all users a default e-mail address of
	<noreply@blogger.com> if they don't want their own e-mail address
	listed). FeedWordPress now allows the user to correct for this problem
	with a couple of new settings under **Syndication --> Authors**, which
	allow users to turn off e-mail based author matching for particular
	addresses, or, if desired, to turn it off entirely. By default, e-mail
	based author matching is still turned on, but disabled for a list of
	known generic e-mail addresses. Right now, the "list" consists entirely
	of <noreply@blogger.com>; if you know other addresses that should be
	added, please [contact me](http://radgeek.com/contact) to let me know.

	Please note that if you have already encountered this issue on your
	blog, upgrading FeedWordPress will prevent it from re-occurring in the
	future, but you still need to do two other things to fix the existing
	problem on your blog.
	
	First, for each feed where posts have been mis-attributed, you need to
	change the existing author mapping rules to re-map a a syndicated
	author's name to the proper target account. Go to **Syndication -->
	Authors**, select the feed you want to change from the drop-down list,
	and then change the settings under the "Syndicated Authors" section.
	(You will probably need to select "will be assigned to a new user..." to
	create a new user account with the appropriate name.)
	
	Second, for each feed where posts have been mis-attributed, you need to
	re-assign already-syndicated posts that were mis-attributed to the
	correct author. You can do that from **Syndication --> Authors** by
	using the author re-assignment feature, described below.

*	AUTHOR RE-ASSIGNMENT FOR A PARTICULAR FEED: The author settings page
	for each syndicated feed, under **Syndication --> Authors**, now
	includes an section titled "Fixing mis-matched authors," which provides
	an interface for re-assigning or deleting all posts attributed to a
	particular author on a particular feed.

*	SUPPORT FOR `<atom:source>` ELEMENT IN SYNDICATED FEEDS: Some feeds
	(for example, those produced by FeedWordPress) aggregate content from
	several different sources, and include information about the original
	source of the post in an `<atom:source>` element. A new setting under
	**Syndication --> Options** allows you to control what FeedWordPress
	will report as the source of posts syndicated from aggregator feeds in
	your templates and feeds: you can have FeedWordPress report that the
	source of a post is the aggregator feed itself, or you can have it
	report that the source of a post is the original source that the
	aggregator originally syndicated the post from.
	
	By default, FeedWordPress will report the aggregator, not the original
	source, as the source of a syndicated item.

*	LOAD BALANCING AND TIME LIMITING FEATURES FOR UPDATES: Some users have
	encountered issues due to running up against PHP execution time limits
	during the process of updating large syndicated feeds, or a very large
	set of syndicated feeds. FeedWordPress now has a feature that allows you
	to limit the total amount of time spent updating a feed, through the
	"Time limit on updates" setting under **Syndication --> Options**. By
	turning on this setting and adjusting the time limit to a low enough
	figure to avoid your PHP installation's time-out setting. (PHP execution
	time limits are usually in the vicinity of 30 seconds, so an update
	time limit of 25 seconds or so should provide plenty of time for updates
	while allowing a cushion of time for other, non-update-related functions
	to do their work.)
	
	If feed updates are interrupted by the time limit, FeedWordPress uses
	some simple load balancing features to make sure that updates to other
	feeds will not be blocked by the time-hogging feed, and will also make
	sure that when the interrupted update is resumed, FeedWordPress will
	skip ahead to resume processing items at the point at which it was
	interrupted last time, so that posts further down in the feed will
	eventually get processed, and not get blocked by the amount of time it
	takes to process the items higher up in the feed.

*	`guid` INDEX CREATION BUTTON: FeedWordPress frequently issues queries on
	the `guid` column of the WordPress posts database (since it uses post
	guid URIs to keep track of which posts it has syndicated). In very large
	FeedWordPress installations, you can often significantly improve
	performance by creating a database index on the `guid` column, but
	normally you would need to poke around with MySQL or a tool like
	phpMyAdmin to do this. FeedWordPress can now save you the trouble: to
	create an index on the `guid` column, just go to
	**Syndication --> Options**, and mash the button at the bottom of the
	"Back End" section.

= 2008.1101 =

* 	INTERFACE BUG THAT PREVENTED ADDING NEW SITES FIXED: The UI reforms in
	FWP 2008.1030 unintentionally introduced a bug that prevents clean
	installations of FeedWordPress from providing an input box for adding
	new feeds to the list of syndicated feeds. This bug has been fixed.

= 2008.1030 =

*	WORDPRESS 2.6 COMPATIBILITY: FeedWordPress should now be compatible with
	WordPress 2.6, and should work more or less seamlessly with the new post
	revision system. A bug which caused multiple new revisions to be created
	for posts on certain feeds, regardless of whether or not the item had
	been updated, has been fixed.

*	INTERFACE IMPROVEMENTS: The user interface has been substantially
	restyled to fit in better with the visual style of WordPress 2.5 and
	2.6.

*	YOUTUBE BUG FIXED: POSTS SYNDICATED THROUGH AN AUTOMATIC UPDATE ARE NO
	LONGER STRIPPED OF `<OBJECT>` TAGS AND CERTAIN OTHER HTML ELEMENTS: Due
	to the way that some versions of WordPress process posts that are
	inserted into the database when no user is logged in, many users
	experienced an issue where YouTube videos and other content using the
	HTML `<object>` tag would be stripped out of posts that were syndicated
	during an automatic update. (Posts that were syndicated through manual
	updates from within the WordPress Dashboard were not affected, because
	the issue does not arise when an update is executed under a logged-in
	administrator's credentials.) This bug has now been fixed; YouTube
	videos and other content using `<object>` tags should now appear
	properly in syndicated posts, regardless of the way in which the post
	was syndicated.

*	AJAX BUGS FIXED: Bugs which blocked the normal operation of WordPress
	2.5's AJAX interface elements when FeedWordPress was activated have been
	fixed.

*	TAG SUPPORT: A couple of features have been introduced to take advantage
	of the tagging features in WordPress 2.3.x, 2.5.x, and 2.6.x. Now, when
	unfamiliar categories are encountered for posts on a feed, you can
	choose for FeedWordPress (1) to drop the category; (2) to drop the
	category and to filter out any post that does not match at least one
	familiar category; (3) to create a new category with that name, or,
	now, you can also have FeedWordPress (4) create a new *tag* with that
	name. This option can be set site-wide under Syndication --> Options,
	or it can be set on a feed-by-feed basis in a feed's Edit screen.
	
	In addition, you can now set particular tags to apply to all incoming
	syndicated posts, under Syndication --> Options, or you can set tags
	to apply to all incoming syndicated posts from a particular feed in that
	feed's Edit screen.

* 	FORMATTING FILTERS: There is a new option available under Syndication ->
	Options which allows users to choose whether or not to expose syndicated
	posts to being altered by formatting filters. By default, FeedWordPress
	has always protected syndicated posts (which are already in display-ready
	HTML when they are syndicated) from being reformatted by formatting
	filters. However, this approach means that certain plugins which depend
	on formatting filters (for example, to add "Share This" bars or relevant
	links to the end of a post) are blocked from working on any syndicated
	posts. If you want to use one of these plugins together with
	FeedWordPress, you can now do so by changing the "Formatting Filters"
	setting from "Protect" to "Expose."

* 	`<atom:source>` ELEMENTS NOW INCLUDED IN ATOM FEED: Atom 1.0 provides
	a standard method for aggregators to indicate information about the original source of
	a syndicated post, using the `<atom:source>` element. FeedWordPress now 
	introduces standard `<atom:source>` elements including the title, homepage, and
	feed URI of the source from which a syndicated post was syndicated. Cf.
	<http://www.atomenabled.org/developers/syndication/atom-format-spec.php#element.source>

*	MODULARIZATION OF CODE: The code for different elements of FeedWordPress
	has been broken out into several modules for easier inspection,
	documentation, and maintenance of the code.

*	VERSIONING SCHEME CHANGED: FeedWordPress's feature set has proven stable
	enough that it can now be removed from beta status; a good thing, since
	I was very quickly running out of version numbers to use. New releases
	of FeedWordPress will have version numbers based on the date of their
	release.

= 0.993 =

*	WORDPRESS 2.5.1 COMPATIBILITY: FeedWordPress should now be compatible
	with WordPress 2.5.1.

*	WORDPRESS 2.5 INTERFACE IMPROVEMENTS: FeedWordPress's Dashboard
	interface has undergone several cosmetic changes that should help it
	integrate better with the WordPress Dashboard interface in WordPress
	version 2.5.x.

*	SYNDICATED POSTS CAN BE MARKED AS "PENDING REVIEW": WordPress 2.3 users
	can now take advantage of WordPress's new "Pending Review" features for
	incoming syndicated posts. Posts marked as "Pending Review" are not
	published immediately, but are marked as ready to be reviewed by an
	Administrator or Editor, who can then choose to publish the post or
	hold it back. If you want to review syndicated posts from a particular
	feed, or from all feeds, before they are posted, then use
	Syndication --> Syndicated Sites --> Edit or Syndication --> Options to
	change the settings for handling new posts.

*	AWARE OF NEW URI FOR del.icio.us FEEDS: Previous releases of
	FeedWordPress already automatically split del.icio.us tags up
	appropriately appropriately when generating categories. (del.icio.us
	feeds smoosh all the tags into a single `<dc:subject>` element,
	separated by spaces; FeedWordPress un-smooshes them into multiple
	categories by separating them at whitespace.) Unfortunately, del.icio.us
	recently broke the existing behavior by changing host names for their
	feeds from del.icio.us to feeds.delicious.com. Version 0.993 accounts
	for the new host name and un-breaks the tag splitting.
	
= 0.992 =

*	AUTHOR RE-MAPPING: FeedWordPress now offers considerable control over
	how author names on a feed are translated into usernames within the
	WordPress database. When a post by an unrecognized author comes in,
	Administrators can now specify any username as the default username to
	assign the post to by setting the option in Syndication --> Options
	(formerly FeedWordPress only allowed you to assign such posts to user
	#1, the site administrator). Administrators can also create re-mapping
	rules for particular feeds (under Syndication --> Syndicated Sites -->
	Edit), so that (for example) any posts attributed to "Administrator"
	on the feed <http://praxeology.net/blog/feed/> will be assigned to
	a user named "Roderick T. Long," rather than a user named
	"Administrator." These settings also allow administrators to filter out
	posts by particular users, and to control what will happen when
	FeedWordPress encounters a post by an unrecognized user on that
	particular feed.
	
*	BUG RELATED TO URIS CONTAINING AMPERSAND CHARACTERS FIXED: A bug in
	WordPress 2.x's handling of URIs in Blogroll links created problems for
	updating any feeds whose URIs included an ampersand character, such as
	Google News RSS feeds and other feeds that have multiple parameters
	passed through HTTP GET. If you experienced this bug, the most likely
	effect was that FeedWordPress simply would not import new posts from a
	feed when instructred to do so, returning a "0 new posts" response. In
	other cases, it might lead to unpredictable results from feed updates,
	such as importing posts which were not contained in the feed being
	syndicated, but which did appear elsewhere on the same website. This bug
	has, hopefully, been resolved, by correcting for the bug in WordPress.

= 0.991 =

*	WORDPRESS MU COMPATIBILITY: FeedWordPress should now be compatible with
	recent releases of WordPress MU. Once FeedWordPress is made available
	as a plugin, each individual blog can choose to activate FeedWordPress
	and syndicate content from its own set of contributors.

*	DISPLAY OF MAGPIE WARNINGS: A number of MagpieRSS warnings or error
	messages that were displayed when performing an automatic update are
	no longer displayed, unless debugging parameters have been explicitly
	enabled.

*	BUG RELATED TO INTERNATIONAL CHARACTERS IN AUTHOR NAMES FIXED: Due to a
	subtle incompatability between the way that FeedWordPress generated new
	user information, and the way that WordPress 2.0 and later added new
	authors to the database, FeedWordPress might end up creating duplicate
	authors, or throwing a critical error message, when it encountered
	authors whose names included international characters. This
	incompatability has now been fixed; hopefully, authors with
	international characters in their names should now be handled properly.

*	`<media:content>` BUG IN MAGPIERSS FIXED: A bug in MagpieRSS's handling
	of namespaced elements has been fixed. Among other things, this bug
	caused items containing a Yahoo MediaRSS `<media:content>` element (such
	as many of the feeds produced by wordpress.com) to be represented
	incorrectly, with only a capital "A" where the content of the post
	should have been. Feeds containing `<media:content>` elements should now
	be syndicated correctly.

*	update_feedwordpress PARAMETER: You can now use an HTTP GET parameter
	(`update_feedwordpress=1`) to request that FeedWordPress poll its feeds
	for updates. When used together with a crontab or other means of
	scheduling tasks, this means that you can keep your blog automatically
	updated on a regular schedule, even if you do not choose to use the
	cron-less automatic updates option.

*	Some minor interface-related bugs were also fixed.


= 0.99 =

Version 0.99 adds several significant new features, fixes some bugs, and
provides compatability with WordPress 2.2.x and 2.3.x.

*	WORDPRESS 2.2 AND 2.3 COMPATIBILITY: FeedWordPress should now be
	compatible with WordPress version 2.2 and the upcoming WordPress
	version 2.3. In particular, it has been tested extensively against
	WordPress 2.2.3 and WordPress 2.3 Release Candidate 1.

*	AUTOMATIC UPDATES WITHOUT CRON: FeedWordPress now allows you to
	automatically schedule checks for new posts without using external task
	scheduling tools such as cron. In order to enable automatic updates, go
	to **Syndication --> Options** and set "Check for new posts" to
	"automatically." For details, see "Automatic Feed Updates" in
	README.text.

	An important side-effect of the changes to the update system is that if
	you were previously using the cron job and the `update-feeds.php` script
	to schedule updates, you need to change your cron set-up. The old
	`update-feeds.php` script no longer exists. Instead, if you wish to use
	a cron job to guarantee updates on a particular schedule, you should
	have the cron job fetch the front page of your blog (for example, by
	using `curl http://www.zyx.com/blog/ > /dev/null`) instead of activating
	the `update-feeds.php` script. If automatic updates have been enabled,
	fetching the front page will automatically trigger the update process.

*	INTERFACE REORGANIZATION: All FeedWordPress functions are now located
	under a top-level "Syndication" menu in the WordPress Dashboard. To
	manage the list of syndicated sites, manually check for new posts on
	one or more feeds, or syndicate a new site, you should use the main page
	under **Syndication**. To change global settings for FeedWordPress,
	you should use **Syndication --> Options**.

*	FILE STRUCTURE REORGANIZATION: Due to a combination of changing styles
	for FeedWordPress plugins and lingering bugs in the FeedWordPress admin
	menu code, the code for FeedWordPress is now contained in two different
	PHP files, which should be installed together in a subdirectory of your
	plugins directory named `feedwordpress`. (See README.text for
	installation and upgrade instructions relating to the change.)

*	MULTIPLE CATEGORIES SETTING: Some feeds use non-standard methods to
	indicate multiple categories within a single category element. (The most
	popular site to do this is del.icio.us, which separates tags with a
	space.) FeedWordPress now allows you to set an optional setting, for any
	feed which does this, indicating the character or characters used to
	divide multiple	categories, using a Perl-compatible regular expression.
	(In the case of del.icio.us feeds, FeedWordPress will automatically use
	\s for the pattern without your having to do any further configuration.)
	To turn this setting on, simply use the "Edit" link for the feed that
	you want to turn it on for.

*	REGULAR EXPRESSION BUG FIXED: Eliminated a minor bug in the regular
	expressions for e-mail addresses (used in parsing RSS `author`
	elements), which could produce unsightly error messages for some users
	parsing RSS 2.0 feeds.

*	DATE / UPDATE BUG FIXED: A bug in date handling was eliminated that may
	have caused problems if any of (1) WordPress, or (2) PHP, or (3) your
	web server, or (4) your MySQL server, has been set to use a different
	time zone from the one that any of the others is set to use. If
	FeedWordPress has not been properly updating updated posts, or has been
	updating posts when there shouldn't be any changes for the update, this
	release may solve that problem.

*	GOOGLE READER BUGS FIXED: A couple of bugs that made it difficult for
	FeedWordPress to interact with Google Reader public feeds have been
	fixed. Firstly, if you encountered an error message reading "There was a
	problem adding the newsfeed. [SQL: ]" when you tried to add the feed,
	the cause of this error has been fixed. Secondly, if you succeeded in
	getting FeedWordPress to check a Google Reader feed, only to find that
	the title of posts had junk squashed on to the end of them, that bug
	has been fixed too. To fix this bug, you must install the newest version
	of the optional MagpieRSS upgrade.

*	FILTER PARAMETERS: Due to an old, old bug in WordPress 1.5.0 (which was
	what was available back when I first wrote the filter interface),
	FeedWordPress has traditionally only passed one parameter to
	syndicated_item and syndicated_post filters functions -- an array
	containing either the Magpie representation of a syndicated item from
	the feed, or the database representation of a post about to be inserted
	into the WordPress database. If you needed information about the feed
	that the item came from, this was accessible only through a pair of
	global variables, $fwp_channel and $fwp_feedmeta.

	Since it's been a pretty long time since WordPress 1.5.0 was in
	widespread usage, I have gone ahead and added an optional second
	parameter to the invocation of the syndicated_item and syndicated_post
	filters. If you have written a filter for FeedWordPress that uses either
	of these hooks, you can now register that filter to accept 2 parameters.
	If you do so, the second parameter will be a SyndicatedPost object,
	which, among other things, allows you to access information about the
	feed from which an item is syndicated using the $post->feed and the
	$post->feedmeta elements (where $post is the name of the second
	parameter).
	
	NOTE THAT THE OLD GLOBAL VARIABLES ARE STILL AVAILABLE, for the time
	being at least, so existing filters will not break with the upgrade.
	They should be considered deprecated, however, and may be eliminated in
	the future.

*	FILTER CHANGE / BUGFIX: the array that is passed as the first argument
	syndicated_post filters no longer is no longer backslash-escaped for
	MySQL when filters are called. This was originally a bug, or an
	oversight; the contents of the array should only be escaped for the
	database *after* they have gone through all filters. IF YOU HAVE WRITTEN
	ANY syndicated_post FILTERS THAT PRESUME THE OLD BEHAVIOR OF PASSING IN
	STRINGS THAT ARE ALREADY BACKSLASH-ESCAPED, UPDATE YOUR FILTERS
	ACCORDINGLY.

*	OTHER MINOR BUGFIXES AND INTERNAL CHANGES: The internal architecture of
	FeedWordPress has been significantly changed to make the code more
	modular and clean; hopefully this should help reduce the number of
	compatibility updates that are needed, and make them easier and quicker
	when they are needed.

= 0.981 =

Version 0.981 is a narrowly targeted bugfix and compatibility release, whose
main purpose is to resolve a major outstanding problem: the incompatibility
between version 0.98 of WordPress and the recently released WordPress 2.1.

*	WORDPRESS 2.1 COMPATIBILITY: FeedWordPress is now compatible with
	WordPress 2.1, as well as retaining its existing support for WordPress
	2.0 and 1.5. Incompatibilities that resulted in database warnings, fatal
	errors, and which prevented FeedWordPress from syndicating new posts,
	have been eliminated.

*	RSS-FUNCTIONS.PHP RENAMED TO RSS.PHP: if you use the upgraded MagpieRSS
	replacement that's included with FeedWordPress, be sure to note that
	there are now *two* files to upload from the `OPTIONAL/wp-includes`
	subdirectory in order to carry out the upgrade: rss-functions.php and
	rss.php. **It is necessary to upload both files**, due to a change in
	the file naming scheme in WordPress 2.1, and it is necessary to do so
	whether you are using WordPress 2.1 or not. If you only upload the
	`rss-functions.php` file as in previous installations you will not have
	a working copy of MagpieRSS; the rss.php file contains the actual code.

*	DATE BUG AFFECTING SOME PHP INSTALLATIONS RESOLVED: due to a subtle bug
	in parse_w3cdtf(), some installations of PHP encountered problems with
	FeedWordPress's attempt to date posts, which would cause some new posts
	on Atom feeds to be dated as if they had apppeared in 1969 or 1970
	(thus, effectively, never appearing on front page at all). This bug in
	the date handling should now be fixed.

*	PHP <?=...?> SHORT FORM ELIMINATED: some installations of PHP do not
	allow the <?=...?> short form for printing PHP values, which was used
	extensively in the FeedWordPress interface code. Since this could cause
	fatal errors for users with the wrong installation of PHP, the short
	form has been replaced with full PHP echo statements, and is no longer
	used in FeedWordPress.

*	BETTER USER INTERFACE INTEGRATION WITH WORDPRESS 2.x: Some minor changes
	have been made to help the FeedWordPress interface pages blend in better
	with the user interface when running under WordPress 2.x.

* 	GLOBAL CATEGORIES BUG RESOLVED: a bug that prevented some users from
	setting one or more categories to apply to syndicated posts from all
	feeds (using the checkbox interface under Options --> Syndication) has
	been resolved.

= 0.98 =

*	WORDPRESS 2.0 COMPATIBILITY: This is a narrowly-targeted release to
	solve a major outstanding problem. FeedWordPress is now compatible with
	both WordPress 1.5 and WordPress 2.0. Incompatibilities that caused
	fatal SQL errors, and a more subtle bug with off-kilter counts of posts
	under a given category, have been resolved. FeedWordPress tests for
	database schema using the global $wp_db_version variable (if null, then
	we presume that we're dealing with WordPress 1.5).

	NOTE: I have **not** fully tested FeedWordPress with WordPress 2.0.
	Further testing may reveal more bugs. However, you should now be able
	to get at least basic FeedWordPress functionality up and running.

*	AUTHOR MATCHING: FeedWordPress tests several fields to see if it can
	identify the author of the post as a user already in the WordPress user
	database. In previous versions, it tested the user login, the nickname,
	and tested for "aliases" listed in the Profile (see documentation). FWP
	now also matches authors on the basis of e-mail address (*if* an e-mail
	address is present). This is particularly helpful for formats such as
	RSS 2.0, in which authors are primarily identified by e-mail addresses.

= 0.97 =

*	INSTALLATION PROCEDURE: Some of the changes between 0.96 and 0.97
	require upgrades to the meta-data stored by FeedWordPress to work
	properly. Thus, if you are upgrading from 0.96 or earlier to 0.97, most
	FeedWordPress operations (including updates and template functions)
	WILL BE DISABLED until you run the upgrade procedure. Fortunately,
	running the upgrade procedure is easy: just go to either Options -->
	Syndication or Links --> Syndicated in the WordPress Dashboard and press
	the button.

*	FEED FORMAT SUPPORT: Support has been added for the Atom 1.0 IETF
	standard. Several other elements are also newly supported
	(dcterms:created, dcterms:issued, dcterms:modified, dc:identifier,
	proper support for the RSS 2.0 guid element, the RSS 2.0 author element,
	the use of Atom author or Dublin Core dc:creator constructs at the feed
	level to identify the author of individual items, etc.)

	N.B.: full support of several Atom 1.0 features, such as categories
	and enclosures, requires you to install the optional rss-functions.php
	upgrade in your wp-includes directory.

*	BUG FIX: Running `update-feeds.php` from command line or crontab
	returned "I don't syndicate..." errors. It turns out that WordPress
	sometimes tramples on the internal PHP superglobals that I depended on
	to determine whether or not the script was being invoked from the
	command line. This has been fixed (the variables are now checked
	*before* WordPress can trample them). Note that `update-feeds.php` has
	been thoroughly overhauled anyway; see below for details.

*	BUG FIX: Duplicate categories or author names. Fixed two bugs that could
	create duplicate author and/or category names when the name contained
	either (a) certain international characters (causing a mismatch between
	MySQL and PHP's handling of lowercasing text), or (b) characters that
	have a special meaning in regular expressions (causing MySQL errors when
	looking for the author or category due to regexp syntax errors). These
	should now be fixed thanks to careful escaping of names that go into
	regular expressions and careful matching of lowercasing functions
	(comparing results from PHP only to other results from PHP, and results
	from MySQL only to other results from MySQL).

*	BUG FIX: Items dated December 31, 1969 should appear less often. The
	function for parsing W3C date-time format dates that ships with
	MagpieRSS can only correctly parse fully-specified dates with a
	fully-specified time, but valid W3C date-time format dates may omit the
	time, the day of the month, or even the month. Some feeds in the wild
	date their items with coarse-grained dates, so the optional
	`rss-functions.php` upgrade now	includes a more flexible parse_w3cdtf()
	function that will work with both coarse-grained and fully-specified
	dates. (If parts of the date or the time are omitted, they are filled in
	with values based on the current time, so '2005-09-10' will be dated to
	the current time on that day; '2004' will be dated to this day and time
	one year ago.

	N.B.: This fix is only available in the optional `rss-functions.php`
	upgrade.

*	BUG FIX: Evil use of HTTP GET has been undone. The WordPress interface
	is riddled with	inappropriate (non-idempotent) uses of HTTP GET queries
	(ordinary links	that make the server do something with significant
	side-effects, such as deleting a post or a link from the database).
	FeedWordPress did some of this too, especially in places where it aped
	the WordPress interface	(e.g. the "Delete" links in Links -->
	Syndicated). That's bad business, though. I've changed the interface so
	that all the examples of improper side-effects that I can find now
	require an HTTP POST to take effect. I think I got pretty much
	everything; if there's anything that I missed, let me know.

	Further reading: [Sam Ruby 2005-05-06: This Stuff Matters](http://www.intertwingly.net/blog/2005/05/06/This-Stuff-Matters)

*	BUG FIX: Categories applied by `cats` setting should no longer prevent
	category-based filtering from working. In FeedWordPress, you can (1)
	apply certain categories to all syndicated posts, or all posts from
	a particular feed; and (2) filter out all posts that don't match one
	of the categories that are already in the WordPress database (allowing
	for simple category-based filtering; just load up WordPress with the
	categories you want to accept, and then tell FeedWordPress not to create
	new ones). However, the way that (1) and (2) were implemented meant that
	you couldn't effectively use them together; once you applied a known
	category to all syndicated posts from a particular feed, it meant that
	they'd have at least one familiar category (the category or categories
	you were applying), and that would get all posts past the filter no
	matter what categories they were originally from.
	
	Well, no longer. You can still apply categories to all syndicated posts
	(using either Syndication --> Options, or the feed-level settings under
	Links --> Syndicated). But these categories are not applied to the post
	until *after* it has already passed by the "familiar categories" filter.
	So now, if you want, you can do category filtering and *then* apply as
	many categories as you please to all and only posts that pass the filter.

*	BUG FIX: Other minor typos and HTML gaffes were fixed along the way.

*	PERFORMANCE: get_feed_meta() no longer hits the database for information
	on every call; it now caches link data in memory, so FeedWordPress only
	goes to the database once for each syndicated link. This may
	substantially improve performance if your database server resources
	are tight and your templates make a lot of use of custom settings from
	get_feed_meta().

*	API CHANGE: Link ID numbers, rather than RSS URIs, are now used to
	identify the feed from which a post is syndicated when you use template
	functions such as get_feed_meta(). The practical upshot of this is you
	can switch feeds, or change the feed address for a particular syndicated
	site, without breaking your templates for all the posts that were
	syndicated from the earlier URI.

*	API CHANGE: if you have plugins or templates that make use of the
	get_feed_meta() function or the $fwp_feedmeta global, note that the
	data formerly located under the `uri` and `name` fields is now located
	under the `link/uri` field and the `link/name` field, respectively. Note
	also that you can access the link ID number for any given feed under the
	global $fwp_feedmeta['link/id'] (in plugins) or 
	get_feed_meta('link/id') (in a template in post contexts).
	
*	FEATURE: the settings for individual feeds can now be edited using a
        humane interface (where formerly you had to tweak key-value pairs in the
	Link Notes section). To edit settings for a feed, pick the feed that you
	want under Links --> Syndicated and click the Edit link.

*	FEATURE: The "Unsubscribe" button (formerly "Delete") in Links -->
	Syndicated now offers three options for unsubscribing from a feed: (1)
	turning off the subscription without deleting the feed data or affecting
	posts that were syndicated from the feed (this works by setting the Link
	for the feed as "invisible"); (2) deleting the feed data and all of the
	posts that were syndicated from the feed; or (3) deleting the feed data
	and *keeping* the posts that were syndicated from the feed
	setting the Link to "Invisible" (meaning that it will not be displayed
	in lists of the site links on the front page, and it won't be checked
	for updates; (2) deleting the Link and all of the posts that were
	syndicated from its feed; or (3) deleting the feed data but keeping the
	posts that were syndicated (which will henceforward be treated as if
	they were local rather than syndicated posts). (Note that (1) is usually
	the best option for aggregator sites, unless you want to clean up the
	results of an error or a test.)

*	FEATURE / BUG FIX: If you have been receiving mysterious "I don't
	syndicate...", or "(local) HTTP status code was not 200", or "(local)
	transport error - could not open socket", or "parse error - not well
	formed" errors, then this update may solve your problems, and if it does
	*not* solve them, it will at least make the reasons for the problems
	easier to understand. That's because I've overhauled the way that
	FeedWordPress goes about updating feeds.

	If you use the command-line PHP scripting method to run scheduled
	updates, then not much should change for you, except for fewer
	mysterious errors. If you have done updates by sending periodic HTTP
	requests to <http://your-blog.com/path/wp-content/update-feeds.php>,
	then the details have changed somewhat; mostly in such a way as to make
	things easier on you. See the README file or online documentation on
	Staying Current for the details.

*	FEATURE: FeedWordPress now features a more sophisticated system for
	timed updates. Instead of polling *every* subscribed feed for updates
	*each* time `update-feeds.php` is run, FeedWordPress now keeps track of
	the last time it polled each feed, and only polls them again after a
	certain period of time has passed. The amount of time is normally set
	randomly for each feed, in a period between 30 minutes and 2 hours (so
	as to stagger updates over time rather than polling all of the feeds at once. However, the length of time between updates can also be set
	directly by the feed, which brings us to ...
	
*	FEATURE: FeedWordPress now respects the settings in the `ttl` and
	Syndication Module RSS elements. Feeds with these elements set will not
	be polled any more frequently than they indicate with these feeds unless
	the user manually forces FeedWordPress to poll the feed (see Links -->
	Syndicated --> Edit settings).

= 0.96 =

*	FEATURE: support has been added for enclosures in RSS 2.0 and Atom
	0.6+ newsfeeds. WordPress already supports adding enclosures to an
	item; FeedWordPress merely gets the information on the enclosure
	from the feed it is syndicating and plugs that information directly
	into the WordPress database so that (among other things) that post
	will have its enclosure listed in your blog's RSS 2 newsfeed.

	Note that enclosure support requires using the optional MagpieRSS
	upgrade (i.e., replacing your `wp-includes/rss-functions.php` with
	`OPTIONAL/wp-includes/rss-functions.php` from the FWP archive)

*	FEATURE: for completeness's sake, there is now a feed setting,
	`hardcode url`, that allows you to set the URI for the front page
	of a contributor's website manually (that is, prevent it from being
	automatically updated from the feed channel link on each update). To
	set the URI manually, put a line like this in the Link Notes section
	of a feed:
	
		hardcode url: yes
	
	You can also instruct FeedWordPress to use hardcoded URIs by default
	on all feeds using Options --> Syndication

*	FEATURE: by default, when FeedWordPress finds new syndicated posts,
	it (1) publishes them immediately, (2) turns comments off, and (3)
	turns trackback / pingback pings off. You can now alter all three
	default behaviors (e.g., to allow pings on syndicated posts, or to
	send newly-syndicated posts to the draft pile for moderation) using
	Options --> Syndication


= From 0.91 to 0.95 =

*	BUG FIX: Fixed an obscure bug in the handling of categories:
	categories with trailing whitespace could cause categories with
	duplicate names to be created. This no longer happens. While I was
	at it I tightened up the operation of
	FeedWordPress::lookup_categories() a bit in general.

*	FEATURE DEPRECATED: the feed setting `hardcode categories` is now
	deprecated in favor of `unknown categories` (see below), which
	allows you to strip off any syndication categories not already in
	your database using `unknown categories: default` or `unknown
	categories: filter`. If you have `hardcode categories: yes` set on a
	feed, this will be treated as `unknown categories: default` (i.e.,
	no new categories will be added, but if a post doesn't match any of
	the categories it will be added in the default category--usually
	"Uncategorized" or "General").

*	FEATURE: You can now set global defaults as to whether or not
	FeedWordPress will update the Link Name and Link Description
	settings for feeds automatically from the feed title and feed
	tagline. (By default, it does, as it has in past versions.) Whether
	this behavior is turned on or off, you can still override the
	default behavior using feed settings of `hardcode name: yes`,
	`hardcode name: no`, `hardcode description: yes`, or `hardcode
	description: no`.

*	FEATURE: Users can now provide one or several "aliases" for an
	author, just as they can for a category. For example, to make
	FeedWordPress treat posts by "Joseph Cardinal Ratzinger" and "Pope
	Benedict XVI" as by the same author, edit the user profile for Pope
	Benedict XVI and add a line like this to the "User profile" field:
	
		a.k.a.: Joseph Cardinal Ratzinger
	
	You can add several aliases, each on a line by itself. You can also
	add any other text you like to the Profile without interfering with
	the aliases.
	
*	FEATURE: Users can now choose how to handle syndicated posts that
	are in unfamiliar categories or by unfamiliar authors (i.e.,
	categories or authors whose names are not yet in the WordPress
	database). By default, FeedWordPress will (as before) create a new
	category (or new author) and use it for the current post and any
	future posts. This behavior can be changed, either for all feeds or
	for one or another particular feed.
	
	There are now three different options for an unfamiliar author: (1)
	FeedWordPress can create a new author account and attribute the
	syndicated post to the new account; (2) FeedWordPress can attribute
	the post to an author if the author's name is familiar, and to a
	default author (currently, this means the Site Administrator
	account) if it is not; (3) FeedWordPress can drop posts by
	unfamiliar authors and syndicate only posts by authors who are
	already in the database.
	
	There are, similarly, two different options for an unfamiliar
	category: (1) FeedWordPress can create new categories and place the
	syndicated post in them; (2) FeedWordPress can drop the unfamiliar
	categories and place syndicated posts only in categories that it is
	already familiar with. In addition, FeedWordPress 0.95 lets you
	choose whether posts that are in *no* familiar categories should be
	syndicated (and placed in the default category for the blog) or
	simply dropped.
	
	You can set the default behavior for both authors and categories
	using the settings in Options --> Syndication. You can also set
	different behavior for specific feeds by adding the `unfamiliar
	author` and / or `unfamiliar categories` settings to the Link Notes
	section of a feed:
	
		unfamiliar author: (create|default|filter)
		unfamiliar categories: (create|default|filter)
	
	A setting of `unfamiliar author: create` will make FeedWordPress
	create new authors to match unfamiliar author names *for this feed
	alone*. A setting of `unfamiliar author: default` will make it
	assign posts from unfamiliar authors to the default user account. A
	setting of `unfamiliar author: filter` will cause all posts (from
	this feed alone) to be dropped unless they are by an author already
	listed in the database. Similiarly, `unfamiliar categories: create`
	will make FeedWordPress create new categories to match unfamiliar
	category names *for this feed alone*; `unfamiliar categories:
	default` will cause it to drop any unfamiliar category names; and
	`unfamiliar categories: filter` will cause it to *both* drop any
	unfamiliar category names *and* to only syndicate posts that are
	placed in one or more familiar categories.
	
	These two new features allow users to do some coarse-grained
	filtering without having to write a PHP filter. Specifically, they
	offer an easy way for you to filter feeds by category or by author.
	Suppose, for example, that you only wanted to syndicate posts that
	your contributors place in the "Llamas" category. You could do so by
	setting up your installation of WordPress so that the only category
	in the database is "Llamas," and then use Options --> Syndication to
	set "Unfamiliar categories" to "don't create new categories and
	don't syndicate posts unless they match at least one familiar
	category". Now, when you update, only posts in the "Llamas" category
	will be syndicated by FeedWordPress.
	
	Similarly, if you wanted to filter one particular feed so that only
	posts by (for example) the author "Earl J. Llama" were syndicated to
	your site, you could do so by creating a user account for Earl J.
	Llama, then adding the following line to the settings for the feed
	in Link Notes:
	
		unfamiliar author: filter
	
	This will cause any posts from this feed that are not authored by
	Earl J. Llama to be discarded, and only the posts by Earl J. Llama
	will be syndicated. (If the setting is used on one specific feed, it
	will not affect how posts from other feeds are syndicated.)
	
== License ==

The FeedWordPress plugin is copyright © 2005-2010 by Charles Johnson. It uses
code derived or translated from:

-	[wp-rss-aggregate.php][] by [Kellan Elliot-McCrea](kellan@protest.net)
-	[MagpieRSS][] by [Kellan Elliot-McCrea](kellan@protest.net)
-	[Ultra-Liberal Feed Finder][] by [Mark Pilgrim](mark@diveintomark.org)
-	[WordPress Blog Tool and Publishing Platform](http://wordpress.org/)

according to the terms of the [GNU General Public License][].

This program is free software; you can redistribute it and/or modify it under
the terms of the [GNU General Public License][] as published by the Free
Software Foundation; either version 2 of the License, or (at your option) any
later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

  [wp-rss-aggregate.php]: http://laughingmeme.org/archives/002203.html
  [MagpieRSS]: http://magpierss.sourceforge.net/
  [HTTP Navigator 2]: http://www.keyvan.net/2004/11/16/http-navigator/
  [Ultra-Liberal Feed Finder]: http://diveintomark.org/projects/feed_finder/
  [GNU General Public License]: http://www.gnu.org/copyleft/gpl.html

