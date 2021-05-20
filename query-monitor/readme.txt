# Query Monitor
Contributors: johnbillion
Tags: debug, debug-bar, debugging, development, developer, performance, profiler, queries, query monitor, rest-api
Requires at least: 3.7
Tested up to: 5.7
Stable tag: 3.7.1
License: GPLv2 or later
Requires PHP: 5.3
Donate link: https://johnblackbourn.com/donations/

Query Monitor is the developer tools panel for WordPress.

## Description

Query Monitor is the developer tools panel for WordPress. It enables debugging of database queries, PHP errors, hooks and actions, block editor blocks, enqueued scripts and stylesheets, HTTP API calls, and more.

It includes some advanced features such as debugging of Ajax calls, REST API calls, and user capability checks. It includes the ability to narrow down much of its output by plugin or theme, allowing you to quickly determine poorly performing plugins, themes, or functions.

Query Monitor focuses heavily on presenting its information in a useful manner, for example by showing aggregate database queries grouped by the plugins, themes, or functions that are responsible for them. It adds an admin toolbar menu showing an overview of the current page, with complete debugging information shown in panels once you select a menu item.

For complete information, please see [the Query Monitor website](https://querymonitor.com/).

Here's an overview of what's shown for each page load:

* Database queries, including notifications for slow, duplicate, or erroneous queries. Allows filtering by query type (`SELECT`, `UPDATE`, `DELETE`, etc), responsible component (plugin, theme, WordPress core), and calling function, and provides separate aggregate views for each.
* The template filename, the complete template hierarchy, and names of all template parts that were loaded or not loaded.
* PHP errors presented nicely along with their responsible component and call stack, and a visible warning in the admin toolbar.
* Blocks and associated properties in post content when using WordPress 5.0+ or the Gutenberg plugin.
* Matched rewrite rules, associated query strings, and query vars.
* Enqueued scripts and stylesheets, along with their dependencies, dependents, and alerts for broken dependencies.
* Language settings and loaded translation files (MO files) for each text domain.
* HTTP API requests, with response code, responsible component, and time taken, with alerts for failed or erroneous requests.
* User capability checks, along with the result and any parameters passed to the capability check.
* Environment information, including detailed information about PHP, the database, WordPress, and the web server.
* The values of all WordPress conditional functions such as `is_single()`, `is_home()`, etc.
* Transients that were updated.

In addition:

* Whenever a redirect occurs, Query Monitor adds an HTTP header containing the call stack, so you can use your favourite HTTP inspector or browser developer tools to trace what triggered the redirect.
* The response from any jQuery-initiated Ajax request on the page will contain various debugging information in its headers. PHP errors also get output to the browser's developer console.
* The response from an authenticated WordPress REST API request will contain an overview of performance information and PHP errors in its headers, as long as the authenticated user has permission to view Query Monitor's output. An [an enveloped REST API request](https://developer.wordpress.org/rest-api/using-the-rest-api/global-parameters/#_envelope) will include even more debugging information in the `qm` property of the response.

By default, Query Monitor's output is only shown to Administrators on single-site installations, and Super Admins on Multisite installations.

In addition to this, you can set an authentication cookie which allows you to view Query Monitor output when you're not logged in (or if you're logged in as a non-Administrator). See the Settings panel for details.

### Other Plugins

I maintain several other plugins for developers. Check them out:

* [User Switching](https://wordpress.org/plugins/user-switching/) provides instant switching between user accounts in WordPress.
* [WP Crontrol](https://wordpress.org/plugins/wp-crontrol/) lets you view and control what's happening in the WP-Cron system

### Privacy Statement

Query Monitor is private by default and always will be. It does not persistently store any of the data that it collects. It does not send data to any third party, nor does it include any third party resources.

[Query Monitor's full privacy statement can be found here](https://github.com/johnbillion/query-monitor/wiki/Privacy-Statement).

## Screenshots

1. Admin Toolbar Menu
2. Aggregate Database Queries by Component
3. Capability Checks
4. Database Queries
5. Hooks and Actions
6. HTTP API Requests
7. Aggregate Database Queries by Calling Function

## Frequently Asked Questions

### Does this plugin work with PHP 8?

Yes.

### Who can access Query Monitor's output?

By default, Query Monitor's output is only shown to Administrators on single-site installations, and Super Admins on Multisite installations.

In addition to this, you can set an authentication cookie which allows you to view Query Monitor output when you're not logged in, or when you're logged in as a user who cannot usually see Query Monitor's output. See the Settings panel for details.

### Does Query Monitor itself impact the page generation time or memory usage?

Short answer: Yes, but only a little.

Long answer: Query Monitor has a small impact on page generation time because it hooks into WordPress in the same way that other plugins do. The impact is low; typically between 10ms and 100ms depending on the complexity of your site.

Query Monitor's memory usage typically accounts for around 10% of the total memory used to generate the page.

### Are there any add-on plugins for Query Monitor?

[A list of add-on plugins for Query Monitor can be found here.](https://github.com/johnbillion/query-monitor/wiki/Query-Monitor-Add-on-Plugins)

In addition, Query Monitor transparently supports add-ons for the Debug Bar plugin. If you have any Debug Bar add-ons installed, deactivate Debug Bar and the add-ons will show up in Query Monitor's menu.

### Where can I suggest a new feature or report a bug?

Please use [the issue tracker on Query Monitor's GitHub repo](https://github.com/johnbillion/query-monitor/issues) as it's easier to keep track of issues there, rather than on the wordpress.org support forums.

### Is Query Monitor available on Altis?

Yes, the [Altis Developer Tools](https://www.altis-dxp.com/resources/developer-docs/dev-tools/) are built on top of Query Monitor.

### Is Query Monitor available on WordPress.com VIP Go?

Yes, it's included as part of the VIP Go platform. However, a user needs to be granted the `view_query_monitor` capability to see Query Monitor even if they're an administrator.

Please note that information about database queries and the environment is somewhat restricted on VIP. This is a platform restriction and not a Query Monitor issue.

### I'm using multiple instances of `wpdb`. How do I get my additional instances to show up in Query Monitor?

You'll need to hook into the `qm/collect/db_objects` filter and add an item to the array containing your `wpdb` instance. For example:

    add_filter( 'qm/collect/db_objects', function( $objects ) {
        $objects['my_db'] = $GLOBALS['my_db'];
        return $objects;
    } );

Your `wpdb` instance will then show up as a separate panel, and the query time and query count will show up separately in the admin toolbar menu. Aggregate information (queries by caller and component) will not be separated.

### Can I click on stack traces to open the file in my editor?

Yes. You can enable this on the Settings panel.

### Do you accept donations?

[I am accepting sponsorships via the GitHub Sponsors program](https://johnblackbourn.com/donations/) and any support you can give will help me maintain this plugin and keep it free for everyone.

In addition, if you like the plugin then I'd love for you to [leave a review](https://wordpress.org/support/view/plugin-reviews/query-monitor). Tell all your friends about it too!

## Changelog ##

### 3.7.1 ###

* Add a fallback for timing processing during Ajax requests that are dispatched before the `shutdown` hook.

### 3.7.0 ###

* <a href="https://querymonitor.com/blog/2021/05/debugging-wordpress-rest-api-requests/">Introduce debugging output in a `qm` property in enveloped REST API responses</a>
* Add HTTP API call information to the overview panel
* Don't show QM output inside WordPress embeds as nobody uses this
* Don't try to access the `QM_HIDE_SELF` constant before it's defined
* Process the timing and memory related stats as early as possible so the data isn't too skewed


### 3.6.8 ###

* Add WordPress memory usage statistic to Overview panel
* Add block context information to the Blocks panel
* Fix row highlighting of TH cells
* Fix some panel resizing bugs


### 3.6.7 ###

* Implement a `QM_DB_SYMLINK` constant to prevent the `db.php` symlink being put into place.
* Remove a dependency on `SAVEQUERIES` in the query collector.
* Remove invalid `scope` attributes on table cells.


### 3.6.6 ###

* PHP 8 fix.
* Improve the display for various empty values when logging.
* Don't display child menus until the parent menu is active. Makes the menu clearer.
* Detect local host names in HTTP API requests and don't mark them as ignoring certificate verification.
* Prevent the text in toggle buttons from being selected when selecting data in tables.
* Remove support for the Dark Mode plugin which isn't Dark Mode any more.


### 3.6.5 ###

* Always show the Logs panel, with a link to help docs.
* Whole bunch of improvements to QM's "broken" state handling.
* Remove usage of deprecated jQuery methods.
* Add support for Altis dependencies as components.
* Add `innodb_buffer_pool_size` variable to the mysql environment list.
* Preformat the Logger output
* Fix the PHP version check.


### 3.6.4 ###

* Correct an error introduced in 3.6.2 with the extra early error handling (ironic).

### 3.6.3 ###

* Correct the size of the close icon.

### 3.6.2 ###

  * Capture and display the most recent PHP error that occurred before QM loaded.
  * Add support for the environment type added in WP 5.5.
  * Avoid a potentially blank translation for some plural forms.
  * Increase some contrast in dark mode.
  * Combine the response-related sections of the Request panel.
  * Add extra sanity checking when attempting to fetch the posix user information.

### 3.6.1 ###

* Adjust the bottom margin when the QM panel is open so QM doesn't cover the bottom of the page. Works more often than not.
* Prevent QM from triggering a fatal itself if a fatal occurs before the HTML dispatcher is loaded.
* Add an informational message to the template output when template hooks are in use.
* Fix errors caused by missing user or group IDs when collecting environment data.
* Add TextMate to list of supported editors.
* Demote some cache warnings to informational messages.
* Support passing backtrace to `QM_Backtrace`.


### 3.6.0 ###

* Improvements to the UI when a fatal error occurs, including an admin toolbar warning.
* Improvements to the UI when QM is running in "broken" mode due to missing jQuery or an unrecoverable JavaScript error.
* Don't display fatal errors if error display is off and the user cannot view QM.
* Improvements to the visual appearance of the `wp_die()` output.
* Simplify re-throwing a caught exception so QM doesn't get the blame for fatal errors, eg. in the WordPress core fatal error handler.
* Add support for logging a variable of any type in the logger, as a replacement for var dumping.
* Don't show a message for errors in Ajax calls that have already occurred on the main page load.
* Don't dispatch QM during an iframed request, eg the plugin info modal or an upgrader action.
* Hide QM itself from various panels by default to remove noise. Can be controlled via the existing `QM_HIDE_SELF` configuration constant.
* Support for the new `is_favicon()` conditional added in WP 5.4.
* Fix the side panel resizing functionality.
* Add a WP-CLI command for creating the symlink to the db file.
* Add filters to `QM_Util::get_file_dirs()` and `get_file_component()` to allow support for non-standard plugin and theme locations.
* Add an action that fires when QM enqueues its assets, so add-on plugins can enqueue theirs only when necessary.


### 3.5.2 ###

* Add support for exposing [Full Site Editing](https://github.com/WordPress/gutenberg/issues?q=label%3A%22%5BFeature%5D+Full+Site+Editing%22) blocks in the Block Editor panel.

### 3.5.1 ###

  * Defer population of the `found_formatted` property because this can fire before WPML has initialised its locale proxy. Fixes #485.
  * Ensure all error types are accounted for when populating the panel menu error count. Fixes #486.


### 3.5.0 ###

* Add an editor selection UI on the Settings panel.
* Improve the output of missing asset dependencies.
* Improve the output of unsuccessful template parts.
* Handle non-boolean constants such as `WP_DEBUG_LOG`, which now accepts a path too.
* Add support for touch devices when resizing the panel. (Works alright-ish, probably needs some animation frame work.)
* Apply the same styles to notices, deprecated, and strict errors.
* Some more style resets for compatibility with popular themes.
* Style changes to bring QM inline with WP 5.3's improved button and focus styles.
* More colour contrast and dark mode tweaks.
* Add permalink-related filters to the concerned filters for the Request panel.
* Fix and improve the admin toolbar menu hover colours.
* Add the error count to the panel menu.
* Remove unnecessary use of plural forms added in 3.4.0.
* More CSS resets to avoid overly tall filters in Firefox.
* Improved styling for warning rows.
* Display the log count in the menu item.


### 3.4.0 ###

* Introduce an exception handler so a stack trace can be shown for fatal errors in PHP >= 7.
* Add separate persistence of QM window for front-end and admin area.
* Add the request and response HTTP headers to the Request panel.
* Introduce Started and Stopped columns in the Timings panel.
* By popular demand, revert back to closest first ordering of stack traces so they're inline with most other dev tools out there.
* Show the script handle in addition to the text domain in the Languages panel.
* Improve the panel menu highlighting colours.
* Better presentation of the default and current values for the settings constants.
* Truncate long host names in the Scripts and Styles panels.
* Add some more of the admin screen globals to the admin collector.
* Switch back to using a monospace font in numeric data cells.
* Allow dark mode to be enabled with `QM_DARK_MODE`.
* Display the total query count even when `SAVEQUERIES` is defined as false.
* Allow proper plural forms to be used wherever a phrase includes a numeric value.
* More style resetting for compatibility with Twenty Twenty.
* Avoid a division by zero when cache hits is 0.
* Switch to (mostly) CSS for the child menu item marker.


### 3.3.7 ###

* Expose instances where a requested template part was not loaded.
* Update the docs for multiple `wpdb` instances.
* Various accessibility improvements.
* Remove the RDBMS info as it's not at all reliable.


### 3.3.6 ###

* Fix a compatibility issue where QM and the fatal error protection in WordPress 5.2+ were handling syntax errors differently.
* Fix some bugs with the icons for the panel controls.

### 3.3.5 ###

  * Add support for the new `get_template_part` action in WP 5.2.
  * Add a friendly error message when the PHP version requirement isn't met.
  * Add support for the new privacy policy conditional in WP 5.2.
  * Add support for the new privacy policy template in WP 5.2.

### 3.3.4 ###

* Updated CSS to avoid conflicts with themes using `ul`, `nav`, and `li` styling.
* Don't define `ajaxurl` if there are no Debug Bar panels to show.
* New icon for QM! By [Tubagus Didin Asrori](https://www.instagram.com/asrorigus/).
* Push the close button a bit further away from the edge of the screen to avoid scrollbar interference on macOS.
* Fix clash with object cache plugins that keep their hit and miss stats private.
* Add missing asset position counters.

### 3.3.3 ###

* Add scripts and styles counts to admin menu items.
* Group the cache logic together to avoid calling cache related functionality when it's not available. Fixes #418.
* Switch to installing the test suite as Composer dependencies.

### 3.3.2 ###

  * Improve the accuracy of the `ver` parameter for enqueued scripts and styles.
  * Separate and simplify the output for the object cache and opcode cache statuses. Fixes #413.
  * Better formatting when no object cache stats are available.


### 3.3.1 ###

* Move the hook processing into its own class and out of the collector, so it can be re-used even if the Hooks collector isn't in use. Fixes #399.
* Increase the sidebar layout to 100% height when there's no admin toolbar.
* Update the QM element ID in the "worst case scenario" JS. Fixes #398.
* Improve the layout of the Settings panel.
* Force the `Core` and `Non-Core` filter items to the bottom of the list, so plugins and themes takes precedence.
* Add an entry for the Settings screen to the narrow view nav menu.
* Add the admin notice hooks to the list of concerned actions for the Admin Screen panel.

### 3.3.0 ###

New features! Read about them here: https://querymonitor.com/blog/2019/02/new-features-in-query-monitor-3-3/

* Introduce sub-menus for displaying Hooks in Use for each panel.
* Output the call stack and responsible component when `wp_die()` is called.
* Support for JavaScript (Jed) translations in WordPress 5.0+.
* Add render timing for blocks using the new hooks introduced in WordPress 5.1.
* Introduce a toggle to display QM on the side of the window.
* Allow non-string values to be used in the logger message. They'll be presented as JSON formatted strings.
* Allow boolean values to be used in log message contexts.
* Add some margin to the Close button so it doesn't get covered up so much by scroll bars.
* Prefix QM's cookie name with `wp-` to ensure interoperability with caches and proxies.
* Separate the Scripts and Styles collector and outputter so they're actually two separate panels.
* Add support for opcode cache detection separate from the object cache detection.
* Rename the main QM container to get around the fact that its name clashes with the plugin rows in older versions of WordPress.
* Avoid using `wp_parse_url()` as it was only introduced in WP 4.4.


### 3.2.2 ###

* Support for nested content blocks (eg. in columns).
* Hide long innerHTML content of blocks behind a toggle.
* Add validation of the referenced media file in media blocks.
* Ensure asset URLs include the `ver` query arg.
* Tweak the warning colours.
* Coding standards.
* Layout tweaks.


### 3.2.1 ###

* Fix a fatal error for < 5.0 sites that are not running the Gutenberg plugin.

### 3.2.0 ###

* Add a new `Blocks` panel for debugging blocks in post content. Supports WordPress 5.0 and the Gutenberg plugin.
* Display the number of times that each template part was included.
* Allow the scripts and styles output to be filtered based on Dependencies and Dependents.
* Remove the `Pin` button in favour of always pinning QM when it's open.
* Add a "Settings" link to the Plugins screen that opens the settings panel.
* Add a link to the Add-ons page on the wiki.
* Add some more verbose and visible error notices for suboptimal PHP configuration directives.
* Add support for identifying any RDBMS, not just MySQL and MariaDB.
* Perform the PHP version check earlier on so that fewer parts of QM need to be compatible with PHP 5.2.
* Highlight plain `http` requests to the HTTP API as insecure.
* Ensure the `Template` admin menu is always shown, even if the template file name isn't known.
* Adjust the JS and CSS asset source to not include the host.
* Add a warning for insecure JS and CSS assets.
* Remove before and after pseudo-elements in the style reset.
* Show as much theme and template information as possible, even if QM doesn't know the template name.
* Highlight non-core rows when filtering the Hooks & Actions panel by Non-Core.
* Add a filter for environment constants.
* Min width CSS for buttons.
* First pass at documenting filters and hooks.
* More coding standards updates.

### 3.1.1 ###

* Add a dark mode for the UI which is used via the Dark Mode plugin.
* Display Query Monitor's output in the user's selected language, instead of the site language.
* Add extended support for the Members and User Role Editor plugins.
* Fix link hover and focus styles.
* Reset some more CSS styles.

### 3.1.0 ###

**Main changes:**

* Lots of accessibility improvements.
* Switch to system default fonts to match the WordPress admin area fonts.
* [Implement a PSR-3 compatible logger](https://querymonitor.com/blog/2018/07/profiling-and-logging/).
* UI improvements for mobile/touch/narrow devices.
* Various improvements to the layout of the Scripts and Styles panels.
* Prevent the "overscroll" behaviour that causes the main page to scroll when scrolling to the end of a panel.
* Remove the second table footer when filtering tables.
* Add a settings panel with information about all of the available configuration constants.

**All other changes:**

* Show a warning message in the Overview panel when a PHP error is trigger during an Ajax request.
* Display a warning when time or memory usage is above 75% of the respective limit.
* Template Part file string normalization so template parts are correctly shown on Windows systems.
* Don't output toggle links or a blank HTTP API transport if not necessary.
* Add a human readable representation of transient timeouts, and prevent some wrapping.
* Add a tear down for the capability checks collector so that cap checks performed between QM's processing and output don't break things.
* Remove the ability to sort the HTTP API Calls table. This removes a column, increasing the available horizontal space.
* Handle a bunch more known object types when displaying parameter values.
* Allow PHP errors to be filtered by level.
* Shorten the displayed names of long namespaced symbols by initialising the inner portions of the name.
* Combine the Location and Caller columns for PHP Errors to save some horizontal space.
* Don't wrap text in the PHP error type column.
* Improve the authentication cookie toggle so it dynamically reflects the current state.
* For now, force QM to use ltr text direction.
* Clarify terminology around the number of enqueued assets.
* Add fallback support for `wp_cache_get_stats()` to fetch cache stats.
* Improve the message shown when no queries are performed.
* Pluck stats from cache controllers that implement a `getStats()` method and return a nested array of stats for each server.
* Rename the `QM_HIDE_CORE_HOOKS` configuration constant to `QM_HIDE_CORE_ACTIONS`.
* Better handling of environments with unlimited execution time or memory limit. Adds a warning for both.
* When an external cache isn't in use, provide some helpful info if an appropriate extension is installed.


### 3.0.1 ###

* Add even more hardening to the JS handling to prevent problems when jQuery is broken.
* Remove the old `no-js` styles which don't work well with the new UI.
* Correct the logic for showing the `Non-Core` component filter option.
* Add another VIP function to the list of functions that call the HTTP API.
* Add an inline warning highlight to capability checks that are empty or of a non-string type.
* Add support for WordPress.com VIP Client MU plugins.
* Add support for displaying laps as part of the timing information.
* Add full support for namespaced Debug Bar add-on panels.
* Switch back to depending on `jquery` instead of `jquery-core`.
* Don't assume `php_uname()` is always callable. Add info about the host OS too.
* Reset inline height attribute when the panel is closed.

### 3.0.0 ###

* Brand new UI that resembles familiar web developer tools. Lots of related improvements and fixes.
* Introduce some basic timing functionality in a Timings panel. See #282 for usage.
* Introduce a `QM_NO_JQUERY` constant for running QM without jQuery as a dependency.
* Greater resilience to JavaScript errors.
* Allow the Scripts and Styles panel to be filtered by host name.
* Expose information about redirects that occurred in HTTP API requests.
* Expose more debugging information for HTTP API requests.
* Don't enable the Capability Checks panel by default as it's very memory intensive.
* Allow PHP errors to be silenced according to their component. See `qm/collect/php_error_levels` and `qm/collect/hide_silenced_php_errors` filters.
* Hide all file paths and stack traces behind toggles by default.
* Remove support for the AMP for WordPress plugin.
* Add associative keys to the array passed to the `qm/built-in-collectors` filter.
* Drop support for PHP 5.2.
* Generally improve performance and reduce memory usage.
