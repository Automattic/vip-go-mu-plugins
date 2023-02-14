<?php
/**
 * Data collector class
 */
class QM_Collector_Object_Cache_Slow_Ops extends QM_Collector {

	public $id = 'object_cache_slow_ops';

	public function name() {
		return __( 'Slow Operations', 'qm-object-cache' );
	}

	public function process() {
		global $wp_object_cache;
		if ( ! method_exists( $wp_object_cache, 'get_stats' ) ) {
			return;
		}

		$stats                         = $wp_object_cache->get_stats();
		$this->data['slow-ops']        = $stats['slow-ops'] ?? null;
		$this->data['slow-ops-groups'] = $stats['slow-ops-groups'] ?? null;
	}
}
