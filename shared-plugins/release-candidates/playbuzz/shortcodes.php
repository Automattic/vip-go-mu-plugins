<?php
/*
 * Security check
 * Exit if file accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



/*
 * Item Shortcode
 * Display a specific item in a desired location on your content.
 *
 * usage: [playbuzz-item url="https://www.playbuzz.com/jonathang/players-and-playmates-playoffs"]
 *
 * @since 0.1.0
 */
add_shortcode( 'playbuzz-item', 'playbuzz_item_shortcode' );
add_shortcode( 'playbuzz-game', 'playbuzz_item_shortcode' );
add_shortcode( 'playbuzz-post', 'playbuzz_item_shortcode' );



/*
 * Section Shortcode
 * Display a list of items according specific tags in a desired location on your content.
 *
 * usage: [playbuzz-section tags="Celebrities"]
 *
 * @since 0.1.0
 */
add_shortcode( 'playbuzz-section', 'playbuzz_section_shortcode' );
add_shortcode( 'playbuzz-hub',     'playbuzz_section_shortcode' );
add_shortcode( 'playbuzz-archive', 'playbuzz_section_shortcode' );



/*
 * Recommendations / Related-Content Shortcode
 * Display playbuzz related playful content links and recommendations according specific tags in a desired location on your content.
 *
 * usage: [playbuzz-recommendations tags="Celebrities" links="https://www.mysite.com/url_in_your_site_where_you_displayed_playbuzz_items"]
 *
 * @since 0.1.0
 */
add_shortcode( 'playbuzz-related',         'playbuzz_recommendations_shortcode' );
add_shortcode( 'playbuzz-recommendations', 'playbuzz_recommendations_shortcode' );



/*
 * Shortcode functions
 *
 * @since 0.1.1
 */
function playbuzz_item_shortcode( $atts ) {

	// Load WordPress globals
	global $wp_version;

	// Load global site settings from DB
	$options = (array) get_option( 'playbuzz' );

	// Prepare site settings
	$site_key       = ( ( ( array_key_exists( 'key',               $options ) ) ) ? $options['key'] : str_replace( 'www.', '', parse_url( home_url(), PHP_URL_HOST ) ) );
	$site_info      = ( ( ( array_key_exists( 'info',              $options ) ) && ( '1' == $options['info']      ) ) ? 'true' : 'false' );
	$site_shares    = ( ( ( array_key_exists( 'shares',            $options ) ) && ( '1' == $options['shares']    ) ) ? 'true' : 'false' );
	$site_comments  = ( ( ( array_key_exists( 'comments',          $options ) ) && ( '1' == $options['comments']  ) ) ? 'true' : 'false' );
	$site_recommend = ( ( ( array_key_exists( 'recommend',         $options ) ) && ( '1' == $options['recommend'] ) ) ? 'true' : 'false' );
	$site_tags      = '';
	$site_tags     .= ( ( ( array_key_exists( 'tags-mix',          $options ) ) && ( '1' == $options['tags-mix']          ) ) ? 'All,'                  : '' );
	$site_tags     .= ( ( ( array_key_exists( 'tags-fun',          $options ) ) && ( '1' == $options['tags-fun']          ) ) ? 'Fun,'                  : '' );
	$site_tags     .= ( ( ( array_key_exists( 'tags-pop',          $options ) ) && ( '1' == $options['tags-pop']          ) ) ? 'Pop,'                  : '' );
	$site_tags     .= ( ( ( array_key_exists( 'tags-geek',         $options ) ) && ( '1' == $options['tags-geek']         ) ) ? 'Geek,'                 : '' );
	$site_tags     .= ( ( ( array_key_exists( 'tags-sports',       $options ) ) && ( '1' == $options['tags-sports']       ) ) ? 'Sports,'               : '' );
	$site_tags     .= ( ( ( array_key_exists( 'tags-editors-pick', $options ) ) && ( '1' == $options['tags-editors-pick'] ) ) ? 'EditorsPick_Featured,' : '' );
	$site_tags     .= ( ( ( array_key_exists( 'more-tags',         $options ) ) ) ? $options['more-tags']  : '' );
	$site_tags      = rtrim( $site_tags, ',' );
	$site_margintop = ( ( ( array_key_exists( 'margin-top',        $options ) ) ) ? $options['margin-top'] : '' );
	$embeddedon     = ( ( ( array_key_exists( 'embeddedon',        $options ) ) ) ? $options['embeddedon'] : 'content' );

	// Set default attribute values if the user did not defined any
	$atts = shortcode_atts(
		array(
			'key'        => $site_key,       // api key allowing configuration and analytics
			'game'       => '',              // defines the item that will be loaded by the IFrame (deprecated in 0.3 ; use "url" attribute)
			'url'        => '',              // defines the item that will be loaded by the IFrame (added in 0.3)
			'info'       => $site_info,      // show item info (thumbnail, name, description, editor, etc)
			'shares'     => $site_shares,    // show sharing buttons 
			'comments'   => $site_comments,  // show comments control from the item page
			'recommend'  => $site_recommend, // show recommendations for more items
			'tags'       => $site_tags,      // filter by tags
			'links'      => '',              // destination url in your site where new items will be displayed
			'width'      => 'auto',          // define custom width (added in 0.3)
			'height'     => 'auto',          // define custom height (added in 0.3)
			'margin-top' => $site_margintop, // margin top for score bar in case there is a floating bar
		), $atts );

	// Playbuzz Embed Code
	$code = '
		<script type="text/javascript" src="//cdn.playbuzz.com/widget/feed.js"></script>
		<div class="pb_feed" data-provider="WordPress ' . esc_attr( $wp_version ) . '" data-key="' . esc_attr( $atts['key'] ) . '" data-tags="' . esc_attr( $atts['tags'] ) . '" data-game="' . esc_url( $atts['url'] . $atts['game'] ) . '" data-game-info="' . esc_attr( $atts['info'] ) . '" data-comments="' . esc_attr( $atts['comments'] ) . '" data-shares="' . esc_attr( $atts['shares'] ) . '" data-recommend="' . esc_attr( $atts['recommend'] ) . '" data-links="' . esc_attr( $atts['links'] ) . '" data-width="' . esc_attr( $atts['width'] ) . '" data-height="' . esc_attr( $atts['height'] ) . '" data-margin-top="' . esc_attr( $atts['margin-top'] ) . '"></div>
	';

	// Theme Visibility
	if ( 'content' == $embeddedon ) {
		// Show only in singular pages
		if ( is_singular() ) {
			return $code;
		}
	} elseif ( 'all' == $embeddedon ) {
		// Show in all pages
		return $code;
	}

}

/*
 * Shortcode functions
 *
 * @since 0.1.4
 */
function playbuzz_section_shortcode( $atts ) {

	// Load WordPress globals
	global $wp_version;

	// Load global site settings from DB
	$options = (array) get_option( 'playbuzz' );

	// Prepare site settings
	$site_key       = ( ( ( array_key_exists( 'key',               $options ) ) ) ? $options['key'] : str_replace( 'www.', '', parse_url( home_url(), PHP_URL_HOST ) ) );
	$site_info      = ( ( ( array_key_exists( 'info',              $options ) ) && ( '1' == $options['info']      ) ) ? 'true' : 'false' );
	$site_shares    = ( ( ( array_key_exists( 'shares',            $options ) ) && ( '1' == $options['shares']    ) ) ? 'true' : 'false' );
	$site_comments  = ( ( ( array_key_exists( 'comments',          $options ) ) && ( '1' == $options['comments']  ) ) ? 'true' : 'false' );
	$site_recommend = ( ( ( array_key_exists( 'recommend',         $options ) ) && ( '1' == $options['recommend'] ) ) ? 'true' : 'false' );
	$site_tags      = '';
	$site_tags     .= ( ( ( array_key_exists( 'tags-mix',          $options ) ) && ( '1' == $options['tags-mix']          ) ) ? 'All,'                  : '' );
	$site_tags     .= ( ( ( array_key_exists( 'tags-fun',          $options ) ) && ( '1' == $options['tags-fun']          ) ) ? 'Fun,'                  : '' );
	$site_tags     .= ( ( ( array_key_exists( 'tags-pop',          $options ) ) && ( '1' == $options['tags-pop']          ) ) ? 'Pop,'                  : '' );
	$site_tags     .= ( ( ( array_key_exists( 'tags-geek',         $options ) ) && ( '1' == $options['tags-geek']         ) ) ? 'Geek,'                 : '' );
	$site_tags     .= ( ( ( array_key_exists( 'tags-sports',       $options ) ) && ( '1' == $options['tags-sports']       ) ) ? 'Sports,'               : '' );
	$site_tags     .= ( ( ( array_key_exists( 'tags-editors-pick', $options ) ) && ( '1' == $options['tags-editors-pick'] ) ) ? 'EditorsPick_Featured,' : '' );
	$site_tags     .= ( ( ( array_key_exists( 'more-tags',         $options ) ) ) ? $options['more-tags']  : '' );
	$site_tags      = rtrim( $site_tags, ',' );
	$site_margintop = ( ( ( array_key_exists( 'margin-top',        $options ) ) ) ? $options['margin-top'] : '' );
	$embeddedon     = ( ( ( array_key_exists( 'embeddedon',        $options ) ) ) ? $options['embeddedon'] : 'content' );

	// Set default attribute values if the user did not defined any
	$atts = shortcode_atts(
		array(
			'key'        => $site_key,       // api key allowing configuration and analytics
			'tags'       => $site_tags,      // filter by tags
			'game'       => '',              // defines the item that will be loaded by the IFrame (deprecated in 0.3 ; use "url" attribute)
			'url'        => '',              // defines the item that will be loaded by the IFrame (added in 0.3)
			'info'       => $site_info,      // show item info (thumbnail, name, description, editor, etc)
			'shares'     => $site_shares,    // show sharing buttons 
			'comments'   => $site_comments,  // show comments control from the item page
			'recommend'  => $site_recommend, // show recommendations for more items
			'links'      => '',              // destination url in your site where new items will be displayed
			'width'      => 'auto',          // define custom width (added in 0.3)
			'height'     => 'auto',          // define custom height (added in 0.3)
			'margin-top' => $site_margintop, // margin top for score bar in case there is a floating bar
		), $atts );

	// Playbuzz Embed Code
	$code = '
		<script type="text/javascript" src="//cdn.playbuzz.com/widget/feed.js"></script>
		<div class="pb_feed" data-provider="WordPress ' . esc_attr( $wp_version ) . '" data-key="' . esc_attr( $atts['key'] ) . '" data-tags="' . esc_attr( $atts['tags'] ) . '" data-game="' . esc_url( $atts['url'] . $atts['game'] ) . '" data-game-info="' . esc_attr( $atts['info'] ) . '" data-comments="' . esc_attr( $atts['comments'] ) . '" data-shares="true" data-recommend="' . esc_attr( $atts['recommend'] ) . '" data-links="' . esc_attr( $atts['links'] ) . '" data-width="' . esc_attr( $atts['width'] ) . '" data-height="' . esc_attr( $atts['height'] ) . '" data-margin-top="' . esc_attr( $atts['margin-top'] ) . '"></div>
	';

	// Theme Visibility
	if ( 'content' == $embeddedon ) {
		// Show only in singular pages
		if ( is_singular() ) {
			return $code;
		}
	} elseif ( 'all' == $embeddedon ) {
		// Show in all pages
		return $code;
	}

}

/*
 * Shortcode functions
 *
 * @since 0.1.4
 */
function playbuzz_recommendations_shortcode( $atts ) {

	// Load WordPress globals
	global $wp_version;

	// Load global site settings from DB
	$options = (array) get_option( 'playbuzz' );

	// Prepare site settings
	$site_key       = ( ( ( array_key_exists( 'key',               $options ) ) ) ? $options['key']   : str_replace( 'www.', '', parse_url( home_url(), PHP_URL_HOST ) ) );
	$site_view      = ( ( ( array_key_exists( 'view',              $options ) ) ) ? $options['view']  : 'large_images' );
	$site_items     = ( ( ( array_key_exists( 'items',             $options ) ) ) ? $options['items'] : 3 );
	$site_links     = ( ( ( array_key_exists( 'links',             $options ) ) ) ? $options['links'] : 'https://www.playbuzz.com' );
	$site_tags      = '';
	$site_tags     .= ( ( ( array_key_exists( 'tags-mix',          $options ) ) && ( '1' == $options['tags-mix']          ) ) ? 'All,'                  : '' );
	$site_tags     .= ( ( ( array_key_exists( 'tags-fun',          $options ) ) && ( '1' == $options['tags-fun']          ) ) ? 'Fun,'                  : '' );
	$site_tags     .= ( ( ( array_key_exists( 'tags-pop',          $options ) ) && ( '1' == $options['tags-pop']          ) ) ? 'Pop,'                  : '' );
	$site_tags     .= ( ( ( array_key_exists( 'tags-geek',         $options ) ) && ( '1' == $options['tags-geek']         ) ) ? 'Geek,'                 : '' );
	$site_tags     .= ( ( ( array_key_exists( 'tags-sports',       $options ) ) && ( '1' == $options['tags-sports']       ) ) ? 'Sports,'               : '' );
	$site_tags     .= ( ( ( array_key_exists( 'tags-editors-pick', $options ) ) && ( '1' == $options['tags-editors-pick'] ) ) ? 'EditorsPick_Featured,' : '' );
	$site_tags     .= ( ( ( array_key_exists( 'more-tags',         $options ) ) ) ? $options['more-tags']  : '' );
	$site_tags      = rtrim( $site_tags, ',' );
	$embeddedon     = ( ( ( array_key_exists( 'embeddedon',        $options ) ) ) ? $options['embeddedon'] : 'content' );

	// Set default attribute values if the user did not defined any
	$atts = shortcode_atts(
		array(
			'key'     => $site_key,   // api key allowing configuration and analytics
			'view'    => $site_view,  // set view type
			'items'   => $site_items, // number of items to display
			'links'   => $site_links, // destination url in your site where new items will be displayed
			'tags'    => $site_tags,  // filter by tags
			'nostyle' => 'false',     // set style
		), $atts );

	// Playbuzz Embed Code
	$code = '
		<script type="text/javascript" src="//cdn.playbuzz.com/widget/widget.js"></script>
		<div class="pb_recommendations" data-provider="WordPress ' . esc_attr( $wp_version ) . '" data-key="' . esc_attr( $atts['key'] ) . '" data-tags="' . esc_attr( $atts['tags'] ) . '" data-view="' . esc_attr( $atts['view'] ) . '" data-num-items="' . esc_attr( $atts['items'] ) . '" data-links="' . esc_attr( $atts['links'] ) . '" data-nostyle="' . esc_attr( $atts['nostyle'] ) . '"></div>
	';

	// Theme Visibility
	if ( 'content' == $embeddedon ) {
		// Show only in singular pages
		if ( is_singular() ) {
			return $code;
		}
	} elseif ( 'all' == $embeddedon ) {
		// Show in all pages
		return $code;
	}

}
