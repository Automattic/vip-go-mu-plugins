<?php

class O2O {

	private static $instance;

	public $connection_factory;
	public $rewrites_enabled = false;

	protected function __construct( $connection_factory ) {
		$this->connection_factory = $connection_factory;
	}
	
	/**
	 * 
	 * @return O2O_Connection_Factory
	 */
	public function get_connection_factory() {
		return $this->connection_factory;
	}

	/**
	 * 
	 * @return O2O
	 */
	public static function GetInstance() {
		if( self::$instance === null ) {
			$connection_factory = new O2O_Connection_Factory();
			$query = new O2O_Query( $connection_factory );

			self::$instance = new O2O( $connection_factory, $query );
		}
		return self::$instance;
	}

	public static function Register_Connection( $name, $from_object_types, $to_object_types, $args = array( ) ) {
		self::GetInstance()->connection_factory->register( $name, $from_object_types, $to_object_types, $args );
	}

	public static function Enable_Rewrites( $enabled = true ) {
		self::GetInstance()->rewrites_enabled = $enabled;
	}

	public function init() {
		
		$query = new O2O_Query( $this->connection_factory );
		$query->init();
		
		if ( function_exists( 'wpcom_vip_enable_term_order_functionality' ) ) {
			//ensure that the ability to sort terms is setup on WordPress.com VIP
			wpcom_vip_enable_term_order_functionality();
		}
		
		if ( $this->rewrites_enabled ) {
			$rewrites = new O2O_Rewrites( $this->connection_factory );
			$rewrites->init();
		}

		if ( is_admin() ) {
			if ( ! class_exists( 'O2O_Admin' ) ) {
				require_once( dirname( __DIR__ ) . '/admin/admin.php' );
			}
			$admin = new O2O_Admin( $this->connection_factory );
			$admin->init();
		}
		
		//@todo, move the below to a better location
		
		//allow custom templates based on connection type
		add_filter( 'archive_template', function($template) {
				global $wp_query;
				if ( is_o2o_connection() ) {
					$additional_templates = array( );

					if ( ($post_type = ( array ) get_query_var( 'post_type' )) && (count( $post_type ) == 1) ) {

						$additional_templates[] = "o2o-{$wp_query->o2o_connection}-{$wp_query->query_vars['o2o_query']['direction']}-{$post_type[0]}.php";

						$additional_templates[] = "o2o-{$wp_query->o2o_connection}-{$post_type[0]}.php";
					}

					$additional_templates[] = "o2o-{$wp_query->o2o_connection}.php";
					if ( $o2o_template = locate_template( $additional_templates ) ) {
						return $o2o_template;
					}
				}
				return $template;
			} );
			
		//redirect canonical o2o based pages to canonical
		add_filter('template_redirect', function(){
			global $wp_query, $wpdb;
			if ( is_404() && is_o2o_connection() && !get_queried_object_id() ) {
				$o2o_query = $wp_query->query_vars['o2o_query'];
				
				if ( $connection = O2O_Connection_Factory::Get_Connection( $o2o_query['connection'] ) ) {
					if(isset( $o2o_query['post_name'] ) ) {
						$post_name = $o2o_query['post_name'];
						$name_post_types = $o2o_query['direction'] == 'to' ? $connection->from() : $connection->to();
						
						$post_name = rawurlencode( urldecode( $post_name ) );
						$post_name = str_replace( '%2F', '/', $post_name );
						$post_name = str_replace( '%20', ' ', $post_name );
						$post_name = array_pop( explode( '/', trim( $post_name, '/' ) ) );

						$post_types = array_map( 'esc_sql', (array) $name_post_types);
						$post_types_in = "('" . implode(', ', $post_types) . "')";
						$post_id = $wpdb->get_var($wpdb->prepare("SELECT post_id from $wpdb->postmeta PM JOIN $wpdb->posts P ON P.ID = PM.post_id ".
								"WHERE meta_key = '_wp_old_slug' AND meta_value = %s AND post_type in {$post_types_in} limit 1", $post_name));
						if($post_id) {
							if($link = get_permalink($post_id)) {
								wp_redirect( $link, 301 );
							}
						}
					}	
				}
			}
		}, 10, 2);
	}

}