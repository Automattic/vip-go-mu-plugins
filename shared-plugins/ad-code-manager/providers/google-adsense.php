<?php
/**
 * Google AdSense Ad Provider for Ad Code manager
 */
class Google_AdSense_ACM_Provider extends ACM_Provider {
	public $crawler_user_agent = 'Mediapartners-Google';

	/**
	 * Register default options for Google AdSense
	 *
	 * @uses apply_filters, parent::__construct
	 * @return null
	 */
	public function __construct() {
		// Default output HTML
		$this->output_html = '<div id="acm-ad-tag-%tag%"><script type="text/javascript"><!--
google_ad_client = "%publisher_id%";
google_ad_slot = "%tag_id%";
google_ad_width = %width%;
google_ad_height = %height%;
//-->
</script>
<script type="text/javascript" src="http://pagead2.googlesyndication.com/pagead/show_ads.js"></script></div>';

		// Default Ad Tag Ids (you will pass this in your shortcode or template tag)
		$this->ad_tag_ids = array(
			array(
				'tag'       => '728x90_leaderboard',
				'url_vars'  => array(
					'tag'       => '728x90_leaderboard',
					'height'    => '90',
					'width'     => '728',
				),
				'enable_ui_mapping' => true,
			),
			array(
				'tag'       => '468x60_banner',
				'url_vars'  => array(
					'tag'       => '468x60_banner',
					'height'    => '60',
					'width'     => '468',
				),
				'enable_ui_mapping' => true,
			),
			array(
				'tag'       => '120x600_skyscraper',
				'url_vars'  => array(
					'tag'       => '120x600_skyscraper',
					'height'    => '600',
					'width'     => '120',
				),
				'enable_ui_mapping' => true,
			),
			array(
				'tag'       => '160x600_wideskyscraper',
				'url_vars'  => array(
					'tag'       => '160x600_wideskyscraper',
					'height'    => '600',
					'width'     => '160',
				),
				'enable_ui_mapping' => true,
			),
			array(
				'tag'       => '300x600_largeskyscraper',
				'url_vars'  => array(
					'tag'       => '300x600_largeskyscraper',
					'height'    => '600',
					'width'     => '300',
				),
				'enable_ui_mapping' => true,
			),
			array(
				'tag'       => '250x250_square',
				'url_vars'  => array(
					'tag'       => '250x250_square',
					'height'    => '250',
					'width'     => '250',
				),
				'enable_ui_mapping' => true,
			),
			array(
				'tag'       => '200x200_smallsquare',
				'url_vars'  => array(
					'tag'       => '200x200_smallsquare',
					'height'    => '200',
					'width'     => '200',
				),
				'enable_ui_mapping' => true,
			),
		);

		$this->ad_code_args = array(
			array(
				'key'       => 'tag',
				'label'     => __( 'Tag', 'ad-code-manager' ),
				'editable'  => true,
				'required'  => true,
				'type'      => 'select',
				'options'   => array(
					// This is added later, through 'acm_ad_code_args' filter
				),
			),
			array(
				'key'       => 'tag_id',
				'label'     => __( 'Tag ID', 'ad-code-manager' ),
				'editable'  => true,
				'required'  => true,
			),
			array(
				'key'       => 'publisher_id',
				'label'     => __( 'Publisher ID', 'ad-code-manager' ),
				'editable'  => true,
				'required'  => true,
			),
		);

		add_filter( 'acm_ad_code_args', array( $this, 'filter_ad_code_args' ) );
		add_filter( 'acm_display_ad_codes_without_conditionals', '__return_true' );

		parent::__construct();
	}

	/**
	 * Register the 'tag's available for mapping in the UI
	 */
	public function filter_ad_code_args( $ad_code_args ) {
		global $ad_code_manager;

		foreach ( $ad_code_args as $tag => $ad_code_arg ) {

			if ( 'tag' != $ad_code_arg['key'] )
				continue;

			// Get all of the tags that are registered, and provide them as options
			foreach ( (array)$ad_code_manager->ad_tag_ids as $ad_tag ) {
				if ( isset( $ad_tag['enable_ui_mapping'] ) && $ad_tag['enable_ui_mapping'] )
					$ad_code_args[$tag]['options'][$ad_tag['tag']] = $ad_tag['tag'];
			}

		}
		return $ad_code_args;
	}
}

/**
 * Google AdSense list table for Ad Code Manager
 */
class Google_AdSense_ACM_WP_List_Table extends ACM_WP_List_Table {
	/**
	 * Register table settings
	 *
	 * @uses parent::__construct
	 * @return null
	 */
	public function __construct() {
		parent::__construct( array(
				'singular'=> 'google_adsense_acm_wp_list_table',
				'plural' => 'google_adsense_acm_wp_list_table',
				'ajax' => true
			) );
	}

	/**
	 * This is nuts and bolts of table representation
	 */
	function get_columns( $columns = null ) {
		$columns = array(
			'cb'             => '<input type="checkbox" />',
			'id'             => __( 'ID', 'ad-code-manager' ),
			'tag'            => __( 'Tag', 'ad-code-manager' ),
			'tag_id'         => __( 'Tag ID', 'ad-code-manager' ),
			'publisher_id'   => __( 'Publisher ID', 'ad-code-manager' ),
			'priority'       => __( 'Priority', 'ad-code-manager' ),
			'operator'       => __( 'Logical Operator', 'ad-code-manager' ),
			'conditionals'   => __( 'Conditionals', 'ad-code-manager' ),
		);
		return parent::get_columns( $columns );
	}

	/**
	 * Output the tag cell in the list table
	 */
	function column_tag( $item ) {
		$output = isset( $item['tag'] ) ? esc_html( $item['tag'] ) : esc_html( $item['url_vars']['tag'] );
		$output .= $this->row_actions_output( $item );
		return $output;
	}
}
?>
