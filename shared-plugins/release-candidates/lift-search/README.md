Lift: Search for WordPress
=================
Contributors: voceplatforms  
Tags: search, cloudsearch, amazon, aws  
Requires at least: 3.4.2  
Tested up to: 3.6.1  
Stable tag: 1.9.1  
License: GPLv2 or later  
License URI: http://www.gnu.org/licenses/gpl-2.0.html  


## Description

Improves WordPress search using Amazon CloudSearch.

Lift leverages the search index power of Amazon CloudSearch to improve your
 WordPress-powered site’s search experience. Learn more at: 
[getliftsearch.com](http://getliftsearch.com/)

**Minimum requirements:**

* WordPress Version 3.4.2
* PHP Version 5.3
* Amazon Web Services account with CloudSearch enabled

## Installation

For full documentation see 
[getliftsearch.com/documentation/](http://getliftsearch.com/documentation/)

### As standard plugin:
> See [Installing Plugins](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins).

**Minimum requirements:**

* WordPress Version 3.4.2
* PHP Version 5.3
* Amazon Web Services account with CloudSearch enabled

1. Upload the `lift-search` directory to the `/wp-content/plugins/` directory

2. Activate the plugin through the 'Plugins' menu in WordPress

3. Enter your Amazon Access Key ID and Secret Access Key.

4. Click "Save Save Amazon Keys." If the test fails, check that both of 
your keys are entered correctly and that you are connected to Amazon. 

5. Enter a Search Domain Name. This must be a unique string to your AWS account.
The domain name string can only contain the following characters: a-z (lowercase),
0-9, and - (hyphen). Uppercase letters and underscores are not allowed. 
The string has a max length of 28 characters.

6. Click "Save Domain Name". Lift will create the new domain for you.  If the 
domain already exist, Lift will confirm if you would like to override the existing 
domain before applying it's schema to the existing domain.

7. Lift will display a progress screen while your new domain is setup.  Once
complete, you will be taken to the Lift Dashboard.


## Frequently Asked Questions

* **What are the requirements to use this plugin?**
	* *WordPress Version 3.4.2*
	* *PHP Version 5.3*
	* *Amazon Web Services account with CloudSearch enabled*

* **Does Lift support WordPress multisite?**
	* *Multisite is supported with each site in the network having it's own search domain. Due to this, searching across sites in a network is not supported, however, may be added at a later date if there is interest.*

* **How much does Lift cost?**
	* *There is no charge for the plugin. The only charges you incur are for usage of Amazon CloudSearch. You can [learn more](http://aws.amazon.com/cloudsearch/pricing/) about expected costs at Amazon's CloudSearch site.*

* **Does Lift support languages other than English?**
	* *Currently, Amazon CloudSearch only supports indexing documents in English. Once other languages are supported, Lift will be updated. Also, a future update will add li8n support for the setup and status pages.*

* **How do I set up Google Analytics to track searches?**
	* *Since Lift hooks in to the standard WordPress search, if you are already tracking searches through Google Analytics you don't need to do anything. If you would like to know how to do this, see the [Google Analytics docs](http://www.google.com/url?q=http%3A%2F%2Fsupport.google.com%2Fanalytics%2Fbin%2Fanswer.py%3Fhl%3Den%26answer%3D1012264). The Query Parameter to enter (step #8) is "s".*

* **What index fields are used when Lift configures a new search domain?**
	* *The index fields are set as follows:*

| Field                    |   Type    |      Facet      |  Result  |  Search         |
|--------------------------|:---------:|:---------------:|:--------:|:---------------:|
| blog_id                  |  uint     |  Yes (default)  |   Yes    |  Yes (default)  |
| site_id                  |  uint     |  Yes (default)  |   Yes    |  Yes (default)  |
| id                       |  uint     |  Yes (default)  |   No     |  Yes (default)  |
| post_author              |  uint     |  Yes (default)  |   No     |  Yes (default)  |
| post_author_name         |  text     |  No             |   Yes    |  Yes (default)  |
| taxonomy_category_id     |  literal  |  Yes            |   No     |  No             |
| taxonomy_category_label  |  text     |  No             |   No     |  Yes (default)  |
| post_content             |  text     |  No             |   No     |  Yes (default)  |
| post_date_gmt            |  uint     |  Yes (default)  |   No     |  Yes (default)  |
| post_status              |  literal  |  Yes            |   No     |  No             |
| post_title               |  text     |  No             |   Yes    |  Yes (default)  |
| post_type                |  literal  |  Yes            |   No     |  Yes            |
| taxonomy_post_tag_id     |  literal  |  Yes            |   No     |  No             |
| taxonomy_post_tag_label  |  text     |  No             |   No     |  Yes (default)  |

* **Which post types are indexed by default? How do I modify which post types are indexed?**
	* *By default, posts and pages are indexed. To modify this, use the `lift_indexed_post_types` filter which is an array of post types to index.*

## Changelog
** 1.9.1 **
* Fixed built in facet option for custom taxonomy fields.
* Added automatic registration of watcher for custom taxonomy fields.

** 1.9.0 **
* Added 'lift_form_fields_html' filter to allow adding/prefixing form with custom html

** 1.8.2 ** 
* Bug Fix: Correctly applied the 'lift_search_form' filter.

** 1.8.1 **
* Fixing failed merge cleanup

**1.8**
* Cleanup of handling around *LiftField classes.
* Implemented a TextPostMetaTextField class to simplify adding text fields in post meta.

**1.7**
* Enhancement: Allow for other region options for CloudSearch domain
* Enhancement: Adding PHP version check on activation
* Bug Fix: Add loading animation on setup pages while loading
* Bug Fix: Cross domain issue loading templates from other domains with WP VIP
* Bug Fix: Fixed clear errors button

**1.6**
* Enhancement: Created extendable classes to simplify adding new fields and filters.
* Bug Fix: Made sure admin nag only shows for users who can access the settings.

**1.5.2**
* Bug Fix: Fixed unneeded ajax calls when logging is disabled.

**1.5.1**
* Bug Fix: Fixed bug with setting endpoint for queue all functionality.
* Bug Fix: Fixed bug with filtering out auto-draft post_status

**1.5**
* Improvement: Redesigned admin management pages with live updates.
* Refactor: Improved error bubbling from API.
* Security: Added missing XSS checks in admin.

**1.4.1**
* Bug Fix: Added back missing date filter

**1.4**
* Bug Fix: Fixed stability issues around initial setup and using an already existing domain.
* Bug Fix: Fixed issue handling -1 posts_per_page parameter.
* Improvement: added site ID and blog ID to allow MS sites to share a single domain.
* Refactor: Improved error response handling from CloudSearch.
* Refactor: Cleaned up the configuration API and schema updating.

**1.3**
* Bug Fix: Fixed scope of Lift_Search references in anonymous function callbacks within crons.
* Bug Fix: Fixed override filter and set the front-end search form to redirect only on new searches.
* Bug Fix: Fixed issue with author name, category, and post_tag data being excluded from documents.
* Refactor: Renamed logging tables to errors.
* Refactor: Added/Removed fields from default schema to give better future flexibility.
* Refactor: Adjusted post_status handling to be match of WP 3.5 logic.

**1.2**
* Bug Fix: Fixed bug where some fields would be deleted from AWS after update.
* Bug Fix: Fixed issue with ALTERNATE_CRON compatibility.
* Bug Fix: Fixed initialization of queuing all posts after setup.
* Bug Fix: Fixed post status handling when searching from the wp-admin.
* Refactor: Added un-install hooks for cleanup after deactivation.
* Refactor: Performance tweaks.
* Refactor: Removed references to WP_PLUGIN_DIR for more flexible installations
* Refactor: Updated storage for queued updates.
* Refactor: Added ability to disable voce-error-logging integration.

**1.1**
* UI: `lift_search_form()` now duplicates the standard `get_search_form()`
markup to play nicer with themes.
* UI: Show the filtered term as the dropdown labels for filters and highlight.
Clean up terms on filter labels. Make Relevancy the default sorting.
* UI: Filters now work when more than one search form is present in a page.
* Refactor: rename filters. `lift_default_fields` to `lift_filters_default_fields`, `lift-form-field-objects` to `lift_filters_form_field_objects`, `lift_form_html` to `lift_search_form`
* Refactor: `Cloud_Config` class to be independent.
* Refactor: Calls to `Cloud_Config_Request::__make_request()` can now override key
flattening.


**1.0.1**
* Fix CloudSearch capitalization.
* Refactor error logging.

**1.0**
* Initial release.