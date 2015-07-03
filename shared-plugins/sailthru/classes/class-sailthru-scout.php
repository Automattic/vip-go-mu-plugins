<?php
/*
 *
 *	Scout depends entirely on Horizon
 *
 */

class Sailthru_Scout_Widget extends WP_Widget {

	/*--------------------------------------------*
	 * Constructor
	 *--------------------------------------------*/
	function __construct() {

		// Register Scout Javascripts
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scout_scripts' ) );

		// Attempt to create the page needed for Scout
		$post_id = $this->create_scout_page();

		// Attempt to register the sidebar widget for Scout
		//wp_register_sidebar_widget('sailthru-recommend-widget', 'Sailthru Recommends', array($this, 'widget'));

		// load plugin text domain
		add_action( 'init', array( $this, 'load_widget_text_domain' ) );


		parent::__construct(
			'sailthru-recommends-id',
			__( 'Sailthru Recommends Widget', 'sailthru-for-wordpress' ),
			array(
				'classname'		=>	'Sailthru_Scout',
				'description'	=>	__( 'Sailthru Scout but in a compact sidebar widget.', 'sailthru-for-wordpress' )
			)
		);

	} // end constructor


	/*--------------------------------------------------*/
	/* Public Functions
	/*--------------------------------------------------*/

	/**
	 * Loads the Widget's text domain for localization and translation.
	 */
	public function load_widget_text_domain() {

		load_plugin_textdomain( 'sailthru-for-wordpress', false, plugin_dir_path( SAILTHRU_PLUGIN_PATH ) . '/lang/' );

	} // end load_widget_text_domain



	/**
	 * Add scout. But only if scout is turned on.
	 */
	public function register_scout_scripts() {

		$params = get_option('sailthru_scout_options');

		// is scout turned on?
		if( isset($params['sailthru_scout_is_on']) &&  $params['sailthru_scout_is_on']) {

			// Check first, otherwise js could throw errors
			if( get_option('sailthru_setup_complete') ) {

				//wp_enqueue_script( 'sailthru-scout', '//ak.sail-horizon.com/scout/v1.js', array('jquery', 'sailthru-horizon') );

				//wp_enqueue_script( 'sailthru-scout-params', SAILTHRU_PLUGIN_URL .'/js/scout.params.js' , array('sailthru-scout') );

				// if conceirge is on, we want noPageView to be set to true
				// see
				$conceirge = get_option('sailthru_concierge_options');
					if( isset($conceirge['sailthru_convierge_is_on'] ) && $conceirge['sailthru_convierge_is_on'] ) {
						$params['sailthru_scout_noPageview'] = 'true';
					}

				add_action('wp_footer', array( $this, 'scout_js' ), 10);

				//wp_localize_script( 'sailthru-scout-params', 'Scout', $params );

			} // end if sailthru setup is done

		} // end if scout is on

	} // register_conceirge_scripts

	/*-------------------------------------------
	 * Utility Functions
	 *------------------------------------------*/

	/**
	 * A function used to render the Scout JS f
	 *
	 * @returns  string
	 */
	 function scout_js() {

	 	$options = get_option('sailthru_setup_options');
		$horizon_domain = $options['sailthru_horizon_domain'];
		$scout = get_option('sailthru_scout_options');
		$scout_params = array();

		// inlcudeConsumed?
		if( isset($scout['sailthru_scout_includeConsumed']) ) {
			$scout_params[] = strlen( $scout['sailthru_scout_includeConsumed'] ) > 0 ?  'includeConsumed: '. (bool) $scout['sailthru_scout_includeConsumed'].'' : '';
		} else {
			$scout['sailthru_scout_includeConsumed'] = '';
		}

		// renderItem?
		if( isset( $scout['sailthru_scout_renderItem']) ) {
			$scout_params[] = strlen($scout['sailthru_scout_renderItem']) > 0 ?  "renderItem: ". (bool) $scout['sailthru_scout_renderItem']."": '';
		} else {
			$scout['sailthru_scout_renderItem'] = '';
		}

		if( isset( $scout['scout_num_visible']) ) {
			$scout_params[] = strlen($scout['scout_num_visible']) > 0 ?  "numVisible:'". esc_js( $scout['sailthru_scout_number'] )."' ": '';
		} else {
			$scout['scout_num_visible'] = '';
		}


		if ($scout['sailthru_scout_is_on'] == 1) {
			echo "<script type=\"text/javascript\" src=\"//ak.sail-horizon.com/scout/v1.js\"></script>";
		 	echo "<script type=\"text/javascript\">\n";
	           echo "SailthruScout.setup({\n";
	           echo "domain: '". esc_js($options['sailthru_horizon_domain'])."',\n";
				if( is_array($scout_params) ) {
					foreach ($scout_params as $key => $val) {
						if (strlen($val) >0)  {
							echo esc_js($val).",\n";
						}
					}
				}
	           echo "});\n";

		     echo " if(SailthruScout.allContent.length == 0) { jQuery('#sailthru-scout').hide() }";
		     echo "</script>\n";
		}

	 }

	/**
	 * A function used to programmatically create a page needed for Scout. The slug, author ID, and title
	 * are defined within the context of the function.
	 *
	 * @returns -1 if the post was never created, -2 if a post with the same title exists, or the ID
	 *          of the post if successful.
	 */

	private function create_scout_page() {

		// never run this on public facing pages
		if( !is_admin() ) {
			return;
		}

		// -1 = No action has been taken.
		$post_id = -1;

		// Our specific settings
		$slug = 'scout-from-sailthru';
		$title = 'Recommended for You';
		$post_type = 'page';
		$post_content = '<div id="sailthru-scout"><div class="loading">Loading, please wait...</div></div>';


		// If the page doesn't already exist, then create it
		$create_page = function_exists( 'wpcom_vip_get_page_by_title' ) ? null == wpcom_vip_get_page_by_title( $title ) : null == get_page_by_title( $title );
		if( $create_page ) {

			// Set the post ID so that we know the post was created successfully
			$post_id = wp_insert_post(
				array(
					'comment_status'	=>	'closed',
					'ping_status'		=>	'closed',
					'post_name'			=>	$slug,
					'post_title'		=>	$title,
					'post_status'		=>	'publish',
					'post_type'			=>	$post_type,
					'post_content'		=>	$post_content
				)
			);

		} else {

	    	$post_id = -2;

		} // end if

		return $post_id;

	}


	/*--------------------------------------------------*/
	/* Widget API Functions
	/*--------------------------------------------------*/


	/**
	 * Outputs the content of the widget.
	 *
	 * @param	array	args		The array of form elements
	 * @param	array	instance	The current instance of the widget
	 */
	function widget($args, $instance) {

		extract( $args, EXTR_SKIP );

		echo $before_widget;

		include( SAILTHRU_PLUGIN_PATH . 'views/widget.scout.display.php' );

		echo $after_widget;
	}
	/**
	 * Processes the widget's options to be saved.
	 *
	 * @param	array	new_instance	The previous instance of values before the update.
	 * @param	array	old_instance	The new instance of values to be generated via the update.
	 */
	public function update( $new_instance, $old_instance ) {

		$instance = array();
			$instance['title'] = filter_var( $new_instance['title'], FILTER_SANITIZE_STRING );

		return $instance;

	} // end widget

	/**
	 * Generates the administration form for the widget.
	 *
	 * @param	array	instance	The array of keys and values for the widget.
	 */
	public function form( $instance ) {

		// Default values for a widget instance
        $instance = wp_parse_args(
        	(array) $instance, array(
                'title' => ''
            )
        );
        $title = esc_attr($instance['title']);


		// Display the admin form
		include( SAILTHRU_PLUGIN_PATH . 'views/widget.scout.admin.php' );

	} // end form




} // end class
add_action( 'widgets_init', create_function( '', 'register_widget("Sailthru_Scout_Widget");' ) );
