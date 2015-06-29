=== Kimili Flash Embed ===
Contributors: Kimili
Tags: flash, swf, swfobject, javascript
Requires at least: 1.5
Tested up to: 2.6.1
Stable tag: 1.4.2
Donate Link: http://kimili.com/donate

Provides a WordPress friendly interface for Geoff Stearns' excellent standards compliant Flash detection and embedding JavaScript, SWFObject

== Description ==

Kimili Flash Embed is a plugin for WordPress that allows you to easily place Flash movies on your site. Built upon Geoff Stearns' [SWFObject](http://blog.deconcept.com/swfobject) javascript code, it is standards compliant, search engine friendly, highly flexible and full featured, as well as easy to use.

Kimili Flash Embed utilizes SWFObject 1.5, is fully compatible with WordPress 2.x and plays well with most other plugins.

== Installation ==

Installing the plugin is as easy as unzipping and uploading the entire kimili-flash-embed folder into your wp-content/plugins directory and activating the plugin. You can deactivate and delete any old versions of KFE you may have.

== Basic Usage ==

Once the plugin is installed and activated, you can add Flash content to your pages using a tag like this in your articles:

> `[kml_flashembed movie="filename.swf" height="150" width="300" /]`

If you’re using the WYSIWYG editor in WordPress 2.0 - 2.3 to write your posts, you should see a new button on the right end of the toolbar with a Flash player logo. Click it, and a basic KFE tag will be dropped into your post editor, ready to be populated with the SWF URL, width and height.

The only required attributes in a KFE tag are movie, height, and width. See the the following sections all available attributes and advanced usage.

== Available Attributes ==

All of the available attributes for the KFE tag should be lowercase and double quoted. They are:

MOVIE (required)  
The path and file name of the flash movie you want to display.

HEIGHT (required)  
The height of the flash movie. You can specify in pixels using just a number or percentage.

WIDTH (required)  
The widthof the flash movie. You can specify in pixels using just a number or percentage.

ALLOWFULLSCREEN  
(true|false) Grants access for a Flash movie to enter full screen mode when using Flash Player 9.  Defaults to false.

ALLOWSCRIPTACCESS  
(always|never) Controls the ability to perform outbound scripting through use of FSCommand actions or getURL actions from within your SWF. If unspecified, the Flash Player defaults to "always".

ALTTEXT (deprecated)  
The text or html you want to display if the required flash player is not found. This attribute is ignored if target is used.

BGCOLOR  
(#RRGGBB, hexadecimal RGB value) - Specifies the background color of the Flash movie.

DETECTKEY  
This is the url variable name the script will look for when bypassing the detection. Defaults to 'detectflash'. For example: To bypass the Flash detection and simply write the Flash movie to the page, you could add ?detectflash=false to the url of the document containing the Flash movie.

FID  
Use this attribute to give your movie a unique id on the page for scripting purposes.

FVARS  
Pass variables (name/value pairs) into your movie with this attribute. You can pass in as few or as many variables as you want, separating name/value pairs with a semicolon. Syntax is as follows:

> `fvars=" name = value ; name = value "`
	
In addition to hard coded values, you can also pass in arbitrary Javascript or PHP code, like such:

> Javascript - `href = ${document.location.href;}`  
> PHP - `date = ?{date('F j, Y');}`
	
These can be strung together in any order inside the fvars attribute:

> `fvars=" href = ${document.location.href;} ; date = ?{date('F j, Y');} ; name = Johnny Bravo "`
	
FVERSION  
You can specify what version of the flash player is required to play your movie. Defaults to 6.

LOOP  
(true, false) - Specifies whether the movie repeats indefinitely or stops when it reaches the last frame. The default value is true if this attribute is omitted.

MENU  
(true) displays the full menu, allowing the user a variety of options to enhance or control playback.  
(false) displays a menu that contains only the Settings option and the About Flash option.

NOSCRIPT  
Text or html content you would like to display to users browsing on a non-javascript browser or with javascript disabled.

PLAY  
(true, false) - Specifies whether the movie begins playing immediately on loading in the browser. The default is true.

QUALITY  
(low, high, autolow, autohigh, best ) - Specifies the playback quality of the Flash movie.

REDIRECTURL  
If you wish to redirect users who don't have the correct Flash Player version, use this parameter and they will be redirected. Ignored if using useexpressinstall.

SCALE  
(showall, noborder, exactfit) - Dictates how the movie fills in the specified target area.

TARGET  
This is the ID of an element on your page that you want your flash movie to display within.

TARGETCLASS  
This is the class name of the element on your page that you want your flash movie to display within. Defaults to "flashmovie".

USEEXPRESSINSTALL  
(true) Use this if you want to invoke the Flash Player Express Install functionality. This gives users the option to easily update their Flash Player if it doesn't meet the required version without leaving your site. Also see XIREDIRECTURL

WMODE  
(window, opaque, transparent) - Sets the Window Mode property of the Flash movie for transparency, layering, and positioning in the browser.

XIREDIRECTURL  
When using the useexpressinstall functionality, use this attribute to specify an alternate URL to redirect users who complete the Express Install upgrade. **Note** - The URL you define here must be a complete URL, including http://www.yourdomain.com/

You can find out more about Flash player attributes at [Adobe's Knowledge Base](http://kb.adobe.com/selfservice/viewContent.do?externalId=tn_12701&sliceId=1)

== Using Flash Player Express Install ==

If you want to give visitors to your site the option to upgrade their Flash Player to the latest version as quickly and seamlessly as possible, you can use the Flash Player’s Express Install functionality.

= General Notes =

Your SWF files need to be a minimum of 214px wide by 137px high so the entire upgrade dialog can be seen by the user if the Express Install is triggered. Furthermore, if your Express-Install-enabled SWF is not at least that size, the Express Install function will automatically fail.

It may also be a good idea to only place one SWF with Express Install functionality on each page. This way users won’t be greeted with multiple upgrade dialog boxes and be forced to choose one.  Onto the specifics:

= Specifics =

Define the minimum flash player version required by your .SWF using the fversion attribute:

> `fversion="9"`

Add the useexpressinstall attribute to your [kml_flashembed /] tag, like this:

> `useexpressinstall="true"`
	
OPTIONAL - You can also add the xiredirecturl attribute to redirect users who successfully upgrade their flash player:

> `xiredirecturl="http://www.yoursite.com/bleeding/edge/flash/"`
	
In the end, your KFE tag should look something like this:

> `[kml_flashembed movie="filename.swf" height="300" width="300" fversion="9" useexpressinstall="true"  /]`
	
That is all you need in order to invoke the Express Install functionality.  In the case of the above KFE tag, if a user arrives at your site with either a Flash Player 6, 7, or 8 installed, they will be alerted that they need a more recent version of the Flash Player and be given the option to upgrade it without leaving your site.

== Frequently Asked Questions ==

So I can maintain them in one place, please see the Kimili Flash Embed FAQs at the [Kimili Flash Embed Home](http://kimili.com/plugins/kml_flashembed#faqs)

== Version History ==

> **Note:** Because this plugin has been around for a while and numerous older versions exist, yet version 1.4 is the first version to actually be included in the WordPress Plugin Repository, any older versions are NOT available here.  If you'd like to download an older version, you can do so at the [Kimili Flash Embed for WordPress Home Page](http://kimili.com/plugins/kml_flashembed/wp).

= Version 1.4.3 =

* Fixed a bug with how fvars are output in RSS feeds.

= Version 1.4.2 =

* Fixed a problem with URL file-access on some server configurations.

= Version 1.4.1 =

* Fixed an incompatibility with other plugins that were using the buttonsnap.php library
* Updated Toolbar buttons to work with TinyMCE 3, used in WordPress 3

= Version 1.4 =

* Added "allowFullScreen" attribute for full support of Flash Player 9
* Fixed a bug when specifying percentage heights and widths.

= Version 1.3.1 =

* Fixed movie ID (fid attribute) handling.
           
= Version 1.3 =

* Updated SWFObject Javascript to latest codebase (SWFObject 1.5)
* Simplified Express Install handling to reflect changes in SWFObject 1.5
* Cleaned up code, declaring undeclared variables.

= Version 1.2 =

* Improved compatibility with other plugins.
* Removed the need to turn off GZIP compression.
* Added Flash movies to RSS feeds.
* Added a "targetclass" attribute to define a class name for the element which the SWF will be rendered within.
* Fixed a problem with invalid HTML rendering.
* Fixed a problem when passing in URLs with query strings in the FVARS attribute.
* Added Rich Text Editor Toolbar button to quickly insert KFE tags in a post.
* Updated SWFObject Javascript to latest codebase (SWFObject 1.4.4)

= Version 1.1 =

* Much improved compatibility with other WordPress plugins and themes. (yay!)
* Simplified embedding multiple instances of the same SWF.
* Removed FlashObject code from RSS feeds, allowing for feed validation.
* Updated JS to latest codebase. (FlashObject 1.3d)

= Version 1.0 =

* Updated JS to latest codebase. (FlashObject 1.3, released 1/17/06)
* Modified JS to support old browsers.
* Added ability to pass arbitrary Javascript and PHP values to SWF. 
* Includes Express Install functionality.

= Version 0.3.1 =

* Fixed a bug that prevented the Javascript from displaying properly on some servers

= Version 0.3 =

* Fixed a bug that prevented the Flash movie from displaying properly on archive pages.
* Updated Flash Object Javascript to include NS4 compatibility.

= Version 0.2 =

* Eliminated the need to install and link to a separate JavaScript file
* Initialized some previously uninitialized variables, cleaning things up a bit
* Fixed a bug that prevented fvars from being passed to the flash
* Dealt with a strange WP behaivior that was keeping the code from validating (See URL above for more info)