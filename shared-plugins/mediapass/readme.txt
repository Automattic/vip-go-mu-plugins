=== Plugin Name ===

Contributors: joehoward, matthewsacks, MediaPass&#8482;
Plugin Name: MediaPass Subscription Plugin
Plugin URI: http://mediapass.com/wordpress    
Tags: billing, content monetization, earn money, media pass, mediapass, member, membership, monetize, overlay, payments, paywall, premium content, registration, subscribe, subscriptions,
Donate link: http://mediapass.com
Requires at least: 3.0
Tested up to: 3.3.1
Stable tag: 2.1
Version: 2.1

Easily charge for articles, blogs, or videos (and make 3-10x more money) using the MediaPass&#8482; plugin for WordPress.

== Description ==

The MediaPass&#8482; plugin gives WordPress users the easiest way to sell subscriptions to articles, blogs, or videos AND MAKE 3-10x MORE MONEY.  Easily manage subscription pages, or even for specific articles or posts, with the click of a button. WYSIWYG buttons are added to your post editor to provide an easy way to insert subscriptions to any chosen content on your website.  You can also enable subscriptions by category, tag, and users.  Easily track your growing, recurring revenue online.  Merchant accounts are not required for websites using MediaPass&#8482;.  Integrate your MediaPass.com account with your WordPress website and watch your revenue grow.

== Installation ==

If you do not have a MediaPass&#8482; account, you must register one before installing the plugin. Go to (http://www.mediapass.com) to register an account. Visit the FAQ for more information. Once you have registered an account, follow these directions:

1. Download the plugin from the WordPress plugin directory or from (http://www.mediapass.com/wordpress)
2. Upload the MediaPass folder to the /wp-content/plugins/ directory
3. Activate the plugin through the 'Plugins' menu in WordPress

You can also install the plugin by the following: 

1. In your WordPress dashboard, go to the 'Plugins' menu then click 'Add New'
2. Search for 'mediapass'
3. Install and activate

== Frequently Asked Questions ==

= How do I get started? =

If you do not have a MediaPass&#8482; account, you must register one before installing the plugin. Go to (http://www.mediapass.com) to register an account. Follow the simple steps on the 'Installation' tab.

= How do I use the page overlay style? =

Highlight the text within your content window that you would like to use as a teaser. Click the MediaPass "mediapass overlay" button of your wysiwyg content window.

= How do I use the in-page option? =

Highlight the text within your content window that you would like to hide for those not signed up for access to your content. Click the MediaPass&#8482; "mediapass in-page" button of your wysiwyg content window.

= How do I use the video overlay option? =

Click the MediaPass&#8482; "mediapass video" button of your wysiwyg content window. Paste in your video embed code, replacing the text "Paste Your Video Code Here". You can also set the delay and title within the WordPress shortcode you see.  

= Where can I learn more about using MediaPass&#8482; and the plugin? =

For more information, please visit http://www.mediapass.com/wordpress

== Screenshots ==

1. Post editor - Highlight the content you wish to protect, then click on the subscription style you wish to use.
2. Post list - Simply enable subscriptions by post with one click.
3. Category list - Simply enable subscriptions for all posts under a particular category with one click.
4. Pricing configuration - Easily manage your subscription prices all in one place.

== Changelog ==

= 2.1 =
* Major bug fixes.

= 2.02 =
* Minor bug fixes.

= 2.01 =
* Minor bug fixes.

= 2.0 =
* Add more options to enable premium content.

= 1.0 =
* Add Video Overlay option.
* Imported mediapass.com account settings.

= 0.9.5 =
* Upon successful association of MediaPass account with plugin, activate the site and set the default mode to "exclude" to accomodate new defaults
* Remove unused code from menu_default
* Remove old comments regarding unused code removal
* Migrate to production API

= 0.9.4 =
* Enable account deauthorization process.

= 0.9.3 =
* Ajax now validates nonces.  Different nonce seed for each page initiating the action.
* Ajax handlers now validates capabilities.

= 0.9.2 =
* Converted all NONCE generation to MediaPass_Plugin::nonce_for($action_specific_nonce)
* Migrated is_good_post() to is_valid_http_post_action($action_specific_nonce)
* Removed unused code from menu_placement() - page has become purely instructional
* Added icon to main menu
* Updated version to 0.9.2