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
		// this space intentionally left blank
	}
}
