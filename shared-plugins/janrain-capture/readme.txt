=== Social User Registration and Profile Storage with Janrain Capture ===
Contributors: byronjanrain, kylejanrain, rebekahjanrain
Tags: capture, janrain, sso
Requires at least: 3.5
Tested up to: 3.8.3
License: APL
Stable tag: trunk

Social User Registration and Profile Storage with Janrain Capture

== Description ==
Janrain Capture provides hosted registration and authentication with social identity providers including Facebook, Google, Yahoo!, OpenID, LinkedIn, eBay, Twitter, and many others, or through traditional form field methods. Capture also includes a cloud-hosted registration and authentication system that allows site owners to maintain a central repository for user information, that can be deployed on one or more web sites.	Customers can automatically store social profile data from social login and registration, site-specific data, off-line and 3rd-party data, as well as legacy data. Capture can build a unified view on users by uniting this user information, normally distributed across disparate databases, mobile sites and apps, web properties and vendor systems such as email marketing, subscription billing or CRM to build a 360 degree view of users.

This module greatly accelerates the time required to integrate Janrain Capture into your WordPress web sites, helping to improve your registration conversion rates by allowing your customers to register and sign-in using their prefered identities.

Janrain Capture's registration and social profile database features include:

= Social login and registration =
In a study conducted by Blue research, Janrain found that 86% of users are very likely to leave a site when prompted to create a new traditional account (username and password). Janrain Capture eliminates the need for a new username and password by authenticating users with existing 3rd party and social identities like Facebook, Google, Yahoo!, LinkedIn, Twitter, and so on. Janrain also found that 42% of users prefer to use Facebook for social registration. However, with support for more than 20 identity providers, customers can meet the needs of the remain 58% of new users.

= Pre-populated forms =
88% of users admit to providing false registration info. Capture seamlessly pre-fills registration fields to streamline registration for users and provide customers with highly accurate, rich social profile data.

= Traditional account support and mapping =
Capture supports existing accounts with side-by-side social and traditional logins as well as the option for customers to offer traditional accounts to new users. Account mapping allows users to map a 3rd party identity to their legacy account resulting in a one-click return login experience for users and rich social profile data for customers.

= Personalized, one-click return experience =
Capture personalizes and simplifies the user's return experience by welcoming the user back by name and prompting them to login with their previously chosen identity provider for a one-click return login.

= Email verification and in-line form field validation =
Capture features an email verification flow to ensure that all user profiles contain an active email account. In-line form field validation further improves the quality of user profile data with field format rules and unique username availability and suggestions.

= Rich customization options =
Capture registration enables customers to match registration screens to their site's look and feel using a CSS and RESTful API calls. Customers can configure registration forms to collect any definable field for storage in the Capture database and with conditional, progressive, and multi-forms, users see highly relevant registration screens based on user origin, activity, site, and so on.

= Self-service account management =
Capture provides users and customers alike with reliable, self-service features for password reset and account deactivation or deletion. In addition, users can access a profile management form to add additional identity providers, update their user profile, or change their on-site privacy settings.

= Advanced Registration Features =
In addition, Capture supports websites with advanced features for sites and users:

*	 TOS acceptance or subscription opt-in.
*	 CAPTCHA verification.
*	 Dirty word filter.
*	 Mobile optimizations and SDKs for native applications.
*	 Event hooks for 3rd party analytics.

[About Janrain Capture](http://janrain.com/products/capture/)

For technical documentation please refer to
[Janrain Capture Documentation](http://developers.janrain.com/documentation/capture/) and [Wordpress Capture Extension Documentation](http://developers.janrain.com/extensions/wordpress-for-capture/)

== Installation ==
Install through the Administration Panel or extract plugin archive in your plugin directory.

Once installed, visit the Janrain Capture menu item in the Administration Panel to enter your Janrain Capture configuration details. At a minimum, you will need to enter an Application Domain, Client ID, and Client Secret.

To insert Capture links in posts or pages you can use the shortcode: [janrain_capture]

By default, [janrain_capture] will result in a link with the text "Sign in / Register" that will launch a modal window pointing to your Capture signin screen. You can customize the text, action, and starting width and height of the modal window by passing in additional attributes. The following is an example of a link to the legacy_register screen with a height of 800px and a width of 600px:

[janrain_capture text="Register" action="legacy_register" width="600" height="800"]

You can prevent the construction of the link and simply return the URL to the screen by adding the attribute href_only="true" to the shortcode.

To insert links in your theme templates you can use the [do_shortcode](http://codex.wordpress.org/Function_Reference/do_shortcode) WordPress function.

As of version 0.0.4, this plugin supports Engage Social Sharing through the Engage application configured for Capture. To use this feature, ensure 'Enable Social Sharing' is checked in the UI Options administration page and use the [janrain_share] shortcode. If the $post object is available, the title, description, URL, and the most recent attached image URL will automatically be determined for sharing. These variables, as well as the button text, can be overridden with the following shortcode attributes:

* title
* description
* url
* img
* text

= Example: =
[janrain_share title="Janrain Engage Share Widget" description="Instructions for how to configure the Janrain Engage Share Widget" url="wordpress.org/extend/plugins/janrain-capture/" text="Tell a friend"]

= Multisite Installation =
This plugin fully supports WordPress Multisite. To install, proceed as above, however you must Network Enable this plugin. The Janrain Capture administration menu will appear on the Network Admin dashboard.

Individual blogs can be updated with separate UI settings and a different Client ID through the Janrain Capture administration menu in each blog's dashboard. If no changes are made they will default to the network admin settings.

== Capture 2.0 Setup ==
As of version 0.2.0 of the plugin, Capture 2.0 integration is available. This is provided for customers using the latest widget version of Capture. If you are not using the Capture Widget, an upgrade path will be available in upcoming releases.

= Activating Capture 2.0: =
1.	Log in to your WordPress Administrator Dashboard.
1.	Navigate to Dashboard > Janrain Capture.
1.	A new UI Type field has been added. Change the UI Type to Capture 2.0 and click save.
1.	Capture 2.0 settings are configured in the 2.0 Settings and UI Settings tabs. Any existing Capture 1.0 settings will be preserved and hidden until you change the UI Type back.

= Configuring Capture 2.0: =
The Edit Profile page for Capture 2.0 requires creating a new WordPress page and adding only the following shortcode to it: [janrain_capture action="edit_profile"]

We set the initial link for this setting to the default WordPress sample page. So if you are trying this out on a new WordPress installation you can just edit that page, and replace the text with shortcode.

We also strongly recommend that you completely remove this page from the navigation menu. It is accessed through the "Edit My Profile" link in the Admin Bar for users who've been created through the Capture Service (that is, the default WordPress admin account will not have a Capture profile).

= Styling Capture 2.0: =
If you have access to your WordPress server's filesystem you can gain full control over how the widget is styled. (Note: This section of settings is hidden until you enable Filesystem Mode on the UI Settings tab).

The latest version of the plugin contains a folder called /janrain_capture_screens/ inside the wp-content/plugins/janrain_capture/ folder. Copy the /janrain_capture_screens/ folder to the /wp-content/plugins/ folder. This creates a local backup, and working in this folder prevents plugin updates from overwriting custom screen styles. This is also the default location of the screens folder in Filesystem Mode.
Once in place, use the built-in Wordpress plugin editor to make modifications to your Capture screens.

Note: You may also host this folder remotely on another server with PHP 5.2+ support, and change the folder under Filesystem Mode Settings.
