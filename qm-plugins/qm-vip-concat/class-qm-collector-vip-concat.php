<?php
/**
 * Data collector class
 */
class QM_Collector_VIPConcat extends QM_Collector_Logger {

	public $id = 'vip_concat';

	public function set_up() {
		parent::set_up();
		foreach ( $this->get_levels() as $level ) {
			add_action( "qm/{$level}", array( $this, $level ), 10, 2 );
		}
	}

	/**
	 * @param mixed $message
	 * @param array<string, mixed> $context
	 * @phpstan-param LogMessage $message
	 * @return void
	 */
	public function vip_concat_info( $message, array $context = array() ) {
		$this->store( 'vip_concat_info', $message, $context );
	}
	public function vip_concat_warn( $message, array $context = array() ) {
		$this->store( 'vip_concat_warn', $message, $context );
	}

	/**
	 * @return array<int, string>
	 * @phpstan-return list<self::*>
	 */
	public function get_levels() {
		return array(
			'vip_concat_info',
			'vip_concat_warn',
		);
	}

	/**
	 * @return array<int, string>
	 * @phpstan-return list<self::*>
	 */
	public function get_warning_levels() {
		return array(
			'vip_concat_warn',
		);
	}
}
