<?php

class QM_DB_Connections_Output extends QM_Output_Html {

	public function __construct( \QM_Collector $collector ) {
		parent::__construct( $collector );

		add_filter( 'qm/output/menus', array( $this, 'admin_menu' ), 99 );
	}

	public function admin_menu( array $menu ) {
		$menu[] = $this->menu( array(
			'id'    => 'qm-db-connections',
			'href'  => '#qm-db-connections',
			'title' => esc_html__( 'DB Connections', 'query-monitor' ),
		));

		return $menu;
	}

	public function output() {
		$data = $this->collector->get_data();
		$total_time = $this->format_elapsed_time( $data['db_connections']['total_connection_time'] );
		$connections = $data['db_connections']['connections'];
		?>
		<div class="qm qm-non-tabular" id="<?php echo esc_attr( $this->collector->id ); ?>">
			<h3><strong>Total connection time:</strong> <?php echo esc_html( $total_time ); ?></h3>
			<h3><strong>Total connections:</strong> <?php echo count( $connections ); ?></h3>
			<?php $this->display_connections( $connections ); ?>
		</div>
		<?php
	}

	/**
	 * Display a table of connections and their properties.
	 *
	 * @param array $connections Array of connections
	 */
	private function display_connections( $connections ) {
		if ( empty( $connections ) ) {
			return;
		}

		echo '<table class="qm-db-connections-table">
				<thead>
					<tr>
						<th>', esc_html__( 'Database Name', 'query-monitor' ), '</th>
						<th>', esc_html__( 'Host', 'query-monitor' ), '</th>
						<th>', esc_html__( 'Port', 'query-monitor' ), '</th>
						<th>', esc_html__( 'User', 'query-monitor' ), '</th>
						<th>', esc_html__( 'Name', 'query-monitor' ), '</th>
						<th>', esc_html__( 'Server State', 'query-monitor' ), '</th>
						<th>', esc_html__( 'Elapsed', 'query-monitor' ), '</th>
						<th>', esc_html__( 'Success', 'query-monitor' ), '</th>
						<th>', esc_html__( 'Queries', 'query-monitor' ), '</th>
						<th>', esc_html__( 'Lag', 'query-monitor' ), '</th>
					</tr>
				</thead>
			<tbody>';

		foreach( $connections as $connection ) {
			echo '<tr><td>' . esc_html( $connection['dbhname'] ) . '</td>';
			echo '<td>' . esc_html( $connection['host'] ) . '</td>';
			echo '<td>' . esc_html( $connection['port'] ) . '</td>';
			echo '<td>' . esc_html( $connection['user'] ) . '</td>';
			echo '<td>' . esc_html( $connection['name'] ) . '</td>';
			echo '<td>' . esc_html( $connection['server_state'] ) . '</td>';
			echo '<td>' . esc_html( $this->format_elapsed_time( $connection['elapsed'] ) ) . '</td>';
			echo '<td>' . esc_html( $connection['success'] ? 'true' : 'false' ) . '</td>';
			echo '<td>' . esc_html( $connection['queries'] ) . '</td>';
			echo '<td>' . esc_html( $connection['lag'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private function format_elapsed_time( $time ) {
		return number_format( sprintf( '%0.1f', $time * 1000 ), 1 ) . 'ms';
	}
}
