# Parse.ly

Stable tag: 3.0.0
Requires at least: 5.0  
Tested up to: 5.8  
Requires PHP: 7.1  
License: GPLv2 or later  
Tags: analytics, parse.ly, parsely, parsley  
Contributors: parsely, hbbtstar, jblz, mikeyarce, GaryJ, parsely_mike, pauargelaguet

The Parse.ly plugin facilitates real-time and historical analytics to your content through a platform designed and built for digital publishing.

## Description

Designed and built for digital publishers, Parse.ly helps you understand how your audience is connecting to your content.

Thousands of writers, editors, site managers, and technologists already use Parse.ly to understand what content draws in website visitors, and why. Using our powerful dashboards and APIs, customers build successful digital strategies that allow them to grow and engage a loyal audience.

Join industry leaders -- like Mashable, Slate, News Corp, and Conde Nast -- who already use Parse.ly to bring clarity to content, audience, and analytics.

### Features

- Get started with Parse.ly right away: the plugin automatically inserts the required metadata and JavaScript on all your published pages and posts.
- Choose what format the metadata takes, and whether logged-in users should be included in the analytics.
- If you've purchased access to the Parse.ly API, add a widget to your site with article recommendations personalized to individual users.

Feedback, suggestions, questions or concerns? Open a new [GitHub issue](https://github.com/Parsely/wp-parsely/issues) or email us at [support@parsely.com](mailto:support@parsely.com). We always want to hear from you!

## Installation

The plugin requires an active Parse.ly account. Parse.ly gives creators, marketers, and developers the tools to understand content performance, prove content value, and deliver tailored content experiences that drive meaningful results.
[Sign up for a free trial of Parse.ly](http://www.parsely.com/trial/?utm_medium=referral&utm_source=wordpress.org&utm_content=wp-parsely).

### Install the plugin from within WordPress

1. Visit the Plugins page from your WordPress dashboard and click "Add New" at the top of the page.
1. Search for "parse.ly" using the search bar on the right side.
1. Click "Install Now" to install the plugin.
1. After it's installed, click "Activate" to activate the plugin on your site.

### Install the plugin manually

1. Download the plugin from WordPress.org or get the latest release from our [Github Releases page](https://github.com/Parsely/wp-parsely/releases).
1. Unzip the downloaded archive.
1. Upload the entire `wp-parsely` folder to your `/wp-content/plugins` directory.
1. Visit the Plugins page from your WordPress dashboard and look for the newly installed Parse.ly plugin.
1. Click "Activate" to activate the plugin on your site.

## Local development

The easiest way to develop this plugin locally is by using the `wp-env` package. [It is an official WP.org package](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) that spins up a Docker-based WordPress environment for plugin development.

Having Docker running,

```
npm install

# Start the environment
npm run dev:start

# Stop the environment
npm run dev:stop
```

This will start up an environment in `localhost:8888`, running in the background.

To develop for WordPress VIP sites, we recommend using [the WordPress VIP dev-env](https://docs.wpvip.com/technical-references/vip-local-development-environment/).

## Frequently Asked Questions

### Where do I find my Site ID?

Your Site ID is likely your own site domain name (e.g., `mysite.com`). You can find this in your Parse.ly account.

### Why can't I see Parse.ly code on my post when I preview?

The code will only be placed on posts and pages which have been published in WordPress to ensure we don't track traffic generated while you're still writing a post or page.

You may also be not tracking logged-in users, via one of the settings.

### How can I edit the values passed to the JSON-LD metadata?

You can use the `wp_parsely_metadata` filter, which sends three arguments: the array of metadata, the post object, and the `parsely_options` array:

    add_filter( 'wp_parsely_metadata', 'filter_parsely_metadata', 10, 3 );
    function filter_parsely_metadata( $parsely_metadata, $post, $parsely_options ) {
        $parsely_metadata['articleSection'] = '...'; // Whatever values you want Parse.ly's Section to be.
        return $parsely_metadata;
    }

This filter can go anywhere in your codebase, provided it always gets loaded.

### Is the plugin compatible with AMP and Facebook Instant Articles?

It is! The plugin hooks into Automattic's official plugins for [AMP](https://wordpress.org/plugins/amp/) and [Facebook Instant Articles](https://wordpress.org/plugins/fb-instant-articles/).

AMP support is enabled automatically if the Automattic AMP plugin is installed

For Facebook Instant Articles support, enable "Parsely Analytics" in the "Advanced Settings" menu of the Facebook Instant Articles plugin.

### Does the plugin support dynamic tracking?

This plugin does not currently support dynamic tracking (the tracking of multiple pageviews on a single page).

Some common use-cases for dynamic tracking are slideshows or articles loaded via AJAX calls in single-page applications -- situations in which new content is loaded without a full page refresh.

Tracking these events requires manually implementing additional JavaScript above [the standard Parse.ly include](http://www.parsely.com/help/integration/basic/) that the plugin injects into your page source. Please consult [the Parse.ly documentation on dynamic tracking](https://www.parsely.com/help/integration/dynamic/) for instructions on implementing dynamic tracking, or contact Parse.ly support for additional assistance.

### Cloudflare support

If the site is running behind a Cloudflare DNS, their Rocket Loader technology will alter how JavaScript files are loaded. [A JavaScript file can be ignored by Rocket Loader](https://support.cloudflare.com/hc/en-us/articles/200169436-How-can-I-have-Rocket-Loader-ignore-specific-JavaScripts) by using `data-cfasync="false"`.

Previous versions of this plugin would mark all scripts with that tag by default. Starting in version 3.0, that behavior has become optional and scripts won't be annotated with `data-cfasync="false"`. The previous behavior can be restored by adding the following filter:

```
add_filter( 'wp_parsely_enable_cfasync_attribute', '__return_true' );
```

## Screenshots

1. The main admin screen of the Parse.ly plugin, showing some of the settings.  
   ![The main settings screen of the wp-parsely plugin](.wordpress-org/screenshot-1.png)

2. The settings for the Parse.ly Recommended Widget. Engage your visitors with predictive and personalized recommendations from Parse.ly.  
   ![The settings for the Parse.ly Recommended Widget](.wordpress-org/screenshot-2.png)
3. A view of the Parse.ly Dashboard Overview. Parse.ly offers analytics that empowers you to better understand how your content is peforming.  
   ![The Parsely Dashboard Overview](.wordpress-org/screenshot-3.png)

## Sample Parse.ly metadata

The standard Parse.ly JavaScript tracker inserted before the closing `body` tag:

    <!-- START Parse.ly Include: Standard -->

       <script data-cfasync="false" id="parsely-cfg" data-parsely-site="example.com" src="https://cdn.parsely.com/keys/example.com/p.js"></script>

    <!-- END Parse.ly Include: Standard -->

A sample `JSON-LD` structured data for a home page or section page:

    <!-- BEGIN Parse.ly 2.5.0 -->
    <script type="application/ld+json">
    {"@context":"http:\/\/schema.org","@type":"WebPage","headline":"WordPress VIP","url":"http:\/\/wpvip.com\/"}
    </script>
    <!-- END Parse.ly -->

A sample `JSON-LD` meta tag and structured data for an article or post:

    <!-- BEGIN Parse.ly 2.5.0 -->
    <script type="application/ld+json">
        {"@context":"http:\/\/schema.org","@type":"NewsArticle","mainEntityOfPage":{"@type":"WebPage","@id":"http:\/\/wpvip.com\/2021\/04\/09\/how-the-wordpress-gutenberg-block-editor-empowers-enterprise-content-creators\/"},"headline":"How the WordPress Gutenberg Block Editor Empowers Enterprise Content Creators","url":"http:\/\/wpvip.com\/2021\/04\/09\/how-the-wordpress-gutenberg-block-editor-empowers-enterprise-content-creators\/","thumbnailUrl":"https:\/\/wpvip.com\/wp-content\/uploads\/2021\/04\/ladyatdesk.png?w=120","image":{"@type":"ImageObject","url":"https:\/\/wpvip.com\/wp-content\/uploads\/2021\/04\/ladyatdesk.png?w=120"},"dateCreated":"2021-04-09T15:13:13Z","datePublished":"2021-04-09T15:13:13Z","dateModified":"2021-04-09T15:13:13Z","articleSection":"Gutenberg","author":[{"@type":"Person","name":"Sam Wendland"}],"creator":["Sam Wendland"],"publisher":{"@type":"Organization","name":"The Enterprise Content Management Platform | WordPress VIP","logo":"https:\/\/wpvip.com\/wp-content\/uploads\/2020\/11\/cropped-favicon-dark.png"},"keywords":[]}
    </script>
    <!-- END Parse.ly -->

## Changelog

See the [change log](https://github.com/parsely/wp-parsely/blob/trunk/CHANGELOG.md).
