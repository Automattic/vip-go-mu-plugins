=== Plugin Name ===
Contributors: automattic, nprasath002, batmoo
Tags: XMLRPC, WordPress.com REST
Requires at least: 3.4
Tested up to: 3.4
Stable tag: 2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==
Push syndication plugin helps users to manage posts across multiple sites. It’s very useful
when managing posts in different platforms like a WordPress.com blog or a standalone
WordPress install. It scales very well and with a single click you can push a post to more
than 100 sites of different platform. Currently WordPress.com blogs and standalone
WordPress blogs are supported and we have plans to extend it to other platforms as well.


The plugin have a settings page along with an API generator tool that can be used to generate
API tokens needed to push content in a WordPress.com blog. In the settings page you can select
the post types you want to push and whether to delete the posts pushed when the master post is deleted.

To push posts in a WordPress.com blog first you need to create an application in the developer
blog with redirect URI as listed in the settings page. Fill the client ID and client secret as
displayed in the app page. Clicking the authorize button will direct you to the authorization
page of WordPress.com. Select the blog you want to push from the dropdown list and click
authorize where you will be redirected back to your settings page which displays the API token,
 log ID and Blog URL. Use this information when registering a WordPress.com site.

Sites must be registered and grouped into sitegroups in order to push content.
In the post edit screen a Syndication metabox will appear with the all the sitegroups defined.
Select the sitegroups you want to push content and hit the publish button to push content.

== Installation ==
As any other WordPress plugin you can enable push syndication plugin through the plugins
page in the WordPress admin area. You also need to define an encryption key which will be
used to encrypt user credentials and save to the database securely.

define('PUSH_SYNDICATION_KEY', 'this-is-a-randon-key')


== Changelog ==

= 1.0 =
* Initial release

== Frequently Asked Questions ==

== Screenshots ==
1. Push Syndication Settings Page
2. Registering an Application
3. WordPress.com Authorization Page
4. WordPress.com API credentials
5. Registering Standalone WordPress Install
6. Registering a WordPress.com Site
7. Sitegroups Metabox