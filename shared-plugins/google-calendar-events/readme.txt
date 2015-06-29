=== Google Calendar Events ===
Contributors: rosshanney
Donate link: http://www.rhanney.co.uk/plugins/google-calendar-events/#donate
Tags: google, google calendar, calendar, event, events, ajax, widget
Requires at least: 3.0
Tested up to: 3.3
Stable tag: 0.7.2

Parses Google Calendar feeds and displays the events as a calendar grid or list on a page, post or widget.

== Description ==

Parses Google Calendar feeds and displays the events as a calendar grid or list on a page, post or widget.

= Features =

* Parses Google Calendar feeds to extract events
* Displays events as a list or within a calendar grid
* Events from multiple Google Calendar feeds can be shown in a single list / grid
* Lists and grids can be displayed in posts, pages or within a widget
* Options to change the number of events retrieved, date / time format, cache duration etc
* Complete customisation of the event information displayed
* Calendar grids can have the ability to change the month displayed

Please visit the [plugin homepage](http://www.rhanney.co.uk/plugins/google-calendar-events) for how to get started and other help.

There is also a [demonstration page](http://www.rhanney.co.uk/plugins/google-calendar-events/gce-demo) showing the plugin in action.

== Installation ==

Use the automatic installer from within the WordPress admin, or:

1. Download the `.zip` file by clicking on the Download button on the right
1. Unzip the file
1. Upload the `google-calendar-events` directory to your `plugins` directory
1. Go to the Plugins page from within the WordPress administration
1. Click Activate for Google Calendar Events

After activation a new Google Calendar Events options menu will appear under Settings.

You can now start adding feeds. Visit the [plugin homepage](http://www.rhanney.co.uk/plugins/google-calendar-events) for a more in-depth guide on getting started.

== Screenshots ==

1. The main plugin admin screen.
1. The add feed admin screen.
1. A page showing a full page calendar grid and various widgets.

== Changelog ==

= 0.7.2 =
* Fixed a bug causing the "More details" Google Calendar information to be displayed in the wrong timezone
* Fixed a bug that prevented setting the cache duration to 0 from working correctly
* Fixed an issue that prevented Ajax from working with FORCE_SSL_ADMIN enabled
* Now uses [wp_enqueue_scripts](http://wpdevel.wordpress.com/2011/12/12/use-wp_enqueue_scripts-not-wp_print_styles-to-enqueue-scripts-and-styles-for-the-frontend/)

= 0.7.1 =
* Fixed bug causing AJAX enabled calendar grids to not function correctly
* Fixed bug causing all-day events from outside required date range to be displayed
* Fixed bug causing tooltip date title heading setting to be ignored
* Added further data sanitisation on output
* Feeds with no events will now be cached to prevent HTTP requests on every page load

= 0.7 =
* Fixed bug causing event dates / times to be displayed in the wrong timezone
* Changed the [link-path] Event Display Builder shortcode to [url]
* Fixed an Opera specific CSS issue causing page lists to be hidden
* Lists can now be displayed in descending or ascending order
* Added [event-id] and [cal-id] Event Display Builder shortcodes
* Added an offset parameter for date / time based Event Display Builder shortcodes
* Added an autolink parameter for enabling / disabling automatic linking of URLs
* Added gce-day-past or gce-day-future classes to calendar grid cells
* Cleaned up CSS

= 0.6 =
* Drastically reduced memory usage
* Improved feed data caching system
* Improved error reporting
* General performance and efficiency improvements
* Added a few more shortcodes to the event display builder
* Other [miscellaneous changes / additions and bug fixes](http://www.rhanney.co.uk/2011/04/29/google-calendar-events-0-6)

= 0.5 =
* Added [event display builder](http://www.rhanney.co.uk/plugins/google-calendar-events/event-display-builder) feature, which vastly improves the customization possibilities of the plugin. This feature encompasses many of the most requested features, such as:
    - All-day events can be handled differently than 'normal' events
    - Start and end times / dates can be displayed on the same line (as can any other event information)
    - HTML (and Markdown) entered in Google Calendar fields can be properly parsed
* Start and end times for retrieval of events are now much more flexible
* A custom error message for non-admin users can now be specified
* No longer loads SimplePie when it is not required

= 0.4.1 =
* Fix / workaround for the long-running timezone bug. Please take a look at [this](http://www.rhanney.co.uk/2011/01/16/google-calendar-events-0-4-1) for more information.
* Added additional 'Maximum no. events to display' option to widget / shortcode (mainly to address a further issue caused by the above fix)
* i18n related bug fix
* Added support for widget_title filter (courtesy of [James](http://lunasea-studios.com))
* Added Hungarian (hu_HU) translation ([danieltakacs](http://ek.klog.hu))
* Now using minified version of jQuery qTip script

= 0.4 =
* More control over how start and end dates / times are displayed
* Events can now be limited to a specified timeframe (number of days)
* Events on the same day in lists can now be shown under a single date title
* JavaScript can now be added to the footer rather than the header, via an option
* The 'Loading...' text can now be customized
* Description text can now be limited to a specified number of words
* Multi-day events can be shown on each day that they span ([sort of](http://www.rhanney.co.uk/2010/08/19/google-calendar-events-0-4#multiday))
* Bug fixes
* i18n / l10n fixes

= 0.3.1 =
* l10n / i18n fixes. Dates should now be localized correctly and should maintain localization after an AJAX request
* MU / Multi-site issues. Issues preventing adding of feeds have been addressed

= 0.3 =
* Now allows events from multiple Google Calendar feeds to be displayed on a single calendar grid / list
* Internationalization support added

= 0.2.1 =
* Added option to allow 'More details' links to open in new window / tab.
* Added option to choose a specific timezone for each feed
* Line breaks in an event description will now be preserved
* Fixed a bug casing the title to not be displayed on lists
* Other minor bug fixes

= 0.2 =
* Added customization options for how information is displayed.
* Can now display: start time, end time and date, location, description and event link.
* Tooltips now using qTip jQuery plugin.

= 0.1.4 =
* More bug fixes.

= 0.1.3 =
* Several bug fixes, including fixing JavaScript problems that prevented tooltips appearing.

= 0.1.2 =
* Bug fixes.

= 0.1.1 =
* Fix to prevent conflicts with other plugins.
* Changes to readme.txt.

= 0.1 =
* Initial release.

== Upgrade Notice ==

= 0.5 =
Event retrieval date / time range is now much more flexible. Also adds event display builder, which allows much greater customization of the event information displayed.

== Frequently Asked Questions ==

Please visit the [FAQ page](http://www.rhanney.co.uk/plugins/google-calendar-events/frequently-asked-questions). If you have further questions, leave a comment on the [plugin homepage](http://www.rhanney.co.uk/plugins/google-calendar-events), or [send me an email](http://www.rhanney.co.uk/contact).