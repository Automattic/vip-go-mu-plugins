# Changelog for the Parsely WordPress plugin

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.6.1] - 2021-10-15

- Fix recommended widget not following configuration #451

## [2.6.0] - 2021-09-29

### Added

- Improve the test environment #411
- Leverage the WordPress e2e testing framework to run end-to-end tests against the plugin #360
- Add a step to the node CI test to confirm built scripts are included in the change #374
- Using npm caching on GitHub Actions #388
- Add e2e test for the plugin action link #403
- API Key: Add utility method for checking it is set #404
- Adding 3.0 upgrade notice on README #400
- Add admin warning for minimum requirements in 3.0 #408

### Changed

- Split out row action link logic #406
- Split out plugin actions links logic #401
- Integrations: Extract into separate classes #345
- Simplifying get_options function #382
- Tests: Rename final *-test.php file to *Test.php #384
- Tests: Improve the get_current_url data provider #383
- Improving reliability of get_current_url tests #398
- Testcase: Allow getMethod() to use different class #405
- Bump prettier from 2.3.2 to 2.4.1 #376
- Bump @wordpress/scripts from 17.1.0 to 18.0.1 #366

### Fixed 

- Widget: Hide the recommendation widget until the element is populated #193
- Add conditional for CPT archives and CPT term archives #328
- Fix rendering errors when rendering multiple recommendation widgets #397
- Hide admin warning on network admin #392
- Remove jQuery from Recommended Widget #385
- Change red color in admin to match wp-admin styles #386
- Remove unused default logo variable #387
- Remove unused return_personalized_json function #391


## [2.5.2] - 2021-09-17

### Changed

- Specify that browserslist should use the defaults setting when building bundles. #363
- Wrapping post list links in an opt-in filter. #369

### Fixed

- Fix notices that would appear if the plugin was set up to print repeating metas but those wouldn't exist. #370
- Fix cookie parsing. In some edge cases, a cookie that contained special characters would not be parsed correctly. #364

## [2.5.1] - 2021-08-10

### Fixed

- Load the API init script before the tracker so values are populated.
- Encode the current URL in the `uuidProfileCall` URL.

## [2.5.0] - 2021-05-17

### Added

- Refreshed contributor documentation into a new [CONTRIBUTING.md](CONTRIBUTING.md) file.
- Introduce a build step for front-end and admin page JavaScript assets which leverages the [`@wordpress/scripts` package](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/). Scripts are now separately enqueued and browser-cacheable.
- Allow for select HTML tags and attributes in the Recommended Content widget title.
- Add a "No boost" option for scores in the widget.
- Add filter `wp_parsely_post_type` to override the [type of content Parse.ly attributes to an article](https://www.parse.ly/help/integration/jsonld#distinguishing-between-posts-and-pages).
- Add support for custom post status slugs via the `wp_parsely_trackable_statuses` filter (to allow for those other than `publish` to be tracked).
- Make `$post_id` available to the `wp_parsely_permalink` filter.

### Changed

- Refactor printed markup into template "views."
- Refactor plugin entry file to perform minimal initialization and separately load the `Parsely` class file.
- Improve the README file (which populates the copy in the plugin repository page).
- Expand test coverage (PHP and JavaScript).
- Expanded string localization and consolidate into a single text domain.
- Adjust HTML heading levels for improved usability and accessibility.
- Improve accessibility of radio inputs on the admin page.
- Improve the widget user interface to make it more consistent with core styles.
- Better load Widget CSS and use plugin version for cache busting.
- Replace widget form and hide front-end output when API values are missing.
- Prevent printing of admin page CSS outside the specific admin page for this plugin.
- Switch to XHRs for `/profile` calls (instead of using JSONP).
- Remove jQuery dependency from the API and Admin scripts.
- Stop using protocol-relative URL for the tracking script.
- Register the [package at Packagist](https://packagist.org/packages/parsely/wp-parsely) for easier install via Composer.

### Fixed

- Fix the "requires a recrawl" notices to limit to specific admin page settings.
- Fix inconsistent ports in canonical URLs.

### Deprecated

- Deprecate filter `after_set_parsely_page` -- use new name `wp_parsely_metadata` instead.
- Deprecate filter `parsely_filter_insert_javascript` -- use new name `wp_parsely_load_js_tracker` instead.

## [2.4.1] - 2021-04-13

### Fixed

- Fix the version number set in the main plugin file.

## [2.4.0] - 2021-04-13

### Added

- Structured data integration tests for posts, pages, category and author archives, and home/front pages.
- License, `.editorconfig`, `.gitattributes`, `CODEOWNERS`, `CHANGELOG.md`, and other development files.
- Documentation for hooks.
- Coding standards and other linting checks.
- JS Build environment entrypoint.

### Changed

- Improve WordPress.org assets (screenshots, icons, readme).
- Switch to using GitHub Actions workflow for CI and WordPress.org deployment.
- Update scaffolded integration test files.
- Improve plugin header (support DocBlock format, add textdomain, update information, clarify license, remove to-do's).
- Separate multisite and single-site tests in CI workflow.

### Fixed

- Fix metadata for home pages, including pages of older posts.
- Fix metadata for category archives.

### Removed

- Remove Parse.ly metadata from search result pages.

## [2.3.0] - 2021-03-24

- Fix and improve Travis configuration.
- Small maintenance items: merge isset() calls, remove unnecessary typecasting, remove is_null() in favour of null comparison, un-nest nested functions, simplify ternary operators, remove unnecessary local variable, etc.
- Improve tests: split utility methods to custom test case, use more specific assertions, etc.
- Update WordPress plugin Tested Up To version.

## [2.2.1] - 2020-12-18

- Add logo to JSON LD publisher object.

## [2.2] - 2020-09-14

- Fix metadata being inserted on a 404 page.
- Add `parsely_filter_insert_javascript` filter hook.

## [2.1.3] - 2020-09-11

- Add defaults for API Secret and Wipe settings.

## [2.1.2] - 2020-07-02

- Cleanup code to conform to WordPress VIP standards.
- Add a guard against null values.

## [2.1.1] - 2020-06-08

- Fix incorrect variable name.

## [2.1] - 2020-06-05

- Update documentation.
- Extract logic for metadata construction and updating into their own methods.
- Add API Secret setting.
- Add bulk-updating of posts when posts are saved.
- Add 10-minute cron job schedule.
- Add Wipe Parsely Metadata Info setting.

## [2.0] - 2019-04-29

- Change JavaScript integration to directly load tracker bundles that are customized for your specific site ID. See https://www.parse.ly/help/integration/basic/.  
  NOTE: Sites that have custom Parse.ly video tracking configured (outside the Parse.ly WordPress plugin) for a player listed at https://www.parse.ly/help/integration/video_v2/#supported-players should contact support@parsely.com before upgrading.

## [1.14] - 2019-01-15

- Update AMP analytics implementation.
- Add ability to use a horizontal layout of the widget (for page footers).
- Add `itm` campaign parameters to widget links for tracking performance.
- Add option to use original or resized thumbnail in the widget.
- Improves handling of missing taxonomy terms and other data.
- Improve post status check.
- Cleanup code to conform to WordPress VIP standards.

## [1.13.1] - 2018-06-18

- Cleanup code to conform to WordPress VIP standards.

## [1.13] - 2018-05-24

- Make AMP integration optional.
- Add support for publisher logo information.
- Fix minor bugs.

## [1.12.5] - 2018-05-16

- Fix kissing close bracket for select tags on settings page.

## [1.12.4] - 2018-05-15

- No net changes from 1.12.3

## [1.12.3] - 2018-05-01

- Cleanup code to conform to WordPress VIP standards.

## [1.12.2] - 2018-04-27

- Cleanup code to conform to WordPress VIP standards.
- Add security fixes.
- Add Author data when on author archive.
- Fix other linting issue
- Fix CSS bug for non-thumbnail widget.
- Remove broken or un-needed CSS rules.

## [1.12.1] - 2018-01-30

- Fix archive pages having post canonicals.

## [1.12] - 2018-01-26

- Add ability to use repeated meta tags instead of ld+json tags for metadata.
- Cleanup code to conform to WordPress VIP standards.
- Fix minor bugs.

## [1.11.2] - 2017-12-19

- No net changes from 1.11.

## [1.11] - 2017-12-18

- Add ability to use Parsely API with widget.
- Add ability to track or not track custom page and post types.
- Add ability to disable JavaScript tracking.
- Fix minor bugs.

## [1.10.3] - 2017-09-21

- Update documentation.
- Amend logic for allowing logged users not to be tracked.

## [1.10.2] - 2016-10-25

- Validate `force_https_canonicals` value.
- Improve setting help text.
- Add security fix.

## [v1.10.1] - 2016-09-22

- Update documentation.
- Add conditional in case there are no custom taxonomies.

## [v1.10] - 2016-09-20

- Add ability to filter final JSON-LD output.
- Add the ability to use a custom taxonomy as tags.
- Add AMP / Facebook Instant integration with official AMP / FBIA plugins from Automattic.
- Fix bug related to HTTPS canonicals.

## [v1.9] - 2016-06-23

- Add ability to assign custom taxonomies as section.
- Fix bug related to adding section to tag field.

## [v1.8] - 2016-01-13

- Update documentation for installation and local development.
- Allow developers to adjust the tag list and the category reported for a post.
- Add support for themes to extend the reported authors.

## [v1.7] - 2014-11-19

- Use JSON-LD / schema.org for parsely-page data instead of proprietary format.
- Add support for multiple authors if using the [Co-Authors Plus plugin](https://wordpress.org/plugins/co-authors-plus/).

## [v1.6] - 2014-04-30

- Maintenance release with multiple changes needed for WordPress VIP inclusion.
- Migrate to WP Settings API.
- Various syntax changes in line with Automattic's guidelines.
- Remove the `tracker_implementation` option, plugin now uses Standard implementation for all installs.
- Update much of the copy in settings page.
- Update screenshots.

## [v1.5] - 2013-06-17

- Add support for new option - "Use Categories as Tags".
- Fix bug that caused wp-admin bar to be hidden when "Do not track authenticated in users" was selected.
- Fix WP category logic bug that failed on users with custom post types.

## [v1.4] - 2012-11-09

- Add early support for post tags.
- Fix permalink errors on category/author/tag pages.
- Add version output to both templates and settings pages.
- Rename API key to Site ID to avoid confusion.

## [v1.3] - 2012-10-03

- Add option to not track or not track authenticated users (default is to not track authenticated users).
- Remove async implementation option.
- Update API key retrieval instructions.
- Add activation/deactivation hooks.
- null categories are now set to "Uncategorized".

## [v1.2] - 2012-08-31

- Add support for using top-level categories for posts instead of the first active post the plugin finds.
- parsely-page meta tag now outputs its value using 'content' attribute instead of 'value'.
- Minor fixes to outputting to use proper WordPress functions.

## [v1.1] - 2012-07-19

- Add ability to add a prefix to content IDs.
- Ensured the plugin only uses long tags `<?php` instead of `<?`.
- Security updates to prevent HTML/JavaScript injection attacks (values are now sanitized).
- Better error checking of values for API key / implementation method.
- Fix bugs.

## [v1.0] - 2012-07-15

- Initial version.
- Add sSupport for parsely-page and JavaScript on home page and published pages and posts as well as archive pages (date/author/category/tag).

[2.6.1]: https://github.com/Parsely/wp-parsely/compare/2.6.0...2.6.1
[2.6.0]: https://github.com/Parsely/wp-parsely/compare/2.5.2...2.6.0
[2.5.2]: https://github.com/Parsely/wp-parsely/compare/2.5.1...2.5.2
[2.5.1]: https://github.com/Parsely/wp-parsely/compare/2.5.0...2.5.1
[2.5.0]: https://github.com/Parsely/wp-parsely/compare/2.4.1...2.5.0
[2.4.1]: https://github.com/Parsely/wp-parsely/compare/2.4.0...2.4.1
[2.4.0]: https://github.com/Parsely/wp-parsely/compare/2.3.0...2.4.0
[2.3.0]: https://github.com/Parsely/wp-parsely/compare/2.2.1...2.3.0
[2.2.1]: https://github.com/Parsely/wp-parsely/compare/2.2...2.2.1
[2.2]: https://github.com/Parsely/wp-parsely/compare/2.1.3...2.2
[2.1.3]: https://github.com/Parsely/wp-parsely/compare/2.1.2...2.1.3
[2.1.2]: https://github.com/Parsely/wp-parsely/compare/2.1.1...2.1.2
[2.1.1]: https://github.com/Parsely/wp-parsely/compare/2.1...2.1.1
[2.1]: https://github.com/Parsely/wp-parsely/compare/2.0...2.1
[2.0]: https://github.com/Parsely/wp-parsely/compare/1.14...2.0
[1.14]: https://github.com/Parsely/wp-parsely/compare/1.13.1...1.14
[1.13.1]: https://github.com/Parsely/wp-parsely/compare/1.13...1.13.1
[1.13]: https://github.com/Parsely/wp-parsely/compare/1.12.5...1.13
[1.12.5]: https://github.com/Parsely/wp-parsely/compare/1.12.4...1.12.5
[1.12.4]: https://github.com/Parsely/wp-parsely/compare/1.12.3...1.12.4
[1.12.3]: https://github.com/Parsely/wp-parsely/compare/1.12.2...1.12.3
[1.12.2]: https://github.com/Parsely/wp-parsely/compare/1.12.1...1.12.2
[1.12.1]: https://github.com/Parsely/wp-parsely/compare/1.12...1.12.1
[1.12]: https://github.com/Parsely/wp-parsely/compare/1.11.2...1.12
[1.11.2]: https://github.com/Parsely/wp-parsely/compare/1.11...1.11.2
[1.11]: https://github.com/Parsely/wp-parsely/compare/1.10.3...1.11
[1.10.3]: https://github.com/Parsely/wp-parsely/compare/1.10.2...1.10.3
[1.10.2]: https://github.com/Parsely/wp-parsely/compare/v1.10.1...1.10.2
[v1.10.1]: https://github.com/Parsely/wp-parsely/compare/v1.10...v1.10.1
[v1.10]: https://github.com/Parsely/wp-parsely/compare/v1.9...v1.10
[v1.9]: https://github.com/Parsely/wp-parsely/compare/v1.8...v1.9
[v1.8]: https://github.com/Parsely/wp-parsely/compare/v1.7...v1.8
[v1.7]: https://github.com/Parsely/wp-parsely/compare/v1.6...v1.7
[v1.6]: https://github.com/Parsely/wp-parsely/compare/v1.5...v1.6
[v1.5]: https://github.com/Parsely/wp-parsely/compare/v1.4...v1.5
[v1.4]: https://github.com/Parsely/wp-parsely/compare/v1.3...v1.4
[v1.3]: https://github.com/Parsely/wp-parsely/compare/v1.2...v1.3
[v1.2]: https://github.com/Parsely/wp-parsely/compare/v1.1...v1.2
[v1.1]: https://github.com/Parsely/wp-parsely/compare/v1.0...v1.1
[v1.0]: https://github.com/Parsely/wp-parsely/releases/tag/v1.0
