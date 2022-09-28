# Parse.ly

Stable tag: 3.5.2  
Requires at least: 5.0  
Tested up to: 6.0.2  
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

Feedback, suggestions, questions or concerns? Open a new [GitHub issue](https://github.com/Parsely/wp-parsely/issues) or email us at [support@parsely.com](mailto:support@parsely.com). We always want to hear from you!

**NOTE:** The plugin requires an active Parse.ly account. [Sign up for a free Parse.ly demo](https://www.parsely.com/getdemo?utm_medium=referral&utm_source=wordpress.org&utm_content=wp-parsely).

### Features

Some notable features of the wp-parsely plugin are:

- Automatically inserts the Parse.ly metadata and JavaScript in all published pages and posts (supports Custom Post Types).
- [Supports Google Tag Manager, AMP, Facebook Instant Articles, Google Web Stories and Cloudflare](https://www.parse.ly/help/integration/frequently-asked-questions#third-party-and-feature-support).
- Offers the `wpParselyOnLoad` and `wpParselyOnReady` JavaScript hooks that allow advanced integrations requiring JavaScript, such as [Dynamic Tracking](https://www.parse.ly/help/integration/dynamic-tracking).
- [Supports WordPress Network (Multisite) setups](https://www.parse.ly/help/integration/frequently-asked-questions#third-party-and-feature-support).
- [Supports decoupled (headless) setups](https://www.parse.ly/help/integration/decoupled-headless-support).
- Provides a [Recommendations Block](https://www.parse.ly/help/integration/recommendations-block) that shows a list of links related to the currently viewed page. Useful for showcasing related content to visitors.
- Provides a [Content Helper](https://www.parse.ly/help/integration/content-helper) in the WordPress Editor sidebar that displays top-performing posts based on the currently edited post's tag or category. Useful for editors to see which related content is popular so they can gain insights from it.
- Provides a settings page to customize your integration. Some of the options include:
  - Output metadata as [JSON-LD](https://www.parse.ly/help/integration/jsonld) or [repeated meta tags](https://www.parse.ly/help/integration/metatags).
  - Choose whether logged-in users should be tracked.
  - Define how to track every Post Type (as Page, as Post or no tracking).
- Offers a wide range of hooks to customize the plugin's functionality even further.

### Documentation and resources

- [Plugin documentation](https://www.parse.ly/help/integration/category/wordpress).
- [Frequently asked questions](https://www.parse.ly/help/integration/wordpress#frequently-asked-questions).
- [Changelog](https://github.com/parsely/wp-parsely/blob/trunk/CHANGELOG.md).

**IMPORTANT:** If you are a [WordPress VIP](https://wpvip.com/) customer, the plugin should be enabled by using an `mu-plugins` filter. Please consult the [WordPress VIP documentation](https://docs.wpvip.com/technical-references/plugins/parse-ly/) for more details.

### Sample Parse.ly metadata

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

### Contributing

Development, code hosting and issue tracking of this plugin happens on the [wp-parsely GitHub repository](https://github.com/Parsely/wp-parsely/). Active development happens on the `develop` branch and releases are made off the `trunk` branch.

To run the plugin locally or to contribute to it, please check the instructions in the [CONTRIBUTING](https://github.com/parsely/wp-parsely/blob/trunk/CONTRIBUTING.md) file.

## Installation

The plugin requires an active Parse.ly account. [Sign up for a free demo of Parse.ly](https://www.parsely.com/getdemo?utm_medium=referral&utm_source=wordpress.org&utm_content=wp-parsely).

For more information, please visit the [installation instructions](https://www.parse.ly/help/integration/wordpress) in the official documentation. 

## Frequently Asked Questions

Please visit the [frequently asked questions](https://www.parse.ly/help/integration/frequently-asked-questions) in the official documentation.

## Changelog

Please visit the [changelog](https://github.com/parsely/wp-parsely/blob/trunk/CHANGELOG.md).

## Screenshots

1. Parse.ly plugin main settings for easy setup. For the plugin to start working, only the Site ID is needed.  
   ![The main settings screen of the wp-parsely plugin](.wordpress-org/screenshot-1.png)
2. Parse.ly plugin settings that require you to submit a website recrawl request whenever you update them.  
   ![The main settings screen of the wp-parsely plugin](.wordpress-org/screenshot-2.png)
3. Parse.ly plugin advanced settings. To be used only if instructed by Parse.ly staff.  
   ![The main settings screen of the wp-parsely plugin](.wordpress-org/screenshot-3.png)
4. The Content Helper. Provides a list of the website's most successful similar posts to the one that is currently being edited.  
   ![The settings for the Parse.ly Recommended Widget](.wordpress-org/screenshot-4.png)
5. The Recommendations Block. Showcases links to content on your site as provided by the Parse.ly /related API.  
   ![The settings for the Parse.ly Recommended Widget](.wordpress-org/screenshot-5.png)
6. A view of the Parse.ly Dashboard Overview. Parse.ly offers analytics that empowers you to better understand how your content is performing.  
   ![The Parsely Dashboard Overview](.wordpress-org/screenshot-6.png)
