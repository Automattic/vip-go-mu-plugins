<?php

class Wpdb_Mock {
	public string $table;
	public string $base_prefix = 'wp_';

	public array $cached_tables = array();

	public function add_table( $dataset, $table ) {
		$this->cached_tables[ $table ] = $dataset;
	}
}
