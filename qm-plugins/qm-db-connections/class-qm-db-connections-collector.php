<?php

class QM_DB_Connections_Collector extends QM_Collector {

	/**
	 * @var string
	 */
	public $id = 'qm-db-connections';

	/**
	 * @return string
	 */
	public function name() {
		return esc_html__( 'DB Connections', 'query-monitor' );
	}

	/**
	 * @return void
	 */
	public function process() {
		foreach ( $GLOBALS as $global ) {
			if ( ! is_object( $global ) || ! get_class( $global ) || ! is_a( $global, 'wpdb' ) ) {
				continue;
			}

			if ( ! property_exists( $global, 'db_connections' ) ) {
				break;
			}

			if ( is_array( $global->db_connections ) && ! empty( $global->db_connections ) ) {
				$elapsed = 0;

				foreach ( $global->db_connections as $conn ) {
					$this->data['db_connections']['connections'][] = $conn;

					if ( isset( $conn['elapsed'] ) ) {
						$elapsed += $conn['elapsed'];
					}
				}

				$this->data['db_connections']['total_connection_time'] = $elapsed;
			}
		}
	}
}
