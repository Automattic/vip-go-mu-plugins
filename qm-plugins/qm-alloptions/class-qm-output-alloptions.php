<?php
/**
 * Output class
 *
 * Class QM_Output_AllOptions
 */
class QM_Output_AllOptions extends QM_Output_Html {

	public function __construct( QM_Collector $collector ) {
		parent::__construct( $collector );
		add_filter( 'qm/output/title', array( $this, 'admin_title' ), 101 );
		add_filter( 'qm/output/menu_class', array( $this, 'admin_class' ) );
		add_filter( 'qm/output/menus', array( $this, 'admin_menu' ), 101 );
	}

	/**
	 * Outputs data in the footer
	 */
	public function output() {
		$data = $this->collector->get_data();
		?>
		<div class="qm qm-non-tabular" id="<?php echo esc_attr($this->collector->id())?>">
			<div class="qm-boxed"><h3>
			<?php
			printf(
				'Total size: <strong>%s</strong> (uncompressed), %s (estimated compression)',
				size_format( $data['total_size'], 2 ),
				size_format( $data['total_size_comp'], 2 )
			);
			?>
			</h3></div>
			<table>
				<thead>
					<tr>
					<th scope="col"><?php esc_html_e( 'Option name', 'qm-monitor' ); ?></th>
					<th scope="col" class="qm-num"><?php esc_html_e( 'Size (bytes)', 'qm-monitor' ); ?></th>
					<th scope="col" class="qm-num"><?php esc_html_e( 'Size (human)', 'qm-monitor' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
					foreach ( $data['options'] as $option ) {
						echo '<tr>';
						printf(
							'<td class="qm-ltr">%s</td><td class="qm-ltr qm-num">%d</td><td class="qm-ltr qm-num">%s</td>',
							$option->name,
							$option->size,
							size_format( $option->size, 2 )
						);
						echo '</tr>';
					}
				?>
				</tbody>
			</table>
			<?php if ( file_exists( WPMU_PLUGIN_DIR . '/wp-cli/alloptions.php' ) ) { ?>
			<div class="qm-boxed">
				<ul>
					<li>use <code>wp option autoload set &lt;option_name&gt; no</code> to disable autoload for given option</li>
				</ul>
			</div>
			<?php } ?>
		</div>
		<?php
	}

	/**
	 * Adds data to top admin bar
	 *
	 * @param array $title
	 *
	 * @return array
	 */
	public function admin_title( array $title ) {
		$data = $this->collector->get_data();

		// Only show title info if size is risky
		if ( $data['total_size_comp'] > MB_IN_BYTES * .8 ) {

			list( $num, $unit ) = explode( ' ', size_format( $data['total_size_comp'], 1 ) );

			$title[] = sprintf(
				_x( '%s<small> %s opts</small>', 'size of alloptions', 'query-monitor' ),
				$num,
				$unit
			);
		}

		return $title;
	}

	/**
	 * @param array $class
	 *
	 * @return array
	 */
	public function admin_class( array $class ) {
		$class[] = 'qm-alloptions';
		return $class;
	}

	public function admin_menu( array $menu ) {

		$menu[] = $this->menu( array(
			'id'    => 'qm-alloptions',
			'href'  => '#qm-alloptions',
			'title' => __( 'Autoloaded Options', 'query-monitor' ),
		));

		return $menu;
	}
}