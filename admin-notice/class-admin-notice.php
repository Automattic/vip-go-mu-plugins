<?php

namespace Automattic\VIP\Admin_Notice;

class Admin_Notice {
	public $message;
	public $start_date;
	public $end_date;

	/**
	 * Create AdminNotice
	 *
	 * @param {string} $message - the text to be displayed
	 * @param {string} $start_date - the date* since the notice should start to show up
	 * @param {string} $end_date - the date* till the notice should show up
	 *
	 * * Date Format should follow 'Day-Month-Year Hour:Minute' format.
	 */
	public function __construct( string $message, string $start_date, string $end_date ) {
		$this->message = $message;
		$this->start_date = new \DateTime( $start_date, new \DateTimeZone( 'UTC' ) );
		$this->end_date = new \DateTime( $end_date, new \DateTimeZone( 'UTC' ) );
	}
}
