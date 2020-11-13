<?php

namespace Automattic\VIP\Admin_Notice;

class Admin_Notice_Controller {
	private $all_notices = [];

	public function add( Admin_Notice $notice ) {
		array_push( $this->all_notices, $notice );
	}

	public function display_notices() {
		$filtered_notices = array_filter( $this->all_notices, function ( $notice ) {
			return $notice->should_render();
		});

		foreach ( $filtered_notices as $notice ) {
			$notice->display();
		}
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'vip-admin-notice-script', plugins_url( '/js/script.js', __FILE__ ), [], '1.0' );
	}
}
