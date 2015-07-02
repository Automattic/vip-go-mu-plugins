=== Post Forking ===

Contributors: benbalter, danielbachhuber, jorbin
Tags: posts, forking, version control, collaboration, forks, revisions, git, journalism, collaborative editing
Requires at least: 3.5
Tested up to:  3.6
Stable tag: 0.2.1
License: GPLv3 or Later

WordPress Post Forking allows users to fork or create an alternate version of content to foster a more collaborative approach to WordPress content curation.

== Description ==

WordPress Post Forking allows users to "fork" or create an alternate version of content to foster a more collaborative approach to WordPress content curation. This can be used, for example, to allow external users (such as visitors to your site) or internal users (such as other authors) with the ability to submit proposed revisions. It can even be used on smaller or single-author sites to enable post authors to edit published posts without their changes appearing immediately. If you're familiar with Git, or other decentralized version control systems, you're already familiar with WordPress post forking.

= How might you use it? =

* Allowing users without edit or publish post capabilities to edit and submit changes to content (similar to [GitHub’s pull request system](https://help.github.com/articles/using-pull-requests))
* Collaborative editing (by resolving two users’ conflicted saves – [Wired’s example](http://www.wired.com/wiredenterprise/2012/02/github-revisited/))
* Saving draft changes of already-published content
* Scheduling pending changes to already-published content

= How does it work? =

When a user without the `edit_post` capability attempts to edit a given post, WordPress will automatically create a "fork" or alternate version of the post which they can freely edit. The edit screen will look just like the standard post editing interface that they are used to. When they're done, they simply click "submit for review." At this point, the fork goes into the standard WordPress moderation queue (just like any time an author without the `publish_post` capability submits a post), where an editor can review, and potentially approve the changes for publishing. If the changes can be automatically merged, the original post will be updated, otherwise, the editor will be presented with the ability to resolve the conflicting changes. All this is done using WordPress's built-in custom post type, revision, and diff functionality, so it should look familiar to most WordPress users.

= Concepts =

WordPress Post Forking introduces many of Git's well-established conventions to the WordPress world, and as a result, uses a unique vocabulary to describe what it does:

* **Post** - Any WordPress post that uses the `post_content` field, including posts, pages, and custom post types
* **Fork** - Clone of a post intended for editing without disturbing the parent post
* **Branch** - Parallel versions of the same parent post, owned by the post author
* **Merge** - To push a fork's changes back into its parent post
* **Conflict** - When a post is forked if a given line is changed on the fork, and that same line is subsequently edited on the parent post prior to the merge, the post cannot be automatically merged, and the conflict is presented to the merger to resolve

= Why this plugin? =

* [GitHub for Journalism — What WordPress Post Forking could do to Editorial Workflows
](http://ben.balter.com/2012/02/28/github-for-journalism-what-wordpress-post-forking-could-do-to-editorial-workflows/)

= Project Status =

This version constitutes an initial release designed to showcase the plugin's core functionality and is intended to be improved upon with additional features and refinements as the project evolves. Please consider [contributing your time](https://github.com/benbalter/post-forking/wiki/How-to-Contribute) to help improve the project.

= More Information =

For more information, or to contribute to this documentation, please visit the [Post Forking project wiki](https://github.com/benbalter/post-forking/wiki).

[Photo courtesy [babomike](http://www.flickr.com/photos/babomike/5626846346/)]

== Installation ==

= Automatic Install =
1. Login to your WordPress site as an Administrator, or if you haven't already, complete the famous [WordPress Five Minute Install](http://codex.wordpress.org/Installing_WordPress)
2. Navigate to Plugins->Add New from the menu on the left
3. Search for Post Forking
4. Click "Install"
5. Click "Activate Now"

= Manual Install =
1. Download the plugin from the link in the top left corner
2. Unzip the file, and upload the resulting "post-forking" folder to your "/wp-content/plugins directory" as "/wp-content/plugins/post-forking"
3. Log into your WordPress install as an administrator, and navigate to the plugins screen from the left-hand menu
4. Activate Post Forking

= Building =

To compile javascript / stylesheets, simple run `script/build` from the projet's root directory.

== Frequently Asked Questions ==

Please see (and feel free to contribute to) the [Frequently Asked Questions Wiki](https://github.com/benbalter/post-forking/wiki/Frequently-Asked-Questions).

== Screenshots ==

![Create Branch as a shortcut option on the pages lists](http://cl.ly/image/1y3H0P0X2535/Screen%20Shot%202013-09-13%20at%204.21.05%20PM.png)

![Fork Metabox on the edit post screen](http://cl.ly/image/252p230e121Y/Screen%20Shot%202013-09-13%20at%204.21.21%20PM.png)

![Forks in the the admin menu](http://cl.ly/image/2N2X1W1g0r3C/Screen%20Shot%202013-09-13%20at%204.21.33%20PM.png)

![Forks overview page](http://cl.ly/image/1A3K2W0Z2L2m/Screen%20Shot%202013-09-13%20at%204.21.48%20PM.png)

![Fork Edit Screen](http://farm8.staticflickr.com/7404/9738349400_3abc106f54_b.jpg)

== Changelog ==

= 0.2 =

* WP 3.6 compatibility (props @alleyinteractive, @netaustin)
* "View fork" preview should try to use template redirect intelligently (props @goldenapples)
* Documentation fixes (props @yurivictor)
* Better diffing (props @neuaustin)
* Better tests (props @jorbin)
* Preserve line breaks on merge (#81)
* Ability to delete forks (#85)
* Added merge API hook (props @pablovallejo)
* Minor security fixes (props @joncave, @paulgibbs)
* Better i18n (props @boddhi, @bueltge)
* Added Dutch translation (props @bjornw)
* Added French translation (props @fxbenard)
* Added German translation (props @bueltge)
* Make `post_id` an optional argument on `title_filter` to prevent errors in P2 theme (props @japh)
* Added build file to minify JS and CSS (props @jorbin)
* Improved UI
* [Complete changelog](https://github.com/post-forking/post-forking/compare/0.1...master)

= 0.1 =
* Initial release

== Upgrade Notice ==

### 0.1
* Initial Release

== Frequently Asked Questions ==

Please see (and feel free to contribute to) the [Frequently Asked Questions Wiki](https://github.com/benbalter/post-forking/wiki/Frequently-Asked-Questions).

== How To Contribute ==

Post Forking is an open source project and is supported by the efforts of an entire community. We'd love for you to get involved. Whatever your level of skill or however much time you can give, your contribution is greatly appreciated.

* **Everyone** - Help expand [the project's documentation wiki](https://github.com/benbalter/post-forking/wiki/) and answer questions in the support forums to make it easier for other users to get started, or join the discussion on the [P2 (Blog)](http://postforking.wordpress.com) to help shape the project's future.
* **Users** - Download the [latest development version](https://github.com/benbalter/post-forking/tree/develop) of the plugin, and [submit bug/feature requests](https://github.com/benbalter/post-forking/issues).
* **Non-English Speakers** - [Contribute a translation](http://translations.benbalter.com/) using the GlotPress web interface - no technical knowledge required ([how to](http://translations.benbalter.com/projects/how-to-translate)).
* **Technical Folks** - [Fork the development version](https://github.com/benbalter/post-forking/tree/develop) and submit a pull request, especially for any [known issues](https://github.com/benbalter/post-forking/issues). [This tutorial](https://help.github.com/articles/set-up-git) may be helpful if you're new to git.

== Roadmap ==

= Future Features (Maybe): =

* Front end editing (just click edit, make your change, hit submit)
* Ability to fork more than just the `post_content` (e.g., taxonomies, post meta)
* [Appending parent revision history to fork](https://github.com/benbalter/post-forking/issues/15)
* Spoofing `post_type` so metaboxes, etc. appear
* [Author pages for fork contributors](https://github.com/benbalter/post-forking/issues/17)
* [Open Enhancements](https://github.com/benbalter/post-forking/issues?labels=enhancement&page=1&state=open)

== Under The Hood ==

** **Warning: geek content!** **

Forking a post creates a copy of the most recent version of the post as a "fork" custom post type. Certain fields (e.g., `post_content`, `post_title`) are copied over to the new fork. The plugin also stores the revision ID for the revision prior to when the fork was created (see [`includes/revisions.php`](https://github.com/benbalter/post-forking/blob/master/includes/revisions.php#L2) for more information as to why we store the previous revision).

The fork post type has its own capabilities, allowing a user without the ability to edit or publish on the parent post to edit a fork. Once changes have been made, assuming the user does not have the `publish_fork` capability, the user would submit the fork for review (similar to submitting a Pull Request in GitHub parlance) using the normal WordPress moderation system.

Publishing a fork (either by the fork author, if they have the capability, or my an editor) triggers the merge itself. The post content of the fork undergoes a three way merge with the base revision and current version of the parent post.

A fork can have three post statuses:

1. Draft - The fork is being edited
1. Pending - The fork has been submitted for publication
1. Published - The fork has been merged

Note: No user should have the `edit_published_fork` capability. Once published, the fork post_type simply exists to provide a record of the change and allow the author page, to theoretically list contributions by author.

== Upgrade Notice ==

### 0.1
* Initial Release

== Where To Get Support Or Report An Issue ==

*There are various resources available, depending on the type of help you're looking for:*

* For getting started and general documentation, please browse, and feel free to contribute to [the project wiki](https://github.com/benbalter/post-forking/wiki).
* For support questions ("How do I", "I can't seem to", etc.) please search and if not already answered, open a thread in the [Support Forums](http://wordpress.org/support/plugin/post-forking).
* For technical issues (e.g., to submit a bug or feature request) please search and if not already filed, [open an issue on GitHub](https://github.com/benbalter/post-forking/issues).
* For implementation, and all general questions ("Is it possible to..", "Has anyone..."), please search, and if not already answered, post a topic to the [general discussion list serve](https://groups.google.com/forum/#!forum/post-forking)
* For general discussion about the project and planning, please see the [P2](http://postforking.wordpress.com)
