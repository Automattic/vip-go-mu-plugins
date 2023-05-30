<?php
/**
 * Data collector class
 */
class QM_Collector_Object_Cache_Slow_Ops extends QM_Collector {

	public $id = 'object_cache_slow_ops';

	public function name() {
		return __( 'Slow Operations', 'qm-object-cache' );
	}

	/**
	 * @return QM_Data
	 */
	public function get_storage(): QM_Data {
		return new QM_Data_Object_Cache();
	}

	public function process() {
		global $wp_object_cache;
		if ( ! method_exists( $wp_object_cache, 'get_stats' ) ) {
			return;
		}

		$stats                       = $wp_object_cache->get_stats();
		$this->data->slow_ops        = $stats['slow-ops'] ?? null;
		$this->data->slow_ops_groups = $stats['slow-ops-groups'] ?? null;
	}
}
