=== Local Time ===
Contributors: Viper007Bond
Tags: timezone, localization, locale
Requires at least: 3.2
Tested up to: 3.3
Stable tag: trunk

Displays post and comment date and times in the visitor's timezone using Javascript. Heavily based on code from the P2 theme.

== Description ==

Displays post and comment date and times in the visitor's timezone using Javascript. No theme modifications are needed.

Heavily based on code from the [P2 theme](http://p2theme.com/) by [Automattic](http://automattic.com/).

**Demo**

Check out [one of my sites](http://www.finalgear.com/) to try it yourself. All of the dates and times on the site are GMT+0 but will be displayed in your timezone once the page is fully done loading.

== Installation ==

Visit Plugins &rarr; Add New in your administration area and search for the name of this plugin.

== ChangeLog ==

= Version 1.2.1 =
* Fix bug affecting the post time functions. Whoops.

= Version 1.2.0 =
* Make use of HTML `data` attributes instead of nested and hidden `<span>`s to store the additional data.
* Move the Javascript all into one `.js` file so it can be cached.
* Rewriting of the filters to reduce code duplication.

= Version 1.1.5 =
* Have the Javascript set the date and/or time's `title` value to "This date and/or time has been adjusted to match your timezone" so the user knows it's been adjusted.

= Version 1.1.4 =
* Move the `is_feed()` check later so it'll actually work.

= Version 1.1.3 =
* Missing parenthesis was causing a parse error. Whoops!

= Version 1.1.2 =
* Disable only for the new version of P2.
* Disable if the WPTouch plugin is active and displaying it's theme (this plugin breaks with it).
* Disable inside of feeds.

= Version 1.1.1 =
* If the time format is `U` (i.e. a Unix timestamp), then don't modify the output as it's most likely being used by PHP rather than being displayed.

= Version 1.1.0 =
* Add localization for comment dates and times.

= Version 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.1 =
Fixes broken post times. Whoops.

= 1.2.0 =
Leaner, meaner, and faster.