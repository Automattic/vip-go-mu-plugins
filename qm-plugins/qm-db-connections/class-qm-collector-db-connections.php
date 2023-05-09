<?php

class QM_Collector_DB_Connections extends QM_Collector {

	/**
	 * @var string
	 */
	public $id = 'db-connections';

	/**
	 * @return string
	 */
	public function name() {
		return esc_html__( 'DB Connections', 'query-monitor' );
	}

	/**
	 * @return QM_Data
	 */
	public function get_storage(): QM_Data {
		return new QM_Data_DB_Connections();
	}

	/**
	 * @return void
	 */
	public function process() {
		if ( ! isset( $GLOBALS['wpdb'] ) || ! property_exists( $GLOBALS['wpdb'], 'db_connections' ) ) {
			return;
		}

		$db_connections = $GLOBALS['wpdb']->db_connections;
		if ( ! is_array( $db_connections ) || empty( $db_connections ) ) {
			return;
		}

		$elapsed = 0;
		foreach ( $db_connections as $conn ) {
			$this->data->db_connections['connections'][] = $conn;

			if ( isset( $conn['elapsed'] ) ) {
				$elapsed += $conn['elapsed'];
			}
		}
		$this->data->db_connections['total_connection_time'] = $elapsed;
	}
}
