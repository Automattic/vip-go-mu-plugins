<?php
/**
 * Data collector class
 */
class QM_Collector_ObjectCache extends QM_Collector {

	public $id = 'object_cache';

	public function name() {
		return __( 'Object Cache', 'query-monitor' );
	}

	public function process() {
		global $wp_object_cache;
		if ( ! method_exists( $wp_object_cache, 'get_stats' ) ) {
			return;
		}
		$stats = $wp_object_cache->get_stats();

		$this->data['totals'] = $stats['totals'] ?? null;
		$this->data['stats']  = $stats['stats'] ?? null;
	}
}
