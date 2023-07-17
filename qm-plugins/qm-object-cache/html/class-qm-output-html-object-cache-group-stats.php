<?php
/**
 * Output class
 *
 * Class QM_Output_Html_Object_Cache_Group_Stats
 */
class QM_Output_Html_Object_Cache_Group_Stats extends QM_Output_Html {

	/**
	 * The constructor for the class.
	 */
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
	 * Outputs group stats data in the footer.
	 *
	 * @return void
	 */
	public function output() {
		$data = $this->collector->get_data();
		if ( empty( $data ) ) {
			return;
		}

		$ops         = $data->operations ?? [];
		$groups      = $data->groups ?? [];
		$group_stats = $data->group_stats ?? [];
		$total       = array();

		$this->before_non_tabular_output();

		if ( $group_stats ) {
			foreach ( $group_stats as $operation_type => $operation_stats ) {

				$total[ $operation_type ] = array(
					'count' => 0,
					'time'  => 0,
					'size'  => 0,
				);

				$this->output_before_section( 'Group Stats for ' . $operation_type );
				echo '<thead>';
				echo '<tr>';

				$this->output_sortable_table_col( __( 'Group', 'qm-object-cache' ) );
				$this->output_sortable_table_col( __( 'Count', 'qm-object-cache' ) );
				$this->output_sortable_table_col( __( 'Time', 'qm-object-cache' ) );
				$this->output_sortable_table_col( __( 'Size', 'qm-object-cache' ) );

				echo '</tr>';
				echo '</thead>';
				echo '<tbody>';

				foreach ( $operation_stats as $group => $value ) {
					echo '<tr>';
					$this->output_table_cell( $group );
					$this->output_table_cell( $value['count'] );
					$this->output_table_cell( $this->process_time( $value['time'] ), $value['time'] );
					$this->output_table_cell( $this->process_size( $value['size'] ), $value['size'] );
					echo '</tr>';

					$total[ $operation_type ]['count'] += $value['count'];
					$total[ $operation_type ]['time']  += $value['time'];
					$total[ $operation_type ]['size']  += $value['size'];

				}

				echo '</tbody>';
				echo '<tfoot>';
				echo '<tr>';

				echo '<td>Totals:</td>';
				echo '<td>' . esc_html( $total[ $operation_type ]['count'] ) . '</td>';
				echo '<td>' . esc_html( $this->process_time( $total[ $operation_type ]['time'] ) ) . '</td>';
				echo '<td>' . esc_html( $this->process_size( $total[ $operation_type ]['size'] ) ) . '</td>';

				echo '</tfoot>';
				$this->output_after_section();
			}

			$this->after_non_tabular_output();
		}
	}

	/**
	 * Adds class names to the Query Monitor menu.
	 *
	 * @param array $class
	 *
	 * @return array
	 */
	public function admin_class( array $class ) {
		$class[] = 'qm-object_cache_group_stats';
		return $class;
	}

	/**
	 * Adds the Group Stats child menu to Query Monitor.
	 *
	 * @param array $menu
	 *
	 * @return array
	 */
	public function panel_menu( array $menu ) {
		if ( isset( $menu['object_cache'] ) ) {
			$menu['object_cache']['children'][] = $this->menu(
				array(
					'id'    => 'qm-object_cache_group_stats',
					'href'  => '#qm-object_cache_group_stats',
					'title' => __( 'Group Stats', 'qm-object-cache' ),
				)
			);
		}

		return $menu;
	}

	/**
	 * Returns bytes in human readable size format.
	 *
	 * @param $size int|null Raw size
	 *
	 * @return $size string Human readable size format
	 */
	public function process_size( ?int $size ) {
		return size_format( (int) $size, 2 );
	}

	/**
	 * Returns in human readable time format.
	 *
	 * @param float|null $time Time in microseconds.
	 *
	 * @return string $time Human readable time format in milliseconds.
	 */
	public function process_time( ?float $time ) {
		return number_format_i18n( sprintf( '%0.1f', (float) $time * 1000 ), 1 ) . 'ms';
	}

	/**
	 * Outputs a sortable table column header.
	 *
	 * @param string $title Title to use on column header
	 *
	 * @return void
	 */
	public function output_sortable_table_col( string $title ) {
		echo '<th scope="col" class="qm-sortable-column" role="columnheader">';
		echo $this->build_sorter( esc_html( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</th>';
	}

	/**
	 * Outputs a toggleable table cell for arrays.
	 *
	 * @param array $array Array to be outputted in table cell
	 *
	 * @return void
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
	 *
	 * @return void
	 */
	public function output_table_cell( ?string $value, int $weight = null ) {
		if ( $weight ) {
			$weight = ' data-qm-sort-weight="' . esc_attr( $weight ) . '"';
		}
		echo '<td class="qm-nowrap qm-ltr"' . $weight . '>' . esc_html( $value ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Outputs the start of a table section.
	 *
	 * @param string $heading The section heading to use.
	 *
	 * @return void
	 */
	public function output_before_section( string $heading = '' ) {
		echo '<section>';

		if ( '' !== $heading ) {
			echo '<h3>' . esc_html( $heading ) . '</h3>';
		}

		echo '<table class="qm-sortable">';
	}

	/**
	 * Outputs the end of a table section.
	 *
	 * @return void
	 */
	public function output_after_section() {
		echo '</table>';
		echo '</section>';
	}

}
