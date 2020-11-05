<?php

namespace Automattic\VIP\Admin_Notice;

// function sample_admin_notice__error() {
//     $class = 'notice notice-info';
//     $message = __( 'Irks! An error has occurred.', 'sample-text-domain' );

//     printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
// }
// add_action( 'admin_notices', 'sample_admin_notice__error' );


require_once __DIR__ . '/class-admin-notice.php';


class Admin_Notice_Controller {
	private $notice_class = 'notice notice-info';
	public $all_notices = [];

	public static function init() {
		$instance = new Admin_Notice_Controller();

		add_action( 'admin_notices', [ $instance, 'display_notices' ] );

		$instance->all_notices = [
			new Admin_Notice( 'WordPress 5.5.2 will be released on Friday, October 30th', '01-07-2020', '30-10-2020 15:00' ),
		];
	}

	public function display_notices() {
		$filtered_notices = $this->filter_notices_by_time( $this->all_notices );

		$html_notices = array_map( [ $this, 'convert_notice_to_html' ], $filtered_notices );

		foreach ( $html_notices as $html_notice ) {
			echo( $html_notice . "\n" );
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
