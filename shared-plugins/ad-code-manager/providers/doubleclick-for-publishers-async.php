<?php

class Doubleclick_For_Publishers_Async_ACM_Provider extends ACM_Provider {
	public $crawler_user_agent = 'Mediapartners-Google';

	public function __construct() {

		// Default ad zones for DFP Async
		$this->ad_tag_ids = array(
			array(
				'tag'       => '728x90',
				'url_vars'  => array(
					'tag'       => '728x90',
					'sz'        => '728x90',
					'height'    => '90',
					'width'     => '728',
				),
				'enable_ui_mapping' => true,
			),
			array(
				'tag'       => '300x250',
				'url_vars'  => array(
					'tag'       => '300x250',
					'sz'        => '300x250',
					'width'    => '300',
					'height'     => '250',
				),
				'enable_ui_mapping' => true,
			),
			array(
				'tag'       => '120x600',
				'url_vars'  => array(
					'tag'       => '120x600',
					'sz'        => '120x600',
					'width'    => '120',
					'height'     => '600',
				),
				'enable_ui_mapping' => true,
			),
			array(
				'tag'       => '160x600',
				'url_vars'  => array(
					'tag'       => '160x600',
					'sz'        => '160x600',
					'width'    => '160',
					'height'     => '600',
				),
				'enable_ui_mapping' => true,
			),
			array(
				'tag'       => '300x600',
				'url_vars'  => array(
					'tag'       => '300x600',
					'sz'        => '300x600',
					'width'    => '300',
					'height'     => '600',
				),
				'enable_ui_mapping' => true,
			),


			// An extra, special tag to make sure the <head> gets the output we need it to
			array(
				'tag'           => 'dfp_head',
				'url_vars'      => array(),
			),
		);

		// Default fields for DFP Async
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
				'key'       => 'dfp_id',
				'label'     => __( 'DFP ID', 'ad-code-manager' ),
				'editable'  => true,
				'required'  => true,
			),
			array(
				'key'       => 'tag_name',
				'label'     => __( 'Tag Name', 'ad-code-manager' ),
				'editable'  => true,
				'required'  => true,
			),
		);

		add_filter( 'acm_ad_code_args', array( $this, 'filter_ad_code_args' ) );
		add_filter( 'acm_output_html', array( $this, 'filter_output_html' ), 10, 2 );

		add_filter( 'acm_display_ad_codes_without_conditionals', '__return_true' );

		add_action( 'wp_head', array( $this, 'action_wp_head' ) );

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


	/**
	 * Filter the output HTML to automagically produce the <script> we need
	 */
	public function filter_output_html( $output_html, $tag_id ) {
		global $ad_code_manager;

		switch ( $tag_id ) {
		case 'dfp_head':
			$ad_tags = $ad_code_manager->ad_tag_ids;
			ob_start();
?>
	<!-- Include google_services.js -->
<script type='text/javascript'>
var googletag = googletag || {};
googletag.cmd = googletag.cmd || [];
(function() {
var gads = document.createElement('script');
gads.async = true;
gads.type = 'text/javascript';
var useSSL = 'https:' == document.location.protocol;
gads.src = (useSSL ? 'https:' : 'http:') +
'//www.googletagservices.com/tag/js/gpt.js';
var node = document.getElementsByTagName('script')[0];
node.parentNode.insertBefore(gads, node);
})();
</script>
<script type='text/javascript'>
googletag.cmd.push(function() {
<?php
			foreach ( (array) $ad_tags as $tag ):
				if ( $tag['tag'] == 'dfp_head' )
					continue;

				$tt = $tag['url_vars'];
			$matching_ad_code = $ad_code_manager->get_matching_ad_code( $tag['tag'] );
			if ( ! empty( $matching_ad_code ) ) {
				// @todo There might be a case when there are two tags registered with the same dimensions
				// and the same tag id ( which is just a div id ). This confuses DFP Async, so we need to make sure
				// that tags are unique
?>
googletag.defineSlot('/<?php echo esc_attr( $matching_ad_code['url_vars']['dfp_id'] ); ?>/<?php echo esc_attr( $matching_ad_code['url_vars']['tag_name'] ); ?>', [<?php echo (int)$tt['width'] ?>, <?php echo (int)$tt['height'] ?>], "acm-ad-tag-<?php echo esc_attr( $matching_ad_code['url_vars']['tag_id'] ); ?>").addService(googletag.pubads());
<?php
			}
			endforeach;
?>
googletag.pubads().enableSingleRequest();
googletag.pubads().collapseEmptyDivs();
googletag.enableServices();
});
</script>
<?php

			$output_script = ob_get_clean();
			break;
		default:
			$output_script = "
		<div id='acm-ad-tag-%tag_id%' style='width:%width%px; height:%height%px;'>
<script type='text/javascript'>
googletag.cmd.push(function() { googletag.display('acm-ad-tag-%tag_id%'); });
</script>
		</div>
		";
		}
		return $output_script;

	}

	/**
	 * Add the initialization code in the head
	 */
	public function action_wp_head() {
		do_action( 'acm_tag', 'dfp_head' );
	}

}

class Doubleclick_For_Publishers_Async_ACM_WP_List_Table extends ACM_WP_List_Table {
	function __construct() {
		parent::__construct( array(
				'singular'=> 'doubleclick_for_publishers_async_acm_wp_list_table', //Singular label
				'plural' => 'doubleclick_for_publishers_async_acm_wp_list_table', //plural label, also this well be one of the table css class
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
			'dfp_id'         => __( 'DFP ID', 'ad-code-manager' ),
			'tag_name'       => __( 'Tag Name', 'ad-code-manager' ),
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
