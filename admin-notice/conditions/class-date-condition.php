<?php

namespace Automattic\VIP\Admin_Notice;

class Date_Condition implements Condition {

	private $start_date;
	private $end_date;
	private $decision_date;

	public function __construct( string $start_date, string $end_date, string $decision_date = 'now' ) {
		$this->start_date    = new \DateTime( $start_date, new \DateTimeZone( 'UTC' ) );
		$this->end_date      = new \DateTime( $end_date, new \DateTimeZone( 'UTC' ) );
		$this->decision_date = new \DateTime( $decision_date, new \DateTimeZone( 'UTC' ) );
	}

	public function evaluate() {
		return $this->start_date < $this->decision_date && $this->end_date > $this->decision_date;
	}
}
