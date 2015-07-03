=== Ad Code Manager ===
Contributors: rinatkhaziev, jeremyfelt, danielbachhuber, carldanley, zztimur, automattic, doejo
Tags: advertising, ad codes, ads, adsense, dfp, doubleclick for publishers
Requires at least: 3.1
Tested up to: 3.6-beta3
Stable tag: 0.4.2

Manage your ad codes through the WordPress admin in a safe and easy way.

== Description ==

Ad Code Manager gives non-developers an interface in the WordPress admin for configuring your complex set of ad codes.

Some code-level configuration may be necessary to setup Ad Code Manager. Ad tags must be added (via `do_action()`) to your theme's template files where you'd like ads to appear. Alternatively, you can incorporate ad tags into your website with our widget and our shortcode. [Check out the configuration guide](http://vip.wordpress.com/documentation/configure-ad-code-manager-to-manage-the-advertisements-on-your-site/) for the full details.

A common set of parameters must also be defined for your ad provider. This includes the tag IDs used by your template, the default URL for your ad provider, and the default HTML surrounding that URL. Ad Code Manager comes with support for Google Doubleclick For Publishers (and Async), OpenX, and Google AdSense. All of the logic is abstracted, however, so configuring a different provider is relatively easy. Check `providers/doubleclick-for-publishers.php` for an idea of how to extend ACM to suit your needs.

Once this configuration is in place, the Ad Code Manager admin interface will allow you to add new ad codes, modify the parameters for your script URL, and define conditionals to determine when the ad code appears. Conditionals are core WordPress functions like is_page(), is_category(), or your own custom functions that evaluate certain expression and then return true or false.

[Fork the plugin on Github](https://github.com/Automattic/Ad-Code-Manager) and [follow our development blog](http://adcodemanager.wordpress.com/).

== Installation ==

Since the plugin is in its early stages, there are a couple additional configuration steps:

1. Upload `ad-code-manager` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Incorporate ad tags in your theme template with  `do_action( 'acm_tag', 'slot' )`. Also you can use [acm-tag id="slot"] shortcode or ACM Widget
1. Implement filters to make the plugin work with your provider
1. Configure your ad codes in the WordPress admin ( Tools -> Ad Code Manager )

== Screenshots ==

1. The ACM admin interface before adding ad codes.
1. Adding an ad code with a site name, zone, and multiple conditionals.
1. Access the Help menu in the upper right for configuration assistance.
1. Edit existing ad codes inline through the admin interface.
1. Example of ad tag in use in a theme header template.

== Upgrade Notice ==

= 0.4 =
Easier, streamlined configuration for Doubleclick for Publishers Async and Google AdSense.

= 0.3 =
Conditional operator logic can be set on an ad code by ad code basis. Couple of bug fixes.

= 0.2.3 =
The filter acm_provider_columns is removed in favor of acm_ad_code_args (see acm_ad_code_args )

= 0.2.2 =
Incorporated a new provider for Google AdSense and added bulk delete action for the WP List Table.

= 0.2.1 =
Flush the cache when adding or deleting ad codes, and set priority of 10 when a priority doesn't exist for an ad code.

== Changelog ==

= 0.4.2 (May. 1, 2013) =
* Added robots.txt entries for provider's crawlers

= 0.4.1 (Apr. 27, 2013) =
* Disabled rendering of ads on preview to avoid crawling errors. Thanks [Paul Gibbs](https://github.com/paulgibbs)
* Bug fix: Corrected "medium rectangle" ad size for DFP Async Provider. Thanks [Marco](https://github.com/NRG-R9T)

= 0.4 (Mar. 19, 2013) =
* Streamlined configuration for Doubleclick for Publishers Async and Google AdSense
* Faster, cleaner JavaScript thanks to [Jeremy Felt](https://github.com/jeremyfelt) and [Carl Danley](https://github.com/carldanley)
* New filter 'acm_output_html_after_tokens_processed' for rare cases where you might want to filter html after the tokens are processed

= 0.3 (October 25, 2012) =
* Conditional operator logic can be set on an ad code by ad code basis. Thanks [jtsternberg](https://github.com/jtsternberg) for the pull request!
* Bug fix: If an ad tag doesn't need a URL, ignore the whitelist check
* Bug fix: Make sure that all providers list tables call parent::get_columns to avoid conflicts with filters.
* Coding standards cleanup

= 0.2.3 (June 25,2012) =

* Allow columns to be optional when creating and editing ad codes, introduced new filter acm_ad_code_args
* Remove acm_provider_columns filter

= 0.2.2 (June 5, 2012) =
* New Google Ad Sense provider courtesy of [Erick Hitter](http://www.ethitter.com/)
* Bulk delete action added for the WP List Table of ad codes. Delete more ad codes in one go
* New 'acm_register_provider_slug' for registering a provider that's included outside the plugin (e.g. a theme)
* Bug fix: Instantiate the WP List Table on the view, instead of on admin_init, to reduce conflicts with other list tables

= 0.2.1 (May 14, 2012) =
* Flush the cache whenever an ad code is created or deleted so you don't have to wait for a timeout with persistent cache
* Bug fix: Default to priority 10 when querying for ad codes if there is no priority set

= 0.2 (May 7, 2012) =
* UI reworked from the ground up to look and work much more like the WordPress admin (using WP List Table)
* Abstracted ad network logic, so users can integrate other ad networks. Pull requests to add support to the plugin are always welcome
* Added in-plugin contextual help
* Implemented priority for ad code (allows to workaround ad code conflicts if any)
* Implemented the [acm-tag] shortcode
* Implemented ACM Widget. Thanks to [Justin Sternburg](https://github.com/jtsternberg) at WebDevStudios for the contribution
* Initial loading of the ad codes is now cached using object cache
* Bug fix: Enable using ad codes with empty filters using a filter
* Bug fix: Setting the logical operator from OR to AND did not seem to result in the expected behaviour for displaying ads
* Bug fix: Remove logical operator check when a conditional for an ad code is empty

= 0.1.3 (February 13, 2012) =
* UI cleanup for the admin, including styling and information on applying conditionals

= 0.1.2 (February 9, 2012) =
* Readme with full description and examples
* Bug fix: Save the proper value when editing actions

= 0.1.1 =
* Bug fix release

= 0.1 =
* Initial release

== Configuration Filters ==

There are some filters which allow you to easily customize the output of the plugin. You should place these filters in your theme's functions.php file or in another appropriate place.

[Check out this gist](https://gist.github.com/1631131) to see all of the filters in action.

= acm_ad_tag_ids =

Ad tag ids are used as a parameter when adding tags to your theme (e.g. do_action( 'acm_tag', 'my_top_leaderboard' )). The `url_vars` defined as part of each tag here will also be used to replace tokens in your default URL.
 
Arguments:
* array $tag_ids array of default tag ids

Example usage: Add a new ad tag called 'my_top_leaderboard'

	`add_filter( 'acm_ad_tag_ids', 'my_acm_ad_tag_ids' );
	function my_acm_ad_tag_ids( $tag_ids ) {
		$tag_ids[] = array(
			'tag' => 'my_top_leaderboard', // tag_id 
			'url_vars' => array(
				'sz' => '728x90', // %sz% token
				'fold' => 'atf', // %fold% token
				'my_custom_token' => 'something' // %my_custom_token% will be replaced with 'something'
			),
		);
		return $tag_ids;
	}`

= acm_default_url =

Set the default tokenized URL used when displaying your ad tags. This filter is required.

Arguments:
* string $url The tokenized url of Ad Code

Example usage: Set your default ad code URL

	`add_filter( 'acm_default_url', 'my_acm_default_url' );
	function my_acm_default_url( $url ) {
		if ( 0 === strlen( $url )  ) {
			return "http://ad.doubleclick.net/adj/%site_name%/%zone1%;s1=%zone1%;s2=;pid=%permalink%;fold=%fold%;kw=;test=%test%;ltv=ad;pos=%pos%;dcopt=%dcopt%;tile=%tile%;sz=%sz%;";
		}
	}`

= acm_output_html =

The HTML outputted by the `do_action( 'acm_tag', 'ad_tag_id' );` call in your theme. Support multiple ad formats ( e.g. Javascript ad tags, or simple HTML tags ) by adjusting the HTML rendered for a given ad tag. 

The `%url%` token used in this HTML will be filled in with the URL defined with `acm_default_url`.

Arguments:
* string $output_html The original output HTML
* string $tag_id Ad tag currently being accessed

Example usage:

	`add_filter( 'acm_output_html', 'my_acm_output_html', 10, 2 );
	function my_acm_output_html( $output_html, $tag_id ) {
		switch ( $tag_id ) {
			case 'my_leaderboard':
				$output_html = '<a href="%url%"><img src="%image_url%" /></a>';
				break;
			case 'rich_media_leaderboard':
				$output_html = '<script> // omitted </script>';
				break;
			default:
				break;
		}
		return $output_html;
	}`

= acm_register_provider_slug =

Ad Code Manager has a built in list of providers that it gathers by scanning the 'providers' directory used by the plugin. Additional providers can be added by placing the appropriate files in that directory, or by using the `acm_register_provider_slug` filter to register those that may be included as part of your theme or another plugin.

When using this plugin, you are defining the provider slug as part of the existing object as well as an array of classes associated with that provider slug.

Arguments:
* object $providers An object containing the current registered providers.

Example usage:

	`add_filter( 'acm_register_provider_slug', 'my_acm_register_provider_slug' );
	function my_acm_register_provider_slug( $providers ) {
		$providers->new_provider_slug = array( 
			'provider' => 'My_New_Ad_Company_ACM_Provider', 
			'table' => 'My_New_Ad_Company_ACM_WP_List_Table'
		);
		return $providers;
	}`

= acm_whitelisted_script_urls =

A security filter to whitelist which ad code script URLs can be added in the admin

Arguments:
* array $whitelisted_urls Existing whitelisted ad code URLs

Example usage: Allow Doubleclick for Publishers ad codes to be used

	`add_filter( 'acm_whitelisted_script_urls', 'my_acm_whitelisted_script_urls' );
	function my_acm_whiltelisted_script_urls( $whitelisted_urls ) {
		$whitelisted_urls = array( 'ad.doubleclick.net' );
		return $whitelisted_urls;
	}`

= acm_output_tokens =

Output tokens can be registered depending on the needs of your setup. Tokens defined here will be replaced in the ad tag's tokenized URL in addition to the tokens already registered with your tag id.

Arguments:
* array $output_tokens Any existing output tokens
* string $tag_id Unique tag id 
* array $code_to_display Ad Code that matched conditionals

Example usage: Test to determine whether you're in test or production by passing ?test=on query argument

	`add_filter( 'acm_output_tokens', 'my_acm_output_tokens', 10, 3 );
	function my_acm_output_tokens( $output_tokens, $tag_id, $code_to_display ) {
		$output_tokens['%test%'] = isset( $_GET['test'] ) && $_GET['test'] == 'on' ? 'on' : '';
		return $output_tokens;
	}`

= acm_whitelisted_conditionals =

Extend the list of usable conditional functions with your own awesome ones. We whitelist these so users can't execute random PHP functions.

Arguments: 
* array $conditionals Default conditionals

Example usage: Register a few custom conditional callbacks

	`add_filter( 'acm_whitelisted_conditionals', 'my_acm_whitelisted_conditionals' );
	function my_acm_whitelisted_conditionals( $conditionals ) {
		$conditionals[] = 'my_is_post_type';
		$conditionals[] = 'is_post_type_archive';
		$conditionals[] = 'my_page_is_child_of';
		return $conditionals;
	}`

= acm_conditional_args =

For certain conditionals (has_tag, has_category), you might need to pass additional arguments.

Arguments:
* array $cond_args Existing conditional arguments
* string $cond_func Conditional function (is_category, is_page, etc)

Example usage: has_category() and has_tag() use has_term(), which requires the object ID to function properly

	`add_filter( 'acm_conditional_args', 'my_acm_conditional_args', 10, 2 );
	function my_acm_conditional_args( $cond_args, $cond_func ) {
		global $wp_query;
		// has_category and has_tag use has_term
		// we should pass queried object id for it to produce correct result
		if ( in_array( $cond_func, array( 'has_category', 'has_tag' ) ) ) {
			if ( $wp_query->is_single == true ) {
				$cond_args[] = $wp_query->queried_object->ID;
			}
		}
		// my_page_is_child_of is our custom WP conditional tag and we have to pass queried object ID to it
		if ( in_array( $cond_func, array( 'my_page_is_child_of' ) ) && $wp_query->is_page ) {
			$cond_args[] = $cond_args[] = $wp_query->queried_object->ID;
		}
	
		return $cond_args;
	}`

= acm_display_ad_codes_without_conditionals =

Change the behavior of Ad Code Manager so that ad codes without conditionals display on the frontend. The default behavior is that each ad code requires a conditional to be included in the presentation logic.

Arguments:
* bool $behavior Whether or not to display the ad codes that don't have conditionals

Example usage:

	`add_filter( 'acm_display_ad_codes_without_conditionals', '__return_true' );`

= acm_provider_slug =

By default we use our bundled doubleclick_for_publishers config ( check it in /providers/doubleclick-for-publishers.php ). If you want to add your own flavor of DFP or even implement configuration for some another ad network, you'd have to apply a filter to correct the slug.

Example usage:

	`add_filter( 'acm_provider_slug', function() { return 'my-ad-network-slug'; } );`

= acm_logical_operator =

By default logical operator is set to "OR", that is, ad code will be displayed if at least one conditional returns true.
You can change it to "AND", so that ad code will be displayed only if ALL of the conditionals match

Example usage:

	`add_filter( 'acm_logical_operator', function() { return 'AND'; } );`

= acm_manage_ads_cap =

By default user has to have "manage_options" cap. This filter comes in handy, if you want to relax the requirements.

Example usage:

	`add_filter( 'acm_manage_ads_cap', function( $cap ) { return 'edit_others_posts'; } );`

= acm_allowed_get_posts_args =

This filter is only for edge cases. Most likely you won't have to touch it. Allows to include additional query args for Ad_Code_Manager->get_ad_codes() method.

Example usage:

	`add_filter( 'acm_allowed_get_posts_args', function( $args_array ) { return array( 'offset', 'exclude' ); } );`

= acm_ad_code_count =

By default the total number of ad codes to get is 50, which is reasonable for any small to mid site. However, in some certain cases you would want to increase the limit. This will affect Ad_Code_Manager->get_ad_codes() 'numberposts' query argument.

Example usage:

	`add_filter( 'acm_ad_code_count', function( $total ) { return 100; } );`

= acm_list_table_columns = 

This filter can alter table columns that are displayed in ACM UI.

Example usage:

	`add_filter( 'acm_list_table_columns', 'my_acm_list_table_columns' );
	function my_acm_list_table_columns( $columns ) {
		$columns = array(
			'id'             => __( 'ID', 'ad-code-manager' ),
			'name'           => __( 'Name', 'ad-code-manager' ),
			'priority'       => __( 'Priority', 'ad-code-manager' ),
			'conditionals'   => __( 'Conditionals', 'ad-code-manager' ),
		);
		return $columns;
	}`

= acm_ad_code_args =

This filter comes in pair with previous one, it should return array of ad network specific parameters. E.g. in acm_list_table_columns example we have
'id', 'name', 'priority', 'conditionals'. All of them except name are generic for Ad Code Manager. Hence acm_provider_columns should return only "name". "editable" and "required" indicate whether this field should be editable and required.

Example usage:

	`add_filter( 'acm_ad_code_args', 'my_acm_ad_code_args' );
	function my_acm_ad_code_args( $args ) {
		$args = array(
			array(
				'key'       => 'name',
				'label'     => __( 'Name', 'ad-code-manager' ),
				'editable'  => true,
				'required'  => true,
			),
		);
		return $args;
	}`