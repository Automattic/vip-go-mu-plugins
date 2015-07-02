=== PushUp Notifications ===
Contributors: 10up, carldanley, jakemgold, johnjamesjacoby, houseofgrays, cmmarslender, dsawardekar
Donate link: https://pushupnotifications.com/
Tags: push notifications, push notification, notifications, push, news, services
Requires at least: 3.8
Tested up to: 4.0.1
Stable tag: 1.2.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Effortlessly and selectively deliver push notifications to your readers, instantly alerting them about new content.

== Description ==

PushUp effortlessly connects your WordPress site to our premium **push notification delivery service**, so that you can selectively deliver on-demand notifications to your readers as you update your content.

PushUp requires an account at [pushupnotifications.com](http://pushupnotifications.com), in order to use our high scale push delivery infrastructure.

[youtube http://www.youtube.com/watch?v=HWu4XWEesX4]

PushUp currently supports [Safari Push Notifications on OS X Mavericks](https://developer.apple.com/notifications/safari-push-notifications/); support for additional browsers and services is coming soon.

* **Real-time analytics.** Monitor engagement, as it happens. How many eligible visitors accepted or declined notifications? How many subscribers do you have?
* **Pay as your grow.** Pay for what you need, when you need it, so increased engagement never interrupts your service. No subscribers? No fee. Tightening your belt? Don't click push, don't pay PushUp.
* **For WordPress, by Makers of WordPress.** Designed, tested, and engineered by [10up](http://10up.com), an agency with 25+ core contributors on staff. You won't find gaudy ads or attention stealing buttons; in fact, we think you'll forget that PushUp wasn't part of WordPress.
* **Be selective with one-check push requests.** PushUp adds a "Send push notification" checkbox right above your publish button. Respect your readers - and wallet - by pushing your best content.
* **Impatient writer syndrome protection.** No matter how many times your author mashes that publish button, the same notification will never be sent twice.
* **Built for scale, battle tested by big names.** Vetted by WordPress.com VIP, and already running like a champ on sites like [9to5mac.com](http://9to5mac.com), the world's most popular Apple news blog,  [Deadline.com](http://deadline.com), and [Edelman.com](http://edelman.com). We've already delivered over 18 million notifications, including more than 500,000 notifications in a single day.
* **No extra software necessary.** We leverage technology built right into the browser and operating system, beginning with Safari on OS X Mavericks. Your readers simply accept a notification request - that only shows up the first time they visit - and they will start receiving notifications. Even when their browser is closed.

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory, or simply install from the plugin repository.
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Follow the instructions to setup or activate your [pushupnotifications.com](http://pushupnotifications.com) account.

== Frequently Asked Questions ==

Find a full list of [FAQs on our website](https://pushupnotifications.com/faq/).

= How do my website visitors unsubscribe to notifications? =

Visitors can unsubscribe by opening Safari preferences from the "Safari" menu, clicking on the "Notifications" icon, finding your website in the list of sites, and choosing "Deny" (instead of "Allow").

= Why do you only support Safari 7 on OS X Mavericks (and newer)? =

We think Apple's implementation of website push notifications is the best we've seen - the browser doesn't have to be open, and the implementation uses absolutely no additional system resources. We already have plans to extend the service beyond Safari 7.

= What kind of analytics do you provide? =

At the moment, we provide the following data points:

1.	Conversion rate: number of potential subscribers who accepted notifications.
1.	Total number of current subscribers.
1.	Total number of unique push notifications sent this month.
1.	Total number of unique push notifications sent (all time).

We plan to implement notification delivery click-through (how many visitors actually clicked back to your site from a notification) in a soon to come update.

= How much does PushUp cost? =

We charge a one time site setup fee (typically $14.99) for your first site, which provides up to 100 unique notifications each month for life, as well unlimited use for the first 30 days.

After the first month, we have a "pay as you grow" model where you pay for the actual volume of unique notifications sent that month, with tiered discounts for higher volume. Small businesses shouldn't expect to pay more than $1.99 or $4.99 each month. Even 1 million unique notifications will cost only $99.99. If you want to stop paying, just deactivate the plugin or don't check the push option. [You can play with the price calculator on our website.](https://pushupnotifications.com/#pricing-explore)

== Screenshots ==

1. Selectively and instantly deliver push notifications to your desktop.
2. One-check notifications - check "Push desktop notification" and click publish. It's that simple.
3. Seamlessly integrated analytics, right inside WordPress.
4. Visitors click allow in Safari 7 to receive notifications. That's it.

== Changelog ==

= 1.2.2 =

* Tested up to 4.0.1.

= 1.2.1 =

* Improves error handling when Safari Push Notifications are disabled.
* Add a filter to the localized API path for testing and potential future scaling.

= 1.2 =
* Customize when the notification prompt should appear: after a set number of visits, and/or an easily defined custom event like clicking a link
* Fix edge case resulting in Website Name and/or Notification Title disappearing in the settings screen
* Updates to inline help

= 1.1.7 =
* Fix scheduled posts not being pushed in some instances

= 1.1.6 =
* Use unfiltered short links to ensure click through analytic data passes to final web address
* Introduce filters enabling customization of click through parameters (previously handled on PushUp's side)
* Refine plugin upgrade checks, preventing needless API calls and fixing activation scenarios where settings needed to be saved twice

= 1.1.5 =
* "Save Changes" button on settings screen more appropriately labeled "Activate PushUp" when plugin is not fully configured to send notifications
* Additional improvements to admin performance by further reducing PushUp API requests

= 1.1.4 =
* Improve admin performance by reducing requests to PushUp API
* Refine logic for determining whether to render front end scripts

= 1.1.3 =
* Improved caching of website credentials on the front end

= 1.1.2 =
* Fix an edge case with plugins that manipulate WordPress scheduler / WP-Cron (e.g. WP Cron Control)

= 1.1.1 =
* Refine support for scheduled posts

= 1.1 =
* Support for validating a provisioned domain name mapped with WordPress MU Domain Mapping
* Requested pushes now occur when posts are published outside of the post editor (e.g. scheduled posts, quick edit)

= 1.0 =
* First version!
