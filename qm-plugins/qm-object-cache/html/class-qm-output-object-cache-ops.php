<?php
/**
 * Output class
 *
 * Class QM_Output_Operations_Object_Cache_Ops
 */
class QM_Output_Object_Cache_Ops extends QM_Output_Html {

	public function __construct( QM_Collector $collector ) {
		parent::__construct( $collector );

		global $wp_object_cache;
		if ( ! method_exists( $wp_object_cache, 'get_stats' ) ) {
			return;
		}

		add_filter( 'qm/output/menu_class', array( $this, 'admin_class' ) );
		add_filter( 'qm/output/panel_menus', array( $this, 'panel_menu' ), 99 );
	}

	/**
	 * Outputs data in the footer
	 */
	public function output() {
		$data = $this->collector->get_data();
		if ( empty( $data ) ) {
			return;
		}

		$ops    = $data['operations'] ?? [];
		$groups = $data['groups'] ?? [];

		$this->before_tabular_output();

		echo '<thead>';
		echo '<tr>';
		$this->output_filterable_table_col( __( 'Operation', 'qm-object-cache' ), array_keys( $ops ) );
		$this->output_sortable_table_col( __( 'Key', 'qm-object-cache' ) );
		$this->output_sortable_table_col( __( 'Size', 'qm-object-cache' ) );
		$this->output_sortable_table_col( __( 'Time', 'qm-object-cache' ) );
		$this->output_filterable_table_col( __( 'Group', 'qm-object-cache' ), $groups );
		$this->output_sortable_table_col( __( 'Result', 'qm-object-cache' ) );
		echo '</tr>';
		echo '</thead>';

		echo '<tbody>';
		$total = 0;
		foreach ( $ops as $op_name => $data ) {
			foreach ( $data as $op ) {
				echo '<tr data-qm-operation="' . esc_attr( $op_name ) . '" data-qm-group="' . esc_attr( $op['group'] ) . '">';
				$this->output_table_cell( $op_name );
				if ( is_array( $op['key'] ) ) {
					$this->maybe_output_toggle_table_cell_for_array( $op['key'] );
				} else {
					$this->output_table_cell( $op['key'] );
				}
				$this->output_table_cell( $this->process_size( $op['size'] ), $op['size'] );
				$this->output_table_cell( $this->process_time( $op['time'] ) );
				$this->output_table_cell( $op['group'] );
				$this->output_table_cell( $this->process_result( $op['result'] ) );
				echo '</tr>';
				$total++;
			}
		}
		echo '</tbody>';
		echo '<tfoot>';
		echo '<tr>';
		printf(
			'<td colspan="7">%1$s</td>',
			sprintf(
				/* translators: %s: Number of Object cache operations */
				esc_html( _nx( 'Total: %s', 'Total: %s', $total, 'Object cache operations', 'qm-object-cache' ) ),
				'<span class="qm-items-number">' . esc_html( number_format_i18n( $total ) ) . '</span>'
			)
		);
		echo '</tr>';
		echo '</tfoot>';

		$this->after_tabular_output();
	}

	/**
	 * @param array $class
	 *
	 * @return array
	 */
	public function admin_class( array $class ) {
		$class[] = 'qm-object_cache_ops';
		return $class;
	}

	/**
	 * @param array<string, mixed[]> $menu
	 * @return array<string, mixed[]>
	 */
	public function panel_menu( array $menu ) {
		if ( isset( $menu['object_cache'] ) ) {
			$menu['object_cache']['children'][] = $this->menu( array(
				'id'    => 'qm-object_cache_ops',
				'href'  => '#qm-object_cache_ops',
				'title' => __( 'Operations', 'qm-object-cache' ),
			));
		}

		return $menu;
	}

	/**
	 * Returns in human readable size format
	 *
	 * @param $size int|null     Raw size
	 * @return $size string Human readable size format
	 */
	public function process_size( ?int $size ) {
		return size_format( $size, 2 );
	}

	/**
	 * Returns in human readable time format.
	 *
	 * @param float|null $time   Raw time
	 * @return string $time Human readable time format
	 */
	public function process_time( ?float $time ) {
		return number_format_i18n( sprintf( '%0.1f', $time * 1000 ), 1 ) . 'ms';
	}

	/**
	 * Returns in human readable result.
	 *
	 * @param $result  string Memcached operation result
	 * @return $result string Human readable memcached operation result
	 */
	public function process_result( string $result ) {
		switch ( trim( $result ) ) {
			case 'not_in_memcache':
				$result = __( 'Not in Memcached', 'qm-object-cache' );
				break;
			case 'memcache':
				$result = __( 'Found in Memcached', 'qm-object-cache' );
				break;
			case '[mc already]':
				$result = __( 'Already in Memcached', 'qm-object-cache' );
				break;
			case '[lc already]':
				$result = __( 'Local cache already', 'qm-object-cache' );
				break;
		}

		return $result;
	}

	/**
	 * Outputs a sortable table column header.
	 *
	 * @param string $title Title to use on column header
	 */
	public function output_sortable_table_col( string $title ) {
		echo '<th scope="col" class="qm-sortable-column" role="columnheader">';
		echo $this->build_sorter( esc_html( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</th>';
	}

	/**
	 * Outputs a filterable table column header.
	 *
	 * @param string $title Title to use on column header
	 * @param array $values Groups to filter by
	 * @param array $args   Additional arguments to be passed in
	 */
	public function output_filterable_table_col( string $title, array $values, $args = [] ) {
		echo '<th scope="col" class="qm-filterable-column">';
		echo $this->build_filter( sanitize_title( strtolower( $title ) ), $values, esc_html( $title ), $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</th>';
	}

	/**
	 * Outputs a toggleable table cell for arrays.
	 *
	 * @param array $array Array to be outputted in table cell
	 */
	public function maybe_output_toggle_table_cell_for_array( array $array ) {
		if ( empty( $array ) ) {
			return;
		}

		if ( count( $array ) === 1 ) {
			$this->output_table_cell( $array[0] );
			return;
		}

		echo '<td class="qm-nowrap qm-ltr qm-has-toggle">';
		echo static::build_toggler(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<ol><li>' . esc_html( $array[0] ) . ' [+' . ( count( $array ) - 1 ) . ' more]</li>';
		unset( $array[0] );
		echo '<span class="qm-info qm-supplemental">';
		foreach ( $array as $element ) {
			echo '<li>' . esc_html( $element ) . '</li>';
		}
		echo '</span>';
		echo '</ol></td>';
	}

	/**
	 * Outputs a table cell.
	 *
	 * @param string $value Value to be outputted in table cell
	 * @param int|null $weight Weight by sorting priority
	 */
	public function output_table_cell( string $value, int $weight = null ) {
		if ( $weight ) {
			$weight = ' data-qm-sort-weight="' . esc_attr( $weight ) . '"';
		}
		echo '<td class="qm-nowrap qm-ltr"' . $weight . '>' . esc_html( $value ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
