Taxonomy Images
===============

A WordPress plugin that enables you to associate images from your media library to categories, tags and custom taxonomies. For usage instructions please view the [screencast](http://screenr.com/zMx).


Displaying Your Images in Your Theme
------------------------------------

There are a few filters that you can use in your theme to display the image associations created by this plugin. Please read below for detailed information.


Display a single image representing the term archive
----------------------------------------------------

The following filter will display the image associated with the term asked for in the query string of the url. This filter only works in views that naturally use templates like category.php, tag.php taxonomy.php and all of their derivatives. Please read about [template hierarchy](http://codex.wordpress.org/Template_Hierarchy) for more information about these templates. The simplest use of this filter looks like:

    print apply_filters( 'taxonomy-images-queried-term-image', '' );

This code will generate and print an image tag. It's output can be modifed by passig an optional third parameter to apply filters. This parameter is an array and the following keys may be set:

* __after__ (string) - Text to append to the image's HTML.

* __attr__ (array) - Key/value pairs representing the attributes of the img tag. Available options include: alt, class, src and title. This array will be passed as the fourth parameter to WordPress core function wp_get_attachment_image() without modification.

* __before__ (string) - Text to prepend to the image's HTML.

* __image_size__ (string) - May be any image size registered with WordPress. If no image size is specified, 'thumbnail' will be used as a default value. In the event that an unregistered size is specified, this filter will return an empty string.

Here's an example of what a fully customized version of this filter might look like:

    print apply_filters( 'taxonomy-images-queried-term-image', '', array(
        'after' => '</div>'
    'attr' => array(
        'alt'   => 'Custom alternative text',
        'class' => 'my-class-list bunnies turtles',
        'src'   => 'this-is-where-the-image-lives.png',
        'title' => 'Custom Title',
        ),
    'before' => '<div id="my-custom-div">',
    'image_size' => 'medium',
    ) );


Similar functionality
---------------------

If you just need to get the database ID for the image, you may want to use:

    $image_id = apply_filters( 'taxonomy-images-queried-term-image-id', 0 );

If you need to get the full object of the image, you may want to use:

    $image = apply_filters( 'taxonomy-images-queried-term-image-object', '' );

If you need to get the url to the image, you may want to use the following:

    $image_url = apply_filters( 'taxonomy-images-queried-term-image-url', '' );

You can specify the size of the image in an option third parameter:

    $image_url = apply_filters( 'taxonomy-images-queried-term-image-url', '', array(
        'image_size' => 'medium'
        ) );


If you need data about the image, you may want to use:

    $image_data = apply_filters( 'taxonomy-images-queried-term-image-data', '' );

You can specify the size of the image in an option third parameter:


    $image_data = apply_filters( 'taxonomy-images-queried-term-image-data', '', array(
        'image_size' => 'medium'
        ) );

List term images associated with a post object
----------------------------------------------

When a post is being displayed you may want to display all of the images associated with all of the terms that are associated with the post (a mouthful? Yes indeed!). The `taxonomy-images-list-the-terms` filter does this. Here's what it looks like in its simplest form:

    print apply_filters( 'taxonomy-images-list-the-terms', '' );

This filter accepts an optional third parameter that you can use to customize its output. It is an array which recognizes the following keys:

* __after__ (string) - Text to append to the output. Default value is a closing unordered list element.

* __after_image__ (string) - Text to append to each image. Default value is a closing list-item element.

* __before__ (string) - Text to prepend to the output. Default value is an open unordered list element with an class attribute of "taxonomy-images-the-terms".

* __before_image__ (string) - Text to prepend to each image. Default value is an open list-item element.

* __image_size__ (string) - Any registered image size. Values will vary from installation to installation. Image sizes defined in core include: "thumbnail", "medium" and "large". "Fullsize" may also be used to get the unmodified image that was uploaded. Defaults to "thumbnail".
 
* __post_id__ (int) - The post to retrieve terms from. Defaults to the ID property of the global $post object.
 
* __taxonomy__ (string) - Name of a registered taxonomy to return terms from. Defaults to "category".

Here's an example of what a fully customized version of this filter might look like:

    print apply_filters( 'taxonomy-images-list-the-terms', '', array(
        'after'        => '</div>',
        'after_image'  => '</span>',
        'before'       => '<div class="my-custom-class-name">',
        'before_image' => '<span>',
        'image_size'   => 'detail',
        'post_id'      => 1234,
        'taxonomy'     => 'post_tag',
        ) );

Working with all terms of a given taxonomy
------------------------------------------

You will want to use the 'taxonomy-images-get-terms' filter. This filter is basically a wrapper for WordPress core function [get_terms()](http://codex.wordpress.org/Function_Reference/get_terms). It will return an array of enhanced term objects: each term object will have a custom property named image_id which is an integer representing the database ID of the image associated with the term. This filter can be used to create custom lists of terms. Here's what it's default useage looks like:

    $terms = apply_filters( 'taxonomy-images-get-terms', '' );

Here is what php's print_r() function may return:

    Array
    (
    [0] => stdClass Object
        (
            [term_id] => 8
            [name] => Pirate
            [slug] => pirate
            [term_group] => 0
            [term_taxonomy_id] => 8
            [taxonomy] => category
            [description] => Pirates live in the ocean and ride around on boats.
            [parent] => 0
            [count] => 1
            [image_id] => 44
        )
)

As you can see, all of the goodness of get_terms() is there with an added bonus: the image_id parameter!

This filter recognizes an optional third parameter which is an array of arguments that can be used to modify its output:

* __cache_images__ (bool) If this value is true all assocaite images will be queried for and cached for later use in various template tags. If it is set to false, this query will be suppressed. Do not set this value to false unless you have a really good reason for doing so :) Default value is true.

* __having_images__ (bool) If this value is true then only terms that have associated images will be returned. Setting it to false will return all terms. Default value is true.

* __taxonomy__ (string) Name of a registered taxonomy to return terms from. Multiple taxonomies may be specified by separating each name by a comma. Defaults to "category".

* __term_args__ (array) Arguments to pass to [get_terms()](http://codex.wordpress.org/Function_Reference/get_terms) as the second parameter. Default value is an empty array.

Here's and example of a simple custom loop that you can make to display all term images:

    $terms = apply_filters( 'taxonomy-images-get-terms', '' );
    if ( ! empty( $terms ) ) {
        print '<ul>';
        foreach( (array) $terms as $term ) {
            print '<li><a href="' . esc_url( get_term_link( $term, $term->taxonomy ) ) . '">' . wp_get_attachment_image( $term->image_id, 'detail' ) . '</li>';
        }
        print '</ul>';
    }


Support
-------

If you have questions about integrating this plugin into your site, please [add a new thread to the WordPress Support Forum](http://wordpress.org/tags/taxonomy-images?forum_id=10#postform). I try to answer these, but I may not always be able to. In the event that I cannot there may be someone else who can help.


Bugs, Suggestions
-----------------

Development of this plugin is hosted in a public repository on [Github](https://github.com/mfields/Taxonomy-Images). If you find a bug in this plugin or have a suggestion to make it better, please [create a new issue](https://github.com/mfields/Taxonomy-Images/issues/new)


Hook it up yo!
--------------

If you have fallen in love with this plugin and would not be able to sleep without helping out in some way, please see the following list of ways that you can _hook it up!_:

* __Rate it!__ - Use the star tool on the right-hand sidebar of the [homepage](http://wordpress.org/extend/plugins/taxonomy-images/).

* __Let me know if it works__ - Use the _Compatibility_ widget on the [homepage](http://wordpress.org/extend/plugins/taxonomy-images/) to let everyone know that the current version works with your version of WordPress.

* __Do you Twitter?__ Help promote by using this shortlink: [http://bit.ly/taxonomy-images](http://bit.ly/taxonomy-images)

* __Are you a writer?__ Help promote by writing an article on your website about this plugin.

* __Are you Super-Wicked-Awesome?__ If so, you can always [make a donation](http://wordpress.mfields.org/donate/).


Need More Taxonomy Plugins?
---------------------------

I've released a handfull of plugins dealing with taxonomies. Please see my [plugin page](http://wordpress.org/extend/plugins/profile/mfields) for more info.


Installation
------------

1. Download
1. Unzip the package and upload to your /wp-content/plugins/ directory.
1. Log into WordPress and navigate to the "Plugins" panel.
1. Activate the plugin.
1. Click the "Taxonomy Images" link under the Settings section in the admin menu. There you can select the taxonomies that you would like to add image support for.


Changelog
---------

__0.7.3__

* Fixed the delete image button on edit-terms.php.
* Better escaping.
* Introduced pot file and languages directory.

__0.7.2__

* Return data for fulsize images in archive views. [See this thread](http://wordpress.org/support/topic/image-size-full).

__0.7.1__

* Remove unused link code which is throwing an error when no taxonomies support images.

__0.7__

* No longer breaks display of the [Better Plugin Compatibility Control](http://wordpress.org/extend/plugins/better-plugin-compatibility-control/) plugin.
* Created a custom filter interface for plugin and theme integration.
* Lots of inline documentation added.
* Added custom notices if plugin is used in an unsupported way.
* No notices generated by PHP or WordPress.
* Deprecated function calls removed.
* Security updates.
* All strings are now internationalized.
* Add image to term functionality mimics "Add Featured Image".
* Taxonomy modal button now available in search + upload states.
* Image interface has been added to single term edit screen.
* Users can now choose which taxonomys have image support.
* All functions are now private.
* Shortcode deprecated.
* All global variables and constants have been removed or deprecated.

__0.6__

* Never released.
* Completely recoded.

__0.5__

* __UPDATE:__ Direct link to upload new files from edit-tag.php has been introduced.
* __UPDATE:__ Ability to create an image/term association immediately after upload has been introduced.
* __UPDATE:__ Users can now delete image/term associations.
* __UPDATE:__ Created standalone javascript files - removed inline scripts.
* __UPDATE:__ Obsesive compulsive syntax modifications.
* __UPDATE:__ Localization for strings - still need to "fine-tooth-comb" this.
* __UPDATE:__ Removed all debug functions.

__0.4.4__

* __BUGFIX:__ get_image_html() Now populates the image's alt attribute with appropriate data. Props to [jaygoldman](http://wordpress.org/support/profile/jaygoldman).

__0.4.3__

* __UPDATE:__ Support for WordPress 3.0 has been added. Support for all beta versions of 3.0 has been dropped.
* __COMPAT:__ Removed use of deprecated function is_taxonomy() - props to [anointed](http://profiles.wordpress.org/users/anointed).
* __COMPAT:__ Included a definition for taxonomy_exists() function for backwards compatibility with 2.9 branch. This function is new in WordPress version 3.0.

__0.4.2__

* __UPDATE:__ Changed button name from "Category" to "Taxonomy".
* __UPDATE:__ Support for 2.9 branch has been added again.

__0.4.1__

* __UPDATE:__ Added support for dynamic taxonomy hooks for _tag_row()
* __BROKEN:__ Support for 2.9 branch has been temporarily removed.

__0.4__

* __BUGFIX:__ get_thumb() now returns the fullsize url if there is no appropriate intermediate image.
* __UPDATE:__ Added "taxonomy_images_shortcode".

__0.3__

* __COMPAT:__ Changed the firing order of every hook untilizing the 'category_rows' method to 15. This allows this plugin to be compatible with [Reveal IDs for WP Admin](http://wordpress.org/extend/plugins/reveal-ids-for-wp-admin-25/). Thanks to [Peter Kahoun](http://profiles.wordpress.org/kahi/)
* __COMPAT:__ Added Version check for PHP5.
* __UPDATE:__ `$settings` and `$locale` are now public properties.
* __UPDATE:__ Object name changed to: $taxonomy_images_plugin.
* __UPDATE:__ Added argument $term_tax_id to both print_image_html() and get_image_html().
* __BUGFIX:__ Deleted the register_deactivation_hook() function -> sorry to all 8 who downloaded this plugin so far :)

__0.2__

* Original Release - Works With: wp 2.9.1.