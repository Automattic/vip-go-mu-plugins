# Query Monitor - The developer tools panel for WordPress

Contributors: johnbillion
Tags: debug, debug-bar, development, performance, query monitor
Tested up to: 6.5
Stable tag: 3.16.3
License: GPLv2 or later
Donate link: https://github.com/sponsors/johnbillion

Query Monitor is the developer tools panel for WordPress.

## Description

Query Monitor is the developer tools panel for WordPress. It enables debugging of database queries, PHP errors, hooks and actions, block editor blocks, enqueued scripts and stylesheets, HTTP API calls, and more.

It includes some advanced features such as debugging of Ajax calls, REST API calls, user capability checks, and full support for block themes and full site editing. It includes the ability to narrow down much of its output by plugin or theme, allowing you to quickly determine poorly performing plugins, themes, or functions.

Query Monitor focuses heavily on presenting its information in a useful manner, for example by showing aggregate database queries grouped by the plugins, themes, or functions that are responsible for them. It adds an admin toolbar menu showing an overview of the current page, with complete debugging information shown in panels once you select a menu item.

Query Monitor supports versions of WordPress up to three years old, and PHP version 7.4 or higher.

For complete information, please see [the Query Monitor website](https://querymonitor.com/).

Here's an overview of what's shown for each page load:

* Database queries, including notifications for slow, duplicate, or erroneous queries. Allows filtering by query type (`SELECT`, `UPDATE`, `DELETE`, etc), responsible component (plugin, theme, WordPress core), and calling function, and provides separate aggregate views for each.
* The template filename, the complete template hierarchy, and names of all template parts that were loaded or not loaded (for block themes and classic themes).
* PHP errors presented nicely along with their responsible component and call stack, and a visible warning in the admin toolbar.
* Usage of "Doing it Wrong" or "Deprecated" functionality in the code on your site.
* Blocks and associated properties within post content and within full site editing (FSE).
* Matched rewrite rules, associated query strings, and query vars.
* Enqueued scripts and stylesheets, along with their dependencies, dependents, and alerts for broken dependencies.
* Language settings and loaded translation files (MO files and JSON files) for each text domain.
* HTTP API requests, with response code, responsible component, and time taken, with alerts for failed or erroneous requests.
* User capability checks, along with the result and any parameters passed to the capability check.
* Environment information, including detailed information about PHP, the database, WordPress, and the web server.
* The values of all WordPress conditional functions such as `is_single()`, `is_home()`, etc.
* Transients that were updated.
* Usage of `switch_to_blog()` and `restore_current_blog()` on Multisite installations.

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

Query Monitor is private by default and always will be. It does not persistently store any of the data that it collects. It does not send data to any third party, nor does it include any third party resources. [Query Monitor's full privacy statement can be found here](https://querymonitor.com/privacy/).

### Accessibility Statement

Query Monitor aims to be fully accessible to all of its users. [Query Monitor's full accessibility statement can be found here](https://querymonitor.com/accessibility/).

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

Yes, it's actively tested and working up to PHP 8.2.

### Who can see Query Monitor's output?

By default, Query Monitor's output is only shown to Administrators on single-site installations, and Super Admins on Multisite installations.

In addition to this, you can set an authentication cookie which allows you to view Query Monitor output when you're not logged in, or when you're logged in as a user who cannot usually see Query Monitor's output. See the Settings panel for details.

### Does Query Monitor itself impact the page generation time or memory usage?

Short answer: Yes, but only a little.

Long answer: Query Monitor has a small impact on page generation time because it hooks into a few places in WordPress in the same way that other plugins do. The impact is negligible.

On pages that have an especially high number of database queries (in the hundreds), Query Monitor currently uses more memory than I would like it to. This is due to the amount of data that is captured in the stack trace for each query. I have been and will be working to continually reduce this.

### Can I prevent Query Monitor from collecting data during long-running requests?

Yes, you can call `do_action( 'qm/cease' )` to instruct Query Monitor to cease operating for the remainder of the page generation. It will detach itself from further data collection, discard any data it's collected so far, and skip the output of its information.

This is useful for long-running operations that perform a very high number of database queries, consume a lot of memory, or otherwise are of no concern to Query Monitor, for example:

* Backing up or restoring your site
* Importing or exporting a large amount of data
* Running security scans

### Are there any add-on plugins for Query Monitor?

[A list of add-on plugins for Query Monitor can be found here.](https://querymonitor.com/help/add-on-plugins/)

In addition, Query Monitor transparently supports add-ons for the Debug Bar plugin. If you have any Debug Bar add-ons installed, deactivate Debug Bar and the add-ons will show up in Query Monitor's menu.

### Where can I suggest a new feature or report a bug?

Please use [the issue tracker on Query Monitor's GitHub repo](https://github.com/johnbillion/query-monitor/issues) as it's easier to keep track of issues there, rather than on the wordpress.org support forums.

### Is Query Monitor available on Altis?

Yes, the [Altis Developer Tools](https://www.altis-dxp.com/resources/developer-docs/dev-tools/) are built on top of Query Monitor.

### Is Query Monitor available on WordPress VIP?

Yes, but a user needs to be granted the `view_query_monitor` capability to see Query Monitor even if they're an administrator. [See the WordPress VIP documentation for more details](https://docs.wpvip.com/how-tos/enable-query-monitor/).

### I'm using multiple instances of `wpdb`. How do I get my additional instances to show up in Query Monitor?

This feature was removed in version 3.12 as it was rarely used and considerably increased the maintenance burden of Query Monitor itself. Feel free to continue using version 3.11 if you need to make use of this feature.

### Can I click on stack traces to open the file in my editor?

Yes. You can enable this on the Settings panel.

### How can I report a security bug?

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team helps validate, triage, and handle any security vulnerabilities. [Report a security vulnerability here](https://patchstack.com/database/vdp/query-monitor).

### Do you accept donations?

[I am accepting sponsorships via the GitHub Sponsors program](https://github.com/sponsors/johnbillion). If you work at an agency that develops with WordPress, ask your company to provide sponsorship in order to invest in its supply chain. The tools that I maintain probably save your company time and money, and GitHub sponsorship can now be done at the organisation level.

In addition, if you like the plugin then I'd love for you to [leave a review](https://wordpress.org/support/view/plugin-reviews/query-monitor). Tell all your friends about it too!

## Changelog ##

### 3.16.3 (22 May 2024) ###

* Prevents an infinite loop when logging doing it wrong calls and deprecated calls.
* Removes a global from query-monitor.php

### 3.16.2 (22 May 2024) ###

* Fixes another issue with the PHP autoloader in 3.16.0 and 3.16.1 that was crashing some sites

### 3.16.1 (22 May 2024) ###

* Fixes an issue with the PHP autoloader in 3.16.0 that was crashing some sites

### 3.16.0 (22 April 2024) ###

* Adds full support for debugging new features in WordPress 6.5: JavaScript modules and PHP translation files

### 3.15.0 (10 November 2023) ###

* Adds [a new assertion feature via the `qm/assert` action](https://querymonitor.com/wordpress-debugging/assertions/)
* Confirms the plugin is tested up to WordPress 6.4


### 3.14.1 (21 October 2023) ###

* Improves compatibility with WordPress Playground

### 3.14.0 (18 October 2023) ###

* Corrects the port number handling when displaying URLs for scripts and styles
* Improves the `db.php` handling when activating and deactivating Query Monitor on a single site within a Multisite network, and when `DISALLOW_FILE_MODS` is in use
* Improves check for Debug Bar existence
* Identifies drop-in plugins as a specific component instead of "other"
* Simplifies some of the data structure used when logging queries
* Specifies that Query Monitor supports WordPress versions up to three years old

### 3.13.1 (15 July 2023) ###

* Avoids a fatal error if a deprecated warning is triggered early on during the bootstrap process
* Avoids a PHP warning that can be triggered during certain HTTP API requests when Curl is not in use
* Skips loading QM during the plugin update process

### 3.13.0 (9 July 2023) ###

* Adds a dedicated panel for "Doing it Wrong" and deprecated functionality usage
* Allows data in the HTTP API requests panel to be filtered by host name
* Adds a "Type" column wherever a list of hooks can show both actions and filters
* Improves various aspects of the "Editor" setting
* Increases the minimum supported version of PHP to 7.4
* Many thanks to @crstauf for the majority of the new features in this release

### 3.12.3 (17 May 2023) ###

* Improves theme template part data collection when the Gutenberg plugin is in use with a block theme
* Skips attempting to resolve a block template if the theme doesn't support block templates
* Removes the fallback to `$EZSQL_ERROR` for database query errors as it's not possible to determine if the error should be ignored

### 3.12.2 (27 April 2023) ###

* Adds the total count to the table footer of the PHP Errors panel
* Improves the destination URL for links that point to the site editor
* Implements some minor visual improvements
* Removes unreliable information about the transport for HTTP API requests
* Removes Query Monitor output from the interim login modal

### 3.12.1 (24 March 2023) ###

* Corrects some inter-panel links that point to the Queries panel and sub-panels
* Switches to `sessionStorage` for the selected table column filters so they don't persist across tabs or sessions
* Removes the "Debug Bar:" prefix on the menus for panels inherited from the Debug Bar plugin

### 3.12.0 (16 March 2023) ###

* Clarifies and improves information in the Template panel when a block theme or full site editing (FSE) is in use
* Avoids PHP warnings if a third party plugin makes unexpected changes to language file paths
* Implements some minor performance improvements
* Removes misleading information about WordPress memory limits
* Removes support for multiple instances of `wpdb` (see the FAQ for more information)

### 3.11.2 (23 February 2023) ###

* Implements various accessibility improvements
* Fixes an issue where not all admin area footer scripts were shown in the Scripts panel
* Improves output when the SQLite feature in the Performance Labs plugin is in use
* Removes QM output altogether from the Customizer
* Ensures `wp-content/db.php` from another plugin doesn't get removed when deactivating QM

### 3.11.1 (3 January 2023) ###

* Avoids a fatal error in PHP 8 when `posix_getpwuid()` or `posix_getgrgid()` doesn't return an expected value.

### Earlier versions ###

For the changelog of earlier versions, <a href="https://github.com/johnbillion/query-monitor/releases">please refer to the releases page on GitHub</a>.