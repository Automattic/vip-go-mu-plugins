<?php

namespace WorDBless;

class WpDie {

	use Singleton;

	/**
	 * @var array<string, callable>
	 */
	private $core_handlers = array(
		'wp_die_ajax_handler'   => '_ajax_wp_die_handler',
		'wp_die_json_handler'   => '_json_wp_die_handler',
		'wp_die_jsonp_handler'  => '_json_wp_die_handler',
		'wp_die_xmlrpc_handler' => '_xmlrpc_wp_die_handler',
		'wp_die_xml_handler'    => '_xml_wp_die_handler',
	);

	private function __construct() {
		foreach ( array_keys( $this->core_handlers ) as $filter ) {
			add_filter( $filter, array( $this, 'change_handler' ), 10, 1 );
		}
	}

	public function change_handler( $function ) {
		return array( $this, 'handler' );
	}

	public function handler( $message, $title = '', $args = array() ) {
		$current_filter = current_filter();
		if ( ! array_key_exists( $current_filter, $this->core_handlers ) ) {
			return;
		}

		$args['exit'] = false;
		${$this->core_handlers[ $current_filter ]}( $message, $title, $args );
	}

}
