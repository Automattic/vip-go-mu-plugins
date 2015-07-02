# WP Help #
Contributors: markjaquith
Donate link: http://txfx.net/wordpress-plugins/donate
Tags: help, documentation, client sites, clients, docs
Requires at least: 3.4
Tested up to: 3.5
Stable tag: 1.2

Site operators can create detailed, hierarchical documentation for the site's authors, editors, and contributors, viewable in the WordPress admin.

## Description ##

Site operators can create detailed, hierarchical documentation for the site's authors, editors, and contributors, viewable in the WordPress admin. Powered by Custom Post Types, you get all the power of WordPress to create, edit, and arrange your documentation. Perfect for customized client sites. Never send another "here's how to use your site" e-mail again!

**NEW**: You can now pull in help documents from another WP Help install, and they will be automatically updated when the source documents change (even additions and deletions!). Perfect for WordPress multisite installs, or consultants with a large number of client installs.

## Installation ##

1. Upload the `wp-help` folder to your `/wp-content/plugins/` directory

2. Activate the "WP Help" plugin in your WordPress administration interface

3. Visit "Publishing Help" in the menu to get started (note that you can change the location and title of this menu item)

## Frequently Asked Questions ##

### Who can view the help documents? ###

Anyone who can save posts. So by default, Authors, Editors, Administrators, and Contributors

### Who can edit the help documents? ###

Anyone who can `publish_pages`. So by default, Editors and Administrators.

### How do I reorder the documents? ###

Just like you'd reorder pages. Change the `Order` setting for the page, in the `Attributes` meta box. To make something be first, give it a large negative number like `-100`.

### How do I link to another help page from a help page? ###

Use WordPress' internal linking feature. When launched from a help document, it will only search for other help documents.

### How do I change the default help document? ###

Edit the help document you want to be the default. Check the "Set as default help document" checkbox, and save. This will now be the default document.

### Why can't I edit some documents? ###

Documents that came from another WP Help install that is currently connected, cannot be edited (your changes would just be overwritten anyway). In order to edit these documents, you need to disconnect from sync permanently, or edit the at their source WP Help install.

### Will enabling sync delete my existing documents? ###

Enabling sync will delete any documents that came in via another sync source. **But it will not delete locally-created documents.**

### Can I have multiple sync sources? ###

Not right now. But what you can do, is have a WP Help install that aggregates another WP Help install and is then itself used as a source for the combination of the two sites docs, to be delivered to a third site.

### Why can't I reorder some docs using drag and drop? ###

You cannot internally sort synced documents — just move the whole "chunk" of synced documents around.. Also, if a local help document is the only document in its level, it won't get a drag handle, because there's nothing to reorder. Give it a sibling, and you'll be able to order them how you like.

## Screenshots ##

1. The Publishing Help screen, which lists and displays available help documents.

## Upgrade Notice ##
### 1.2 ###
Update for better UI and support for WordPress 3.5.

### 1.1 ###
Sexy drag and drop reordering, and order-syncing through the sync API.

### 1.0 ###
MASSIVE UPDATE. Sync help documents from another WP Help install. Rename and relocate the menu item. Dashboard widget. Easier navigation.

### 0.3 ###
Upgrade for a French translation.

### 0.2 ###
Upgrade if you want to use WP Help in one of these languages: Bulgarian, German, Spanish, Mexican Spanish, Macedonian, Dutch, Brazilian Portuguese, or Russian.

## Changelog ##
### 1.2 ###
* New or updated translations for Gujarati, Norwegian, and Brazilian Portuguese.
* CSS fixes for WordPress 3.5.
* More robust AJAX saving when save events overlap.

### 1.1 ###
* Feature: drag and drop reordering of the documents list.
* Feature: local /wp-admin/ links are rewritten when shared through the API so that they're wp-admin-relative and thus work on the destination site.
* Improvement: more consistent cap checks, so plugins can consistently customize who can do what.
* Improvement: restore settings form to previous state when "cancel" is clicked.
* Bug fix: sort order is now synced.

### 1.0 ###
* Feature: sync help documents from another WP Help install.
* Feature: rename the page title.
* Feature: rename the document list title.
* Feature: choose the location of the menu item (Dashboard submenu, or one of three top level positions).
* Feature: easier access to creation, editing, and management of documents.
* Feature: dashboard widget.
* Improvement: better UI for selecting the default document.

### 0.3 ###
* Translation for: French. Squashes a PHP Notice. Add an action hook so people can add their own styles.

### 0.2 ###
* Translations for: Bulgarian, German, Spanish, Mexican Spanish, Macedonian, Dutch, Brazilian Portuguese, and Russian.

### 0.1 ###
* Initial version

## Roadmap ##

I'm not saying these things are definitely coming, but they're the sort of things that are on my radar, in case you were wondering:

* Multiple sync sources
* Multiple sync groups (segment top level documents into "categories" and get a unique sync URL for each)
