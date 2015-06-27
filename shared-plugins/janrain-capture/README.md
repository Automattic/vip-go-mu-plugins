Social User Registration and Profile Storage with Janrain Capture for Wordpress VIP
---------------------------------

This plugin was written for use on WordPress VIP. This plugin provides a framework
for authenticating a user and calling back user data to other client-side services
on the same domain.

### Description
Janrain Capture provides a cloud-hosted user management and authentication platform for collecting rich sets of user data and providing an interface for SSO through Janrain Federate.

[Janrain](http://www.janrain.com/)
[Product Page](http://www.janrain.com/products/capture)
[API Documentation](http://docs.janraincapture.com/)

### Installation
Install through the Administration Panel or extract plugin archive in your plugin directory.

Once installed, visit the Janrain Capture menu item in the Administration Panel to enter your Janrain Capture configuration details. At a minimum, you will need to enter an Application Domain, App ID, API Client ID, and API Client Secret.

To insert Capture links in posts or pages you can use the shortcode: [janrain_capture]

By default, [janrain_capture] will result in a link with the text "Sign in / Register" that will launch a modal window pointing to your Capture signin screen. You can customize the text, action, and starting width/height of the modal window by passing in additional attributes.

[janrain_capture text="Register"]

The Edit Profile page for Capture requires creating a new WordPress page and adding only the following shortcode to it: [janrain_capture action="edit_profile"] 

To insert links in your theme templates you can use the [do\_shortcode](http://codex.wordpress.org/Function_Reference/do_shortcode) WordPress function.

### Customization
After the janrain-capture-screens folder is placed in your theme folder, you may customize the markup and style to your liking and/or to match your theme styling.

Wordpress VIP screens will only use the following 2 CSS filenames in the stylesheets folder: styles.css and mobile-styles.css. Please do not modify the filenames or they will fail to load properly.
