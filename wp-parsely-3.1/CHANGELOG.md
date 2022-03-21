# Changelog for the Parsely WordPress plugin

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.1.3] - 2022-03-17

### Fixed

- Fix rare errors when getting authors metadata. The error occurred on posts that contained malformed authors. [#722](https://github.com/Parsely/wp-parsely/pull/722)
- Improve type definitions on categories metadata generation. [#723](https://github.com/Parsely/wp-parsely/pull/723)

## [3.1.2] - 2022-02-24

### Added

- `wp_parsely_enable_admin_bar` filter. [#691](https://github.com/Parsely/wp-parsely/pull/691)

### Changed

- Don't return metadata on REST if no API key. [#688](https://github.com/Parsely/wp-parsely/pull/688)

### Fixed

- Crash for pages that wouldn't generate a permalink. [#698](https://github.com/Parsely/wp-parsely/pull/698)
- Compatibility issues when the default category wasn't `Uncategorized`. [#620](https://github.com/Parsely/wp-parsely/pull/620)
- Fixed some type safety issues. [#657](https://github.com/Parsely/wp-parsely/pull/657)
- Update outdated references in function comments. [#676](https://github.com/Parsely/wp-parsely/pull/676)
- Exclude some files & dirs from exported plugin archives. [#612](https://github.com/Parsely/wp-parsely/pull/612)
- Not installing wp-env globally on CI. [#665](https://github.com/Parsely/wp-parsely/pull/665)
- Updating documentation. [#614](https://github.com/Parsely/wp-parsely/pull/614), [#613](https://github.com/Parsely/wp-parsely/pull/613), [#616](https://github.com/Parsely/wp-parsely/pull/616), [#654](https://github.com/Parsely/wp-parsely/pull/654), [#683](https://github.com/Parsely/wp-parsely/pull/683)

## [3.1.1] - 2022-02-09

### Fixed

- Users could not create new instances of the recommended widget on WordPress 5.9. [#651](https://github.com/Parsely/wp-parsely/pull/651)
- Correct "since" annotations to 3.1.0. [#646](https://github.com/Parsely/wp-parsely/pull/646)
- Fix recommended widget e2e tests for WordPress 5.9. [#631](https://github.com/Parsely/wp-parsely/pull/631)
- Bumped dependencies. [#632](https://github.com/Parsely/wp-parsely/pull/632) [#637](https://github.com/Parsely/wp-parsely/pull/637)
  - `@wordpress/dom-ready` from 2.13.2 to 3.3.0
  - `@wordpress/babel-preset-default` from 6.4.1 to 6.5.0
  - `@wordpress/e2e-test-utils` from 5.4.10 to 6.0.0
  - `@wordpress/env` from 4.1.3 to 4.2.0
  - `@wordpress/eslint-plugin` from 9.3.0 to 10.0.0
  - `@wordpress/scripts` from 19.2.3 to 20.0.2

### Removed

- Removed unused dependency `@wordpress/i18n`. [#632](https://github.com/Parsely/wp-parsely/pull/632)
 
## [3.1.0] - 2022-01-21

The 3.1.0 release is a minor release for the plugin that does not introduce any breaking changes coming from the 3.0 branch. This version's primary focus is adding support for WordPress decoupled architectures and a revamped settings page. We have also worked hard on refining our code, testing, and delivery process.

The Parse.ly plugin now hooks into the WordPress REST API to provide content metadata in a format that's easy for a variety of client applications to consume. A `parsely` field containing the metadata is now rendered in the tracked objects (e.g., `post` and `page`). No new endpoint is introduced. This behavior can be disabled using a filter. Please refer to the plugin's README file for more details. Note that the tracking script must still be inserted manually in the decoupled front-end or otherwise loaded for your site.

### Added

- Decoupled support. [#489](https://github.com/Parsely/wp-parsely/pull/489) [#500](https://github.com/Parsely/wp-parsely/pull/500)
- Revamped wp-admin settings page, divided in sections. [#518](https://github.com/Parsely/wp-parsely/pull/518)
- Parse.ly stats button on admin bar. [#569](https://github.com/Parsely/wp-parsely/pull/569)
- Show error in settings page when duplicated tracking is selected. [#543](https://github.com/Parsely/wp-parsely/pull/543)
- Instructions for local development. [#525](https://github.com/Parsely/wp-parsely/pull/525)
- Local developer environment logs command. [#532](https://github.com/Parsely/wp-parsely/pull/532)
- Husky-based git commit hooks to enforce linting rules prior to commit. [#538](https://github.com/Parsely/wp-parsely/pull/538)
- Linting for JavaScript and CSS files. [#527](https://github.com/Parsely/wp-parsely/pull/527)
- Types to function arguments in GetCurrentUrlTest. [#504](https://github.com/Parsely/wp-parsely/pull/504)
- End-to-end test to verify if scripts are rendered in the front-end. [#528](https://github.com/Parsely/wp-parsely/pull/528)
- Concurrency to CI configuration and Composer tweaks. [#559](https://github.com/Parsely/wp-parsely/pull/559)
- Explicit dependabot reviewers on GitHub. [#526](https://github.com/Parsely/wp-parsely/pull/526)
- WordPress.org banner images. [#581](https://github.com/Parsely/wp-parsely/pull/581)
- PHPStan static analysis tool. [#590](https://github.com/Parsely/wp-parsely/pull/590)

### Changed

- Hiding _Disable AMP_ field on settings page when the AMP plugin is not enabled. [#519](https://github.com/Parsely/wp-parsely/pull/519)
- Use built-in WordPress submit button instead of custom one in the settings page. [#513](https://github.com/Parsely/wp-parsely/pull/513)
- Improved wp-admin settings page help texts. [#552](https://github.com/Parsely/wp-parsely/pull/552)
- `@wordpress/scripts` bumped from 19.2.1 to 19.2.3. [#503](https://github.com/Parsely/wp-parsely/pull/503) [#603](https://github.com/Parsely/wp-parsely/pull/603)
- `prettier` bumped from 2.4.1 to 2.5.0. [#509](https://github.com/Parsely/wp-parsely/pull/509)
- `concurrently` bumped from 6.4.0 to 6.5.1. [#551](https://github.com/Parsely/wp-parsely/pull/551)
- Ubuntu bumped from 18.04 to 20.04 on CI tests. [#445](https://github.com/Parsely/wp-parsely/pull/445)
- Unit and Integration tests run in random order. [#511](https://github.com/Parsely/wp-parsely/pull/511)
- Correct Parse.ly spelling in tests comments. [#561](https://github.com/Parsely/wp-parsely/pull/561)
- Minor amendments on the documentation. [#514](https://github.com/Parsely/wp-parsely/pull/514)
- Updated release process guidelines. [#567](https://github.com/Parsely/wp-parsely/pull/567)
- Removed checkboxes from GitHub's PR template. [#512](https://github.com/Parsely/wp-parsely/pull/512)
- Improved JS scripts integration tests. [#557](https://github.com/Parsely/wp-parsely/pull/557)
- Source code linting violations (PHPCS with `--serverity=1`). [#544](https://github.com/Parsely/wp-parsely/pull/544)
- WordPress.org screenshots for settings page. [#574](https://github.com/Parsely/wp-parsely/pull/574)

### Fixed

- Incorrect type errors. [#607](https://github.com/Parsely/wp-parsely/pull/607)
- Undefined index error on settings page. [#536](https://github.com/Parsely/wp-parsely/pull/536)
- Source the correct asset for the Recommendations Widget. [#545](https://github.com/Parsely/wp-parsely/pull/545)
- End-to-end tests in CI (GitHub Actions). [#521](https://github.com/Parsely/wp-parsely/pull/521)

### Removed

- Plugin version number being printed in front-end's HTML source code. [#502](https://github.com/Parsely/wp-parsely/pull/502)
- Custom CSS on wp-admin settings page. [#496](https://github.com/Parsely/wp-parsely/pull/496)
- `migrate_old_fields` private function from Recommended Widget. [#599](https://github.com/Parsely/wp-parsely/pull/599)
- PHP 8.2 from CI tests. [#523](https://github.com/Parsely/wp-parsely/pull/523)
- Custom end-to-end Docker image. [#524] (https://github.com/Parsely/wp-parsely/pull/524)

## [3.0.4] - 2022-01-17

### Changed

- Changed plugin loading functions from anonymous to named functions. [#595](https://github.com/Parsely/wp-parsely/pull/595)

## [3.0.3] - 2022-01-12

### Fixed

- [Fixed a fatal error](https://github.com/Parsely/wp-parsely/issues/587) when requesting metadata for a post without categories and `categories as tags` enabled. [#588](https://github.com/Parsely/wp-parsely/pull/588)

## [3.0.2] - 2022-01-05

### Fixed

- [Properly render the post modified date metadata](https://github.com/Parsely/wp-parsely/issues/558) & Fix a [fatal error](https://github.com/Parsely/wp-parsely/issues/562) caused by an unexpected data type [#560](https://github.com/Parsely/wp-parsely/pull/560)

## [3.0.1] - 2021-12-17

### Fixed

- Fix metadata on password protected posts [#547](https://github.com/Parsely/wp-parsely/pull/547)

## [3.0.0] - 2021-12-15

## Important information about this release

wp-parsely 3.0.0 is a major release of the Parse.ly WordPress plugin. The major version bump is because we are introducing a number of breaking changes that have allowed us to modernize the codebase and make future features easier to implement.

The biggest breaking change is the new minimum requirements for running the plugin. You now need PHP 7.1 or newer and WordPress 5.0 or newer. If you are running one of those old versions, you shouldn't get the update option on your WordPress admin.

If you are using the plugin without any code-level customizations (for instance, calling the plugin's functions or methods or hooking in the plugin's WordPress hooks), this update should be seamless and everything should keep operating normally. The plugin's way of working is still fundamentally the same. If you are using those customizations, we recommend you going through the detailed changelog to see if they affect you. In most of the cases, only trivial changes will be required to make your code work.

### Added

- Namespaces to files. [#430](https://github.com/Parsely/wp-parsely/pull/430) [#475](https://github.com/Parsely/wp-parsely/pull/475) [#477](https://github.com/Parsely/wp-parsely/pull/477)
  - Now all functions and classes are under the `Parsely` namespace, or a child namespace of that e.g. `Parsely\Parsely` or `Parsely\UI\Recommended_Widget`. If your code is calling a wp-parsely function (directly, or as a hook callback) without the namespace, then you'll need to update that call.
- Strict typing (`strict_types=1`) to all files in the codebase [#420](https://github.com/Parsely/wp-parsely/pull/420).
  - Passing a value to a function in wp-parsely with an incorrect type will now raise an error.
- Type declarations have been added to function returns [#429](https://github.com/Parsely/wp-parsely/pull/429) and arguments [#455](https://github.com/Parsely/wp-parsely/pull/455).
- `wp_parsely_should_insert_metadata` filter. [#440](https://github.com/Parsely/wp-parsely/pull/440)
  - The filter controls whether the Parse.ly metadata should be inserted in the page's HTML. By default, the meta tags are rendered (the filter returns `true`).
- `wp_parsely_enable_cfasync_tag` filter. [#473](https://github.com/Parsely/wp-parsely/pull/473).
  - The Cloudflare `cfasync` attributes are now not rendered by default, but they can be enabled by returning `true` to this filter.
- WordPress plugin uninstall script. [#444](https://github.com/Parsely/wp-parsely/pull/444)
  - When the plugin is uninstalled, the options will be removed from the database. Deactivating the plugin will not cause the options to be deleted.
- `npm run dev:start` and `npm run dev:stop` commands to run the plugin locally for development purposes. [#493](https://github.com/Parsely/wp-parsely/pull/493)
- E2E test for recommended widget. [#434](https://github.com/Parsely/wp-parsely/pull/434)
- JavaScript code-scanning [#453](https://github.com/Parsely/wp-parsely/pull/453)

### Changed

- Minimum PHP and WP versions required to run the plugin are now 7.1 (from 5.6) and 5.0 from (4.0), respectively. [#416](https://github.com/Parsely/wp-parsely/pull/416)
- The development Node JS version has been bumped from 14 to 16.
- Extract logic from `class-parsely.php` file:
  - Extract admin warning to `Parsely\UI\Admin_Warning`. [#468](https://github.com/Parsely/wp-parsely/pull/468)
  - Extract tracker logic to `Parsely\Scripts` [#478](https://github.com/Parsely/wp-parsely/pull/478)
  - Extract settings page to `Parsely\UI\Settings_Page`. [#467](https://github.com/Parsely/wp-parsely/pull/467)
- Rename `Parsely_Recommended_Widget` class to `Parsely\UI\Recommended_Widget`.
- Rename methods in `Parsely\Scripts` class [#481](https://github.com/Parsely/wp-parsely/pull/481):
  - `register_js()` to `register_scripts()`.
  - `load_js_api()` to `enqueue_js_api()`.
  - `load_js_tracker()` to `enqueue_js_tracker()`.
- Move Parse.ly settings file to `views/parsely-settings.php`. [#459](https://github.com/Parsely/wp-parsely/pull/459)
- _Open on Parse.ly_ links are displayed by default. [#433](https://github.com/Parsely/wp-parsely/pull/433)
  - To disable the feature, the `wp_parsely_enable_row_action_links` filter must return `false`.
- `Parsely::get_current_url()` default value for argument `string $parsely_type` changed from `nonpost` to `non-post`. [#447](https://github.com/Parsely/wp-parsely/pull/447)
  - This change has been done to better align with Parse.ly's backend.
- Enqueue scripts with theme independent hook. [#458](https://github.com/Parsely/wp-parsely/pull/458)
  - The JavaScript scripts are now enqueued at the `wp_enqueue_scripts` hook instead of `wp_footer`.
- Replace multi-select fields with checkboxes on the settings page. [#482](https://github.com/Parsely/wp-parsely/pull/482)
  - Existing selections will be retained.
- Made class members private [#486](https://github.com/Parsely/wp-parsely/pull/486):
  - `Parsely\Integrations\Facebook_Instant_Articles`: `REGISTRY_IDENTIFIER`, `REGISTRY_DISPLAY_NAME`, `get_embed_code()`.
  - `Parsely\UI\Recommended_Widget`: `get_api_url()`.
- Tests: Specify `coverage: none` where it is not needed. [#419](https://github.com/Parsely/wp-parsely/pull/419)
- Bump @wordpress/e2e-test-utils from 5.4.3 to 5.4.8. [#492](https://github.com/Parsely/wp-parsely/pull/492)
- Bump @wordpress/scripts from 18.0.1 to 19.1.0. [#480](https://github.com/Parsely/wp-parsely/pull/480)
- Bump @wordpress/eslint-plugin from 9.2.0 to 9.3.0. [#490](https://github.com/Parsely/wp-parsely/pull/490)

### Fixed

- Fix missing translation support for Yes and No labels in the settings page. [#463](https://github.com/Parsely/wp-parsely/pull/463)
- Avoid making duplicate calls to Parse.ly API on the Recommended Widget's front-end. [#460](https://github.com/Parsely/wp-parsely/pull/460)
- Fix JS string translation in settings page. [#462](https://github.com/Parsely/wp-parsely/pull/462)
- Consistent return types on `update_metadata_endpoint`. [#446](https://github.com/Parsely/wp-parsely/pull/446)
  - The function used to return different return types, now it always returns `void`.
- Consistent return type on `insert_parsely_page`. [#443](https://github.com/Parsely/wp-parsely/pull/443)
  - The function used to return `string|null|array`, now it returns `void`.
- Fixed fatal error when the option in the database was corrupted. [#540](https://github.com/Parsely/wp-parsely/pull/540)
- Tests: Stop using deprecated `setMethods()` method. [#427](https://github.com/Parsely/wp-parsely/pull/427)
- e2e tests: fix watch command. [#476](https://github.com/Parsely/wp-parsely/pull/476)
- Fix non-working README code example. [#439](https://github.com/Parsely/wp-parsely/pull/439)

### Removed

- Previously deprecated filter `after_set_parsely_page`. [#436](https://github.com/Parsely/wp-parsely/pull/436)
  - Use `wp_parsely_metadata` instead.
- Previously deprecated filter `parsely_filter_insert_javascript`. [#437](https://github.com/Parsely/wp-parsely/pull/437)
  - Use `wp_parsely_load_js_tracker` instead.
- `post_has_viewable_type` function. [#417](https://github.com/Parsely/wp-parsely/pull/417)
  - Use `is_post_viewable` instead. The `post_has_viewable_type` function was only added to support older versions of WordPress.
- Custom Parse.ly load text domain. [#457](https://github.com/Parsely/wp-parsely/pull/457)
  - Since the plugin now supports versions of WordPress that load custom text domains automatically, the plugins doesn't have to explicitly load the text domain itself.
- Empty functions for admin settings. [#456](https://github.com/Parsely/wp-parsely/pull/456)
  - The callbacks were never utilised.
- Redundant code coverage annotations. [#469](https://github.com/Parsely/wp-parsely/pull/469)
- Old init Python script. [#441](https://github.com/Parsely/wp-parsely/pull/441)
- "Add admin warning for minimum requirements in 3.0" notice. [#424](https://github.com/Parsely/wp-parsely/pull/424)
  - This was only added in the previous version of the plugin.
- Upgrade README notice. [#470](https://github.com/Parsely/wp-parsely/pull/470)

## [2.6.1] - 2021-10-15

### Fixed

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
- Add support for parsely-page and JavaScript on home page and published pages and posts as well as archive pages (date/author/category/tag).

[3.1.3]: https://github.com/Parsely/wp-parsely/compare/3.1.2...3.1.3
[3.1.2]: https://github.com/Parsely/wp-parsely/compare/3.1.1...3.1.2
[3.1.1]: https://github.com/Parsely/wp-parsely/compare/3.1.0...3.1.1
[3.1.0]: https://github.com/Parsely/wp-parsely/compare/3.0.4...3.1.0
[3.0.4]: https://github.com/Parsely/wp-parsely/compare/3.0.3...3.0.4
[3.0.3]: https://github.com/Parsely/wp-parsely/compare/3.0.2...3.0.3
[3.0.2]: https://github.com/Parsely/wp-parsely/compare/3.0.1...3.0.2
[3.0.1]: https://github.com/Parsely/wp-parsely/compare/3.0.0...3.0.1
[3.0.0]: https://github.com/Parsely/wp-parsely/compare/2.6.1...3.0.0
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
