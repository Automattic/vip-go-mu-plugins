<?php
/**
 * Mock 'Debug Bar' data collector.
 *
 * @package query-monitor
 */

final class QM_Collector_Debug_Bar extends QM_Collector {

	public $id     = 'debug_bar';
	private $panel = null;

	public function set_panel( Debug_Bar_Panel $panel ) {
		$this->panel = $panel;
	}

	public function get_panel() {
		return $this->panel;
	}

	public function process() {
		$this->get_panel()->prerender();
	}

	public function is_visible() {
		return $this->get_panel()->is_visible();
	}

	public function render() {
		return $this->get_panel()->render();
	}

}

function register_qm_collectors_debug_bar() {

	global $debug_bar;

	if ( class_exists( 'Debug_Bar' ) || qm_debug_bar_being_activated() ) {
		return;
	}

	$collectors = QM_Collectors::init();
	$qm         = QueryMonitor::init();

	require_once $qm->plugin_path( 'classes/debug_bar.php' );

	$debug_bar = new Debug_Bar();
	$redundant = array(
		'debug_bar_actions_addon_panel', // Debug Bar Actions and Filters Addon
		'debug_bar_remote_requests_panel', // Debug Bar Remote Requests
		'debug_bar_screen_info_panel', // Debug Bar Screen Info
		'ps_listdeps_debug_bar_panel', // Debug Bar List Script & Style Dependencies
	);

	foreach ( $debug_bar->panels as $panel ) {
		$panel_id = strtolower( sanitize_html_class( get_class( $panel ) ) );

		if ( in_array( $panel_id, $redundant, true ) ) {
			continue;
		}

		$collector = new QM_Collector_Debug_Bar();
		$collector->set_id( "debug_bar_{$panel_id}" );
		$collector->set_panel( $panel );

		$collectors->add( $collector );
	}

}

function qm_debug_bar_being_activated() {
	// phpcs:disable

	if ( ! is_admin() ) {
		return false;
	}

	if ( ! isset( $_REQUEST['action'] ) ) {
		return false;
	}

	if ( isset( $_GET['action'] ) ) {

		if ( ! isset( $_GET['plugin'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			return false;
		}

		if ( 'activate' === $_GET['action'] && false !== strpos( wp_unslash( $_GET['plugin'] ), 'debug-bar.php' ) ) {
			return true;
		}

	} elseif ( isset( $_POST['action'] ) ) {

		if ( ! isset( $_POST['checked'] ) || ! is_array( $_POST['checked'] ) || ! isset( $_POST['_wpnonce'] ) ) {
			return false;
		}

		if ( 'activate-selected' === wp_unslash( $_POST['action'] ) && in_array( 'debug-bar/debug-bar.php', wp_unslash( $_POST['checked'] ), true ) ) {
			return true;
		}

	}

	return false;
	// phpcs:enable
}

add_action( 'init', 'register_qm_collectors_debug_bar' );
