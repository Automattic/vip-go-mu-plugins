<?php
/**
 * Output class
 *
 * Class QM_Output_VIPConcat
 */
class QM_Output_VIPConcat extends QM_Output_Html {

	public function __construct( QM_Collector $collector ) {
		parent::__construct( $collector );

		add_filter( 'qm/output/menu_class', array( $this, 'admin_class' ) );
		add_filter( 'qm/output/menus', array( $this, 'admin_menu' ), 101 );
	}

	/**
	 * Outputs data in the footer
	 */
	public function output() {
		$data = $this->collector->get_data();

		if ( empty( $data->logs ) ) {
			$this->before_non_tabular_output();

			$notice = sprintf(
				/* translators: %s: Link to help article */
				__( 'No data logged. <a href="%s">Read about the WordPress VIP Platform\'s file concatenation feature</a>.', 'query-monitor' ),
				'https://docs.wpvip.com/vip-go-mu-plugins/file-concatenation-and-minification/'
			);
			echo $this->build_notice( $notice ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped --

			$this->after_non_tabular_output();

			return;
		}

		$levels = array();

		foreach ( $this->collector->get_levels() as $level ) {
			if ( $data->counts[ $level ] ) {
				$levels[ $level ] = sprintf(
					'%s (%d)',
					ucfirst( $level ),
					$data->counts[ $level ]
				);
			} else {
				$levels[ $level ] = ucfirst( $level );
			}
		}
		$this->before_tabular_output();

		$level_args = array(
			'all' => sprintf(
				/* translators: %s: Total number of items in a list */
				__( 'All (%d)', 'query-monitor' ),
				count( $data->logs )
			),
		);

		echo '<thead>';
		echo '<tr>';
		echo '<th scope="col" class="qm-filterable-column">';
		echo $this->build_filter( 'type', $levels, __( 'Level', 'query-monitor' ), $level_args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped --
		echo '</th>';
		echo '<th scope="col" class="qm-col-message">' . esc_html__( 'Message', 'query-monitor' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		foreach ( $data->logs as $row ) {

			$row_attr                 = array();
			$row_attr['data-qm-type'] = $row['level'];

			$attr = '';

			foreach ( $row_attr as $a => $v ) {
				$attr .= ' ' . $a . '="' . esc_attr( $v ) . '"';
			}

			$is_warning = in_array( $row['level'], $this->collector->get_warning_levels(), true );

			if ( $is_warning ) {
				$class = 'qm-warn';
			} else {
				$class = '';
			}

			echo '<tr' . $attr . ' class="' . esc_attr( $class ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped --
			echo '<td class="qm-nowrap">';

			if ( $is_warning ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo QueryMonitor::icon( 'warning' );
			} else {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo QueryMonitor::icon( 'blank' );
			}

			echo esc_html( ucfirst( str_replace( 'vip_concat_', '', $row['level'] ) ) );
			echo '</td>';
			printf(
				'<td><pre>%s</pre></td>',
				esc_html( $row['message'] )
			);

			echo '</tr>';

		}

		echo '</tbody>';

		$this->after_tabular_output();
	}

	/**
	 * Adds data to top admin bar
	 *
	 * @param array $title
	 *
	 * @return array
	 */
	public function admin_title( array $title ) {
		return $title;
	}

	/**
	 * @param array $class
	 *
	 * @return array
	 */
	public function admin_class( array $class ) {
		$class[] = 'qm-vip_concat';
		return $class;
	}

	public function admin_menu( array $menu ) {

		$menu[] = $this->menu( array(
			'id'    => 'qm-vip_concat',
			'href'  => '#qm-vip_concat',
			'title' => __( 'VIP JS/CSS Concat', 'query-monitor' ),
		));

		return $menu;
	}
}
