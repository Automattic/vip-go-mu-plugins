<?php

class QM_Collector_Apcu_Cache extends \QM_Collector {
	public $id = 'apcu-cache';

	public function name() {
		return esc_html__( 'APCU Hot-Cache', 'query-monitor' );
	}
}
