=== Query Monitor ===
Contributors: johnbillion
Tags: debug, debug-bar, debugging, development, developer, performance, profiler, queries, query monitor, rest-api
Requires at least: 3.7
Tested up to: 4.9
Stable tag: 3.1.1
License: GPLv2 or later
Requires PHP: 5.3

Query Monitor is the developer tools panel for WordPress.

== Description ==

Query Monitor is the developer tools panel for WordPress. It includes some advanced features such as Ajax and REST API debugging, and the ability to narrow down its output by plugin or theme.

Query Monitor focuses heavily on presenting its information in a useful manner. For example, aggregate database query information is made available, allowing you to quickly determine poorly performing plugins, themes, or functions. It adds an admin toolbar menu showing an overview of the current page, with complete data shown in a panel once you select a menu item.

For complete information, please see [the Query Monitor website](https://querymonitor.com/).

Here's an overview of some of what's shown:

= Database Queries =

 * Shows all database queries performed on the current request
 * Shows notifications for slow queries, duplicate queries, and queries with errors
 * Filter queries by query type (`SELECT`, `UPDATE`, `DELETE`, etc)
 * Filter queries by component (WordPress core, Plugin X, Plugin Y, theme)
 * Filter queries by calling function
 * View aggregate query information grouped by component, calling function, and type
 * Super advanced: Supports multiple instances of wpdb on one page (more info in the FAQ)

Filtering queries by component or calling function makes it easy to see which plugins, themes, or functions are making the most (or the slowest) database queries.

= Hooks =

 * Shows all hooks fired on the current request, along with hooked actions, their priorities, and their components
 * Filter actions by component (WordPress core, Plugin X, Plugin Y, theme)

= Theme =

 * Shows the template filename for the current request
 * Shows the complete template hierarchy for the current request (WordPress 4.7+)
 * Shows all template parts used on the current request

= PHP Errors =

 * PHP errors (warnings, notices, stricts, and deprecated) are presented nicely along with their component and call stack
 * Shows an easily visible warning in the admin toolbar

= Request =

 * Shows query vars for the current request, and highlights custom query vars
 * Shows all matched rewrite rules and associated query strings

= Scripts & Styles =

 * Shows all enqueued scripts and styles on the current request, along with their URL and version
 * Shows their dependencies and dependents, and displays an alert for any broken dependencies

= Languages =

 * Shows language settings and text domains
 * Shows the MO files for each text domain and which ones were loaded or not

= HTTP Requests =

 * Shows all HTTP requests performed on the current request (as long as they use WordPress' HTTP API)
 * Shows the response code, call stack, component, timeout, and time taken
 * Highlights erroneous responses, such as failed requests and anything without a `200` response code

= User Capability Checks =

 * Shows every user capability check that is performed on the page, along with the result and any parameters passed along with the capability check.

= Redirects =

 * Whenever a redirect occurs, Query Monitor adds an `X-QM-Redirect` HTTP header containing the call stack, so you can use your favourite HTTP inspector or browser developer tools to easily trace where a redirect has come from

= Ajax =

The response from any jQuery Ajax request on the page will contain various debugging information in its headers. Any errors also get output to the developer console. No hooking required.

Currently this includes PHP errors and some overview information such as memory usage, but this will be built upon in future versions.

= REST API =

The response from an authenticated WordPress REST API (v2 or later) request will contain various debugging information in its headers, as long as the authenticated user has permission to view Query Monitor's output.

Currently this includes PHP errors and some overview information such as memory usage, but this will be built upon in future versions.

= Authentication =

By default, Query Monitor's output is only shown to Administrators on single-site installs, and Super Admins on Multisite installs.

In addition to this, you can set an authentication cookie which allows you to view Query Monitor output when you're not logged in (or if you're logged in as a non-administrator). See the bottom of Query Monitor's output for details.

= Privacy Statement =

Query Monitor does not persistently store any of the data that it collects. It does not send data to any third party, nor does it include any third party resources.

[Query Monitor's full privacy statement can be found here](https://github.com/johnbillion/query-monitor/wiki/Privacy-Statement).

== Screenshots ==

1. The admin toolbar menu showing an overview
2. Aggregate database queries by component
3. User capability checks with an active filter
4. Database queries complete with filter controls
5. Hooks and actions
6. HTTP requests (showing an HTTP error)
7. Aggregate database queries grouped by calling function

== Frequently Asked Questions ==

= Who can see Query Monitor's output? =

By default, Query Monitor's output is only shown to Administrators on single-site installs, and Super Admins on Multisite installs.

In addition to this, you can set an authentication cookie which allows you to view Query Monitor output when you're not logged in (or if you're logged in as a non-administrator). See the bottom of Query Monitor's output for details.

= Does Query Monitor itself impact the page generation time or memory usage? =

Short answer: Yes, but only a little.

Long answer: Query Monitor has a small impact on page generation time because it hooks into WordPress in the same way that other plugins do. The impact is low; typically between 10ms and 100ms depending on the complexity of your site.

Query Monitor's memory usage typically accounts for around 10% of the total memory used to generate the page.

= Are there any add-on plugins for Query Monitor? =

[A list of add-on plugins for Query Monitor can be found here.](https://github.com/johnbillion/query-monitor/wiki/Query-Monitor-Add-on-Plugins)

In addition, Query Monitor transparently supports add-ons for the Debug Bar plugin. If you have any Debug Bar add-ons installed, just deactivate Debug Bar and the add-ons will show up in Query Monitor's menu.

= Where can I suggest a new feature or report a bug? =

Please use [the issue tracker on Query Monitor's GitHub repo](https://github.com/johnbillion/query-monitor/issues) as it's easier to keep track of issues there, rather than on the wordpress.org support forums.

= Is Query Monitor available on WordPress.com VIP Go? =

Yep! You just need to add `define( 'WPCOM_VIP_QM_ENABLE', true );` to your `vip-config/vip-config.php` file.

(It's not available on standard WordPress.com VIP though.)

= I'm using multiple instances of `wpdb`. How do I get my additional instances to show up in Query Monitor? =

You'll need to hook into the `qm/collect/db_objects` filter and add an item to the array with your connection name as the key and the `wpdb` instance as the value. Your `wpdb` instance will then show up as a separate panel, and the query time and query count will show up separately in the admin toolbar menu. Aggregate information (queries by caller and component) will not be separated.

= Do you accept donations? =

No, I do not accept donations. If you like the plugin, I'd love for you to [leave a review](https://wordpress.org/support/view/plugin-reviews/query-monitor). Tell all your friends about the plugin too!

== Changelog ==

For Query Monitor's changelog, please see [the Releases page on GitHub](https://github.com/johnbillion/query-monitor/releases).
