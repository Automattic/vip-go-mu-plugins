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
		<div class="qm qm-non-tabular" id="<?php echo esc_attr( $this->collector->id() ); ?>">
			<div class="qm-boxed"><h3>
			<?php
			printf(
				wp_kses(
					/* translators: 1. uncompressed size 2. compressed size */
					__( 'Total size: <strong>%1$s</strong> (uncompressed), %2$s (estimated compression)', 'qm-monitor' ),
					[ 'strong' => [] ]
				),
				esc_html( size_format( $data['total_size'], 2 ) ),
				esc_html( size_format( $data['total_size_comp'], 2 ) )
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
						'<td class="qm-ltr">%1$s</td><td class="qm-ltr qm-num">%2$d</td><td class="qm-ltr qm-num">%3$s</td>',
						esc_html( $option->name ),
						esc_html( $option->size ),
						esc_html( size_format( $option->size, 2 ) )
					);
					echo '</tr>';
				}
				?>
				</tbody>
			</table>
			<?php if ( file_exists( WPMU_PLUGIN_DIR . '/wp-cli/alloptions.php' ) ) { ?>
			<div class="qm-boxed">
				<ul>
					<li>
					<?php
					printf(
						/* translators: 1. WP-CLI command */
						esc_html__( 'use %s to disable autoload for given option', 'qm-monitor' ),
						'<code>wp option autoload set &lt;option_name&gt; no</code>'
					);
					?>
					</li>
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
				/* translators: 1. size 2. size unit */
				_x( '%1$s<small> %2$s opts</small>', 'size of alloptions', 'query-monitor' ),
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
		$data = $this->collector->get_data();
		if ( $data['total_size_comp'] > MB_IN_BYTES * .8 ) {
			$class[] = 'qm-warning';
		}
		return $class;
	}


	public function admin_menu( array $menu ) {
		$title = __( 'Autoloaded Options', 'query-monitor' );

		$data = $this->collector->get_data();
		if ( $data['total_size_comp'] > MB_IN_BYTES * .8 ) {
			$title = __( 'Autoloaded Options 🚩', 'query-monitor' );
		}

		$menu[] = $this->menu( array(
			'id'    => 'qm-alloptions',
			'href'  => '#qm-alloptions',
			'title' => $title,
		));

		return $menu;
	}
}
