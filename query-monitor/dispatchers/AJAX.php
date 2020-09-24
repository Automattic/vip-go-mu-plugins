<?php
/**
 * Ajax request dispatcher.
 *
 * @package query-monitor
 */

class QM_Dispatcher_AJAX extends QM_Dispatcher {

	public $id = 'ajax';

	public function __construct( QM_Plugin $qm ) {
		parent::__construct( $qm );

		add_action( 'shutdown', array( $this, 'dispatch' ), 0 );

	}

	public function init() {

		if ( ! self::user_can_view() ) {
			return;
		}

		if ( QM_Util::is_ajax() ) {
			ob_start();
		}

		parent::init();
	}

	public function dispatch() {

		if ( ! $this->should_dispatch() ) {
			return;
		}

		$this->before_output();

		foreach ( $this->get_outputters( 'headers' ) as $id => $output ) {
			$output->output();
		}

		$this->after_output();

	}

	protected function before_output() {

		require_once $this->qm->plugin_path( 'output/Headers.php' );

		foreach ( glob( $this->qm->plugin_path( 'output/headers/*.php' ) ) as $file ) {
			require_once $file;
		}
	}

	protected function after_output() {

		# flush once, because we're nice
		if ( ob_get_length() ) {
			ob_flush();
		}

	}

	public function is_active() {

		if ( ! QM_Util::is_ajax() ) {
			return false;
		}

		if ( ! self::user_can_view() ) {
			return false;
		}

		# If the headers have already been sent then we can't do anything about it
		if ( headers_sent() ) {
			return false;
		}

		# Don't process if the minimum required actions haven't fired:
		if ( is_admin() ) {
			if ( ! did_action( 'admin_init' ) ) {
				return false;
			}
		} else {
			if ( ! did_action( 'wp' ) ) {
				return false;
			}
		}

		return true;

	}

}

function register_qm_dispatcher_ajax( array $dispatchers, QM_Plugin $qm ) {
	$dispatchers['ajax'] = new QM_Dispatcher_AJAX( $qm );
	return $dispatchers;
}

add_filter( 'qm/dispatchers', 'register_qm_dispatcher_ajax', 10, 2 );
