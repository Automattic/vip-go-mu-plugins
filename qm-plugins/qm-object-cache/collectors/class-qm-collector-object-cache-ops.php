<?php
/**
 * Data collector class
 */
class QM_Collector_Object_Cache_Ops extends QM_Collector {

	public $id = 'object_cache_ops';

	public function name() {
		return __( 'Operations', 'qm-object-cache' );
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

		$stats                  = $wp_object_cache->get_stats();
		$this->data->operations = $stats['operations'] ?? null;
		$this->data->groups     = $stats['groups'] ?? null;
	}
}
