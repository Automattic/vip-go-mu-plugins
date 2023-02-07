<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Output class
 *
 * Class QM_Output_ObjectCache
 */
class QM_Output_ObjectCache extends QM_Output_Html {

	public function __construct( QM_Collector $collector ) {
		parent::__construct( $collector );

		add_filter( 'qm/output/menu_class', array( $this, 'admin_class' ) );
		add_filter( 'qm/output/menus', array( $this, 'admin_menu' ), 30 );
	}

	/**
	 * @return string
	 */
	public function name() {
		return __( 'Object Cache', 'qm-object-cache' );
	}


	/**
	 * Outputs data in the footer
	 */
	public function output() {
		global $wp_object_cache;
		if ( ! method_exists( $wp_object_cache, 'get_stats' ) ) {
			echo '<div class="qm qm-non-tabular" id="' . esc_attr( $this->collector->id() ) . '">';
			echo '<div id="object-cache-stats">';
			$wp_object_cache->stats();
			echo '</div></div>';
			return;
		}

		$data = $this->collector->get_data();
		$this->before_non_tabular_output();

		$totals = $data['totals'] ?? false;
		if ( $totals ) {
			$this->output_before_section( 'Totals' );
			if ( isset( $totals['query_time'] ) ) {
				$this->output_table_row( 'Query Time', number_format_i18n( sprintf( '%0.1f', $totals['query_time'] * 1000 ), 1 ) . 'ms' );
			}
			if ( isset( $totals['size'] ) ) {
				$this->output_table_row( 'Size', size_format( $totals['size'], 2 ) );
			}
			$this->output_after_section();
		}

		$stats = $data['stats'] ?? false;
		if ( $stats ) {
			$this->output_before_section( 'Operation Counts' );
			foreach ( $stats as $stat => $value ) {
				if ( $value > 0 ) {
					$this->output_table_row( $stat, number_format_i18n( $value ) );
				}
			}
			$this->output_after_section();
		}

		$this->after_non_tabular_output();
	}

	/**
	 * @param array $class
	 *
	 * @return array
	 */
	public function admin_class( array $class ) {
		$class[] = 'qm-object_cache';
		return $class;
	}

	public function admin_menu( array $menu ) {
		$menu[ $this->collector->id ] = $this->menu( array(
			'id'    => $this->collector->id,
			'href'  => '#qm-object_cache',
			'title' => __( 'Object Cache', 'qm-object-cache' ),
		));

		return $menu;
	}

	/**
	 * Outputs a table row with a key-value pairing.
	 *
	 * @param string $title Title of table row
	 * @param string $value Value of table row
	 */
	public function output_table_row( string $title, string $value ) {
		echo '<tr>';
		echo '<th scope="row">' . esc_html( $title ) . '</th>';
		echo '<td>' . esc_html( $value ) . '</td>';
		echo '</tr>';
	}

	/**
	 * Outputs the beginning of a table section.
	 *
	 * @param string $heading Heading of table
	 */
	public function output_before_section( string $heading ) {
		echo '<section>';
		if ( $heading ) {
			echo '<h3>' . esc_html( $heading ) . '</h3>';
		}
		echo '<table>';
		echo '<thead class="qm-screen-reader-text">';
		echo '<tr>';
		echo '<th scope="col">' . esc_html__( 'Property', 'qm-object-cache' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Value', 'qm-object-cache' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';
	}

	/**
	 * Outputs the end of a table section.
	 */
	public function output_after_section() {
		echo '</tbody>';
		echo '</table>';
		echo '</section>';
	}

}
