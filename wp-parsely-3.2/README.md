# Parse.ly

Stable tag: 3.2.1  
Requires at least: 5.0  
Tested up to: 5.9.2  
Requires PHP: 7.1  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  
Tags: analytics, content marketing, parse.ly, parsely, parsley  
Contributors: parsely, hbbtstar, jblz, mikeyarce, GaryJ, parsely_mike, pauargelaguet, acicovic

The Parse.ly plugin facilitates real-time and historical analytics to your content through a platform designed and built for digital publishing.

## Description

Designed and built for digital publishers, Parse.ly helps you understand how your audience is connecting to your content.

Thousands of writers, editors, site managers, and technologists already use Parse.ly to understand what content draws in website visitors, and why. Using our powerful dashboards and APIs, customers build successful digital strategies that allow them to grow and engage a loyal audience.

Join industry leaders -- like Mashable, Slate, News Corp, and Conde Nast -- who already use Parse.ly to bring clarity to content, audience, and analytics.

### Features

- Get started with Parse.ly right away: the plugin automatically inserts the required metadata and JavaScript on all your published pages and posts.
- Choose what format the metadata takes, and whether logged-in users should be included in the analytics.
- Using it in a decoupled setup? Parse.ly adds metadata to the REST API output for pages, posts and optionally other object types.
- If you've purchased access to the Parse.ly API, add a widget to your site with article recommendations personalized to individual users.

Feedback, suggestions, questions or concerns? Open a new [GitHub issue](https://github.com/Parsely/wp-parsely/issues) or email us at [support@parsely.com](mailto:support@parsely.com). We always want to hear from you!

### Documentation

If you are looking for the plugin's documentation and how to set up your WordPress site with Parse.ly, take a look at [the Parse.ly integration docs](https://www.parsely.com/help/integration/wordpress).

In case you are a WordPress VIP customer, [VIP's documentation](https://docs.wpvip.com/technical-references/plugins/parse-ly/) will be also useful for you.

## Installation

The plugin requires an active Parse.ly account. [Sign up for a free demo of Parse.ly](https://www.parsely.com/getdemo?utm_medium=referral&utm_source=wordpress.org&utm_content=wp-parsely).

### Install the plugin from within WordPress

1. Visit the Plugins page from your WordPress dashboard and click "Add New" at the top of the page.
2. Search for _parse.ly_ using the search bar on the right side.
3. Click _Install Now_ to install the plugin.
4. After it's installed, click _Activate_ to activate the plugin on your site.

### Install the plugin manually

1. Download the plugin from [WordPress.org](https://wordpress.org/plugins/wp-parsely/) or get the latest release from our [Github Releases page](https://github.com/Parsely/wp-parsely/releases).
2. Unzip the downloaded archive.
3. Upload the entire `wp-parsely` folder to your `/wp-content/plugins` directory.
4. Visit the Plugins page from your WordPress dashboard and look for the newly installed Parse.ly plugin.
5. Click _Activate_ to activate the plugin on your site.

Note that this method is the recommended one for installing old versions of the plugin. Those can be downloaded from [WordPress.org](https://wordpress.org/plugins/wp-parsely/advanced/) or the GitHub Releases page.

## Local development

Development, code hosting and issue tracking of this plugin happens on the [wp-parsely GitHub repository](https://github.com/Parsely/wp-parsely/). Active development happens on the `develop` branch and releases are made off the `trunk` branch.

To run the plugin locally or to contribute to it, please check the instructions in the [CONTRIBUTING](https://github.com/parsely/wp-parsely/blob/trunk/CONTRIBUTING.md) file.

## Sample Parse.ly metadata

The standard Parse.ly JavaScript tracker inserted before the closing `body` tag:

~~~html
<script id="parsely-cfg" data-parsely-site="example.com" src="https://cdn.parsely.com/keys/example.com/p.js"></script>
~~~

A sample `JSON-LD` structured data for a home page or section page:

~~~html
<script type="application/ld+json">
{"@context":"http:\/\/schema.org","@type":"WebPage","headline":"WordPress VIP","url":"http:\/\/wpvip.com\/"}
</script>
~~~

A sample `JSON-LD` meta tag and structured data for an article or post:

~~~html
<script type="application/ld+json">
{"@context":"http:\/\/schema.org","@type":"NewsArticle","mainEntityOfPage":{"@type":"WebPage","@id":"http:\/\/wpvip.com\/2021\/04\/09\/how-the-wordpress-gutenberg-block-editor-empowers-enterprise-content-creators\/"},"headline":"How the WordPress Gutenberg Block Editor Empowers Enterprise Content Creators","url":"http:\/\/wpvip.com\/2021\/04\/09\/how-the-wordpress-gutenberg-block-editor-empowers-enterprise-content-creators\/","thumbnailUrl":"https:\/\/wpvip.com\/wp-content\/uploads\/2021\/04\/ladyatdesk.png?w=120","image":{"@type":"ImageObject","url":"https:\/\/wpvip.com\/wp-content\/uploads\/2021\/04\/ladyatdesk.png?w=120"},"dateCreated":"2021-04-09T15:13:13Z","datePublished":"2021-04-09T15:13:13Z","dateModified":"2021-04-09T15:13:13Z","articleSection":"Gutenberg","author":[{"@type":"Person","name":"Sam Wendland"}],"creator":["Sam Wendland"],"publisher":{"@type":"Organization","name":"The Enterprise Content Management Platform | WordPress VIP","logo":"https:\/\/wpvip.com\/wp-content\/uploads\/2020\/11\/cropped-favicon-dark.png"},"keywords":[]}
</script>
~~~

## The Parse.ly Recommendations Block (beta)

The plugin includes a Recommendations Block that uses the [Parse.ly API](https://www.parse.ly/help/api/recommendations#get-related) to showcase links to content on your site. More information about enabling the Recommendations Block can be found in its [documentation](https://github.com/Parsely/wp-parsely/blob/trunk/src/blocks/recommendations/README.md). This feature is currently in beta and disabled by default.

## Screenshots

1. Parse.ly plugin main settings for easy setup. For the plugin to start working, only the website ID is needed.  
   ![The main settings screen of the wp-parsely plugin](.wordpress-org/screenshot-1.png)
2. Parse.ly plugin settings that require you to submit a website recrawl request whenever you update them.  
   ![The main settings screen of the wp-parsely plugin](.wordpress-org/screenshot-2.png)
3. Parse.ly plugin advanced settings. To be used only if instructed by Parse.ly staff.  
   ![The main settings screen of the wp-parsely plugin](.wordpress-org/screenshot-3.png)
4. The settings for the Parse.ly Recommended Widget. Engage your visitors with predictive and personalized recommendations from Parse.ly.  
   ![The settings for the Parse.ly Recommended Widget](.wordpress-org/screenshot-4.png)
5. A view of the Parse.ly Dashboard Overview. Parse.ly offers analytics that empowers you to better understand how your content is peforming.  
   ![The Parsely Dashboard Overview](.wordpress-org/screenshot-5.png)

## Frequently Asked Questions

See [frequently asked questions](https://www.parse.ly/help/integration/wordpress#frequently-asked-questions) on the Parse.ly Technical Documentation.

## Changelog

See the [change log](https://github.com/parsely/wp-parsely/blob/trunk/CHANGELOG.md).
