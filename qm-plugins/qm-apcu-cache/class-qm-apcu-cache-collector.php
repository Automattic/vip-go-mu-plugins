<?php

class QM_Apcu_Cache_Collector extends \QM_Collector {
	public $id = 'qm-apcu-cache';

	public function name() {
		return esc_html__( 'APCU Hot-Cache', 'query-monitor' );
	}
}
