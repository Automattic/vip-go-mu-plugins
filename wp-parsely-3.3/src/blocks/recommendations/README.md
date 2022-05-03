# Parse.ly Recommendations Block

The Recommendations Block is designed to showcase links to content on your site as provided by the [Parse.ly `/related` API endpoint](https://www.parse.ly/help/api/recommendations#get-related). You can add it in Posts, Pages, or nearly any other custom post type. The Block can also be used in Full Site Editing (FSE) mode or as a [Block-based Widget](https://wordpress.org/support/article/block-based-widgets-editor/).

> Note: In wp-parsely version 4.0.0, the Recommendations Block will completely replace the Recommended Widget. If you're using the Widget, we highly recommend migrating to the Block as soon as possible.

## Requirements

The Block is available only in WordPress 5.9 or later.

## How to use

Inside the Block Editor, add the `Parse.ly Recommendations` Block via the [standard block controls](https://wordpress.org/support/article/adding-a-new-block/) and configure its [settings](#settings) using the sidebar.

## Customizing the appearance

The Block's appearance is minimal and inherits the active theme's styling. At this moment, the Block doesn't offer any styling preferences in its settings and its appearance is meant to be manipulated using CSS.

## Settings

### Title

Change the Block's title by updating this setting. You can also make the title section completely vanish by specifying an empty value.

### Maximum Results

Specify the maximum number of recommendations to show.

> Note: In certain cases, the number of results might be lower than this setting (especially if it is set to a large number).

### Show Images

Toggle this setting to enable or disable images in the results. 

### Image Style

Use this setting to specify whether the Block should display the post's original image or the respective thumbnail provided by Parse.ly. Selecting the thumbnail option could be more performant but is not ideal for displaying large images.

> Note: If you select the `Parse.ly thumbnail` setting, the image will appear big and distorted. You should use CSS to tailor it to your needs.

### Sort Recommendations

Sort the results by score or published date. 

### Boost

Sub-sort the results by a variety of [available metrics](https://www.parse.ly/help/api/available-metrics).

### Additional CSS class(es) (Advanced section)

For styling purposes, you can specify one or more CSS classes that will be assigned to the block. 

## FAQ

### I can't find the Block.

Ensure that you're running WordPress version 5.9.0 or later, as the Block is unavailable in lower WordPress versions.

### The Block's images are of low-quality, even though the `Image style` option is set to `Original` and the original image is large.

To resolve this, you must perform the following steps in order:
1. Upgrade to [wp-parsely](https://wordpress.org/plugins/wp-parsely/) 3.3.0 or higher.
2. Contact [Parse.ly Support](https://www.parse.ly/support/) and request a recrawl, mentioning that you're experiencing an image quality issue with the Block.

Once Parse.ly has performed the recrawl, the images loaded by the Block should be correct.
