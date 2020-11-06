<?php

namespace Automattic\VIP\Admin_Notice;

class Admin_Notice_Controller {
	private $notice_class = 'notice notice-info';
	private $all_notices = [];

	public function init() {
		add_action( 'admin_notices', [ $this, 'display_notices' ] );
	}

	public function add( Admin_Notice $notice ) {
		array_push( $this->all_notices, $notice );
	}

	public function display_notices() {
		$filtered_notices = $this->filter_notices_by_time( $this->all_notices );

		$html_notices = array_map( [ $this, 'convert_notice_to_html' ], $filtered_notices );

		foreach ( $html_notices as $html_notice ) {
			echo( wp_kses_post( $html_notice ) . "\n" );
		}
	}

	public function convert_notice_to_html( $notice ) {
		return sprintf( '<div class="%s"><p>%s</p></div>', esc_attr( $this->notice_class ), esc_html( $notice->message ) );
	}

	public function filter_notices_by_time( $input_notices ) {
		$now = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );

		return array_filter( $input_notices, function ( $notice ) use ( $now ) {
			return $notice->start_date < $now && $notice->end_date > $now;
		} );
	}
}
