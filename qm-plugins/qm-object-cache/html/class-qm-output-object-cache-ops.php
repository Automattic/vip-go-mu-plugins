<?php
/**
 * Output class
 *
 * Class QM_Output_Operations_ObjectCache_Ops
 */
class QM_Output_ObjectCache_Ops extends QM_Output_Html {

	public function __construct( QM_Collector $collector ) {
		global $wp_object_cache;
		if ( ! method_exists( $wp_object_cache, 'get_stats' ) ) {
			return;
		}

		parent::__construct( $collector );

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
		$this->output_filterable_table_col( 'Operation', array_keys( $ops ) );
		$this->output_sortable_table_col( 'Key' );
		$this->output_sortable_table_col( 'Size' );
		$this->output_sortable_table_col( 'Time' );
		$this->output_filterable_table_col( 'Group', array_keys( $groups ) );
		$this->output_sortable_table_col( 'Result' );
		echo '</tr>';
		echo '</thead>';

		echo '<tbody>';
		foreach ( $ops as $op_name => $data ) {
			foreach ( $data as $op ) {
				echo '<tr data-qm-operation="' . esc_attr( $op_name ) . '" data-qm-group="' . esc_attr( $op['group'] ) . '">';
				echo '<td class="qm-nowrap qm-ltr">' . esc_html( $op_name ) . '</td>';
				echo '<td class="qm-nowrap qm-ltr">' . esc_html( $op['key'] ) . '</td>';
				echo '<td class="qm-nowrap qm-ltr">' . esc_html( $this->process_size( $op['size'] ) ) . '</td>';
				echo '<td class="qm-nowrap qm-ltr">' . esc_html( $this->process_time( $op['time'] ) ) . '</td>';
				echo '<td class="qm-nowrap qm-ltr">' . esc_html( $op['group'] ) . '</td>';
				echo '<td class="qm-nowrap qm-ltr">' . esc_html( $this->process_result( $op['result'] ) ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody>';

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
				'title' => __( 'Operations', 'query-monitor' ),
			));
		}

		return $menu;
	}

	/**
	 * Returns in human readable size format
	 *
	 * @param $size int
	 * @return $size string
	 */
	public function process_size( $size ) {
		return size_format( $size, 2 );
	}

	/**
	 * Returns in human readable time format
	 *
	 * @param $time float
	 * @return $time string
	 */
	public function process_time( $time ) {
		return number_format_i18n( sprintf( '%0.1f', $time * 1000 ), 1 ) . 'ms';
	}

	/**
	 * Returns in human readable result
	 *
	 * @param $result string
	 * @return $result string
	 */
	public function process_result( $result ) {
		switch ( trim( $result ) ) {
			case 'not_in_memcache':
				$result = 'Not in Memcache';
				break;
			case 'memcache':
				$result = 'Found in Memcache';
				break;
			case '[mc already]':
				$result = 'Already in Memcache';
				break;
			case '[lc already]':
				$result = 'Local cache already';
				break;
		}

		return $result;
	}

	/**
	 * Outputs the table column header
	 *
	 * @param $title string
	 */
	public function output_sortable_table_col( $title ) {
		echo '<th scope="col" class="qm-sortable-column" role="columnheader">';
		echo $this->build_sorter( esc_html__( $title, 'query-monitor' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.I18n.NonSingularStringLiteralText
		echo '</th>';
	}

	/**
	 * Outputs the table column header
	 *
	 * @param $title string
	 */
	public function output_filterable_table_col( $title, $values, $args = [] ) {
		echo '<th scope="col" class="qm-filterable-column">';
		echo $this->build_filter( sanitize_title( strtolower( $title ) ), $values, esc_html__( $title, 'query-monitor' ), $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.I18n.NonSingularStringLiteralText
		echo '</th>';
	}
}
