<?php

namespace Automattic\VIP\Admin_Notice;

class Admin_Notice_Controller {
	private $all_notices = [];

	public function init() {
		add_action( 'admin_notices', [ $this, 'display_notices' ] );
	}

	public function add( Admin_Notice $notice ) {
		array_push( $this->all_notices, $notice );
	}

	public function display_notices() {
		$filtered_notices = array_filter( $this->all_notices, function ( $notice ) {
			return $notice->should_render();
		});

		$html_notices = array_map( function ( $notice ) {
			return $notice->to_html();
		}, $filtered_notices );

		foreach ( $html_notices as $html_notice ) {
			echo( wp_kses_post( $html_notice ) . "\n" );
		}
	}
}
