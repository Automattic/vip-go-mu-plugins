=== Stipple ===
Contributors: stipple
Donate link: http://stippleit.com
Tags: stipple, stippleit, picture labeling, picture, label, labels, image, annotation, annotate, annotations, picture, label, tag, dot
Requires at least: 3.0
Tested up to: 3.2.1
Stable tag: 0.4.3

Add your Wordpress blog to the Stipple Network.

== Description ==

Stipple is the fastest way to label and share the content of photos on your site or blog. Labeling your pictures is easy, just briefly describe the people, places or things in your photos. When readers mouseover your images, they can discover information from your labels.

== Installation ==

1. If you have added Stipple previously directly to your Wordpress templates, first remove what was added to your templates. You do not need to remove your site at the Stipple website.
1. [Sign-up](https://stippleit.com/network/landing) for a Stipple Network account, or [login](https://stippleit.com/login) if you already have one, and add a site within your account for your Wordpress blog. Make sure your blog's URL is added to the site. Refer to Screenshot #2 for more information.
1. At the next step, choose *Wordpress* to open up a set of instructions that includes your site ID. You can click 'Save Settings' here to finish your site's creation.  
1. Download and install the Stipple plugin from the WordPress plugin installer, or download manually, and upload to `/wp-content/plugins/stipple`.
1. Activate the plugin through the 'Plugins' menu in WordPress. The Stipple plugin settings can be found under the 'Settings' menu item on the left side of the dashboard. Please see Screenshot #4 for reference.
1. In the plugin settings, add the site ID of the site you created in Step 2. You can find the site ID on your Network dashboard at the Stipple website, in the *Install* section. See Screenshot #3 for more information.
1. For advanced use, you can use a custom load call, though this isn't required.

== Frequently Asked Questions ==

= Does Stipple work on pictures within a Flash slideshow?  =

Unfortunately, no. Due to the nature of the Flash platform, Stipple will not work on images shown within a flash slideshow or image gallery. Stipple can still be used on other pictures on the same page, though.

= Does Stipple work on images that are inline with text? =

Stipple will not work on these images very well, at least in a way that looks good. This is due to the fact that inline images 'cheat', and let text flow around them, but also pretend to have no height or width, which makes it impossible for Stipple to work. We recommend not using inline images for those pictures you want to add Stipple Dots to.

== Screenshots ==

1. A shot of a Stipple-enabled picture.
2. A screenshot of the site creation process in the Network dashboard at
   http://stippleit.com.
3. A shot of the installation section of the dashboard, highlighting the site
   ID.
4. The Stipple plugin settings.

== Changelog ==

= 0.4.3 =
* Fix dumb error.

= 0.4.2 =
* Always use Cachefly to serve js, as it now supports SSL.

= 0.4.1 =
* Force update so wordpress.org correctly promotes the new version.

= 0.4.0 =
* Switching over to use the asynch loading method.

= 0.3.4 =
* Doc & screenshot updates. Test with Wordpress 3.2.1

= 0.3.3 =
* Doc updates. Test with Wordpress 3.1

= 0.3.2 =
* Doc updates. Test with Wordpress 3.0.4

= 0.3.1 =
* Doc updates. Test with Wordpress 3.0.2

= 0.3 =
* Be more pedantic about how the javascript is included into the page.
* Add option to include the js, but not call STIPPLE.load.

= 0.2.1 =
* readme tweaks

= 0.2 =
* Config UI tweaks and cleanups
* README tweaks.

= 0.1 =
* Initial release.

== Upgrade Notice ==

= 0.2 =
* Initial stable release.

== License ==

Copyright 2010-2011  Stipple  (email : stippletech@stippleit.com)

For support, please start a conversation at Get Satisfaction:
http://getsatisfaction.com/stipple or email stippletech@stippleit.com

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA


