=== CampTix Event Ticketing ===
Contributors:      automattic, kovshenin, andreamiddleton, iandunn
Tags:              ticketing, event ticketing
Requires at least: 3.5
Tested up to:      4.1
Stable tag:        1.4.2
Donate link:       http://wordpressfoundation.org/donate/
License:           GPLv2 or later
License URI:       http://www.gnu.org/licenses/gpl-2.0.html

Simple and Flexible ticketing brought to you by WordCamp.org

== Description ==

CampTix is an easy to use and flexible event ticketing plugin created by WordCamp.org. Allow visitors to purchase tickets to your online or offline event, directly from your WordPress website.

* Multiple tickets and attendees forms
* Coupon codes for discounts
* Mass e-mail attendees
* Export attendees data into CSV or XML
* Public attendees list
* Revenue reports and summaries
* Refund purchased tickets
* and much more!

Feel free to post your feature requests, issues and pull requests to [CampTix on GitHub](https://github.com/automattic/camptix "CampTix on GitHub").

== Installation ==

1. Download and extract CampTix in your `wp-content/plugins` directory
1. Activate the plugin through the Plugins menu in WordPress
1. Go to Tickets - Setup to configure your event settings and payment methods
1. Create a ticket or two, place the `[camptix]` shortcode on a Page
1. Start selling!

For more information, visit the [Getting Started](https://github.com/automattic/camptix/wiki "Getting Started") guide on CampTix Wiki.

== Screenshots ==

1. Ticket sales table
2. Attendee registration form
3. Attendee admin view
4. Summarize by ticket type
5. Summarize by purchase day of week
6. Revenue report
7. Mass e-mail attendees

== Changelog ==

= 1.4.2 =
* Added a nonce check for privacy and attendance toggles for better security
* Various small i18n fixes and language updates
* Various new actions and filters for more flexibility
* New addon that allows admins to track attendance
* New addon that allows admins to require users to be logged in to purchase a ticket
* Removed pending attendees from revenue reports
* [Full changelog](https://github.com/Automattic/camptix/compare/80b2d7997272aea68fa0cfb509d3d72f15cec18a...a9487f954f3013e698e7991c8f12e86ae85234ae)

= 1.4.1 =
* Updated PayPal module to use HTTP 1.1 now that PayPal requires it. Fixes "A payment error has occurred" errors.
* Added support for Eastern name ordering.
* Updated Japanese and French translations.
* Add Slovak translation.
* Fixes E_STRICT notices in PHP 5.4.
* Adds [camptix_stats] shortcode.
* [Full changelog](https://github.com/Automattic/camptix/compare/6c2ff5413d6294b0fca6abc0ebd9124a6b9399f8...e71760abbfb025f4184e329e4c029c694a4d3a01)

= 1.4 =
* Attendees can automatically refund their tickets
* More e-mail templates are customizable
* Added translations for Swedish (Jonathan De Jong), German (Raphael Michel), Japanese (Naoko Takano), Russian (Konstantin Kovshenin), and Portuguese (Rafael Funchal)
* New actions and filters for customization
* Fixed a bug where the [camptix] shortcode would break when used on the homepage
* Reintroduced the Refund All Tickets feature
* Handles duplicate requests from PayPal more gracefully, so attendees aren't set to a failed status
* Added a checkbox to toggle the Attendee privacy feature
* Added an upgrade command for WP-CLI
* [Full changelog](https://github.com/Automattic/camptix/compare/826cc2b...a53af6d)

= 1.3.1 =
* Better escaping and sanitization
* Better error messages during failed payments
* Fixed a bug where the shortcode would display in plain text
* Other minor bug fixes and clean ups

= 1.3 =
* Added the ability to edit confirmation e-mails
* Reworked ticket questions, both under the hood and UI
* Added support to edit new and existing questions
* Added a bunch of currencies for PayPal
* Few bug fixes and minor enhancements

= 1.2.1 =
* Numerous bugs fixed
* RTL stylesheets
* Predefined PayPal credentials with a filter
* French and Hebrew translations: props xibe and maor
* New currency: ILS

= 1.2 =
* Added and API for payment methods
* Enhanced logging around payments
* UI cleanup in ticket questions
* Invalidate attendees list shortcode when an attendee is changed
* Improved admin columns in attendees, tickets and coupons
* Added GBP currency to PayPal
* Enabled meta logging addon by default
* Added textarea and radio question types
* Added column attribute to the [camptix_attendees] shortcode
* Added a couple of language packs
* Minor cleanups and bugfixes

= 1.1 =
* Added JPY currency
* Added l10n functions
* Removing closure functions to support php 5.2
* Questions v2 now a public feature
* Minor cleanups, bugfixes and enhancements

= 1.0 =
* First version
