<?php
/**
 * Output class
 *
 * Class QM_Output_ObjectCache
 */
class QM_Output_ObjectCache extends QM_Output_Html {

	public function __construct( QM_Collector $collector ) {
		parent::__construct( $collector );

		add_filter( 'qm/output/menu_class', array( $this, 'admin_class' ) );
		add_filter( 'qm/output/menus', array( $this, 'admin_menu' ), 101 );
	}

	/**
	 * Outputs data in the footer
	 */
	public function output() {
		?>
		<div class="qm qm-non-tabular" id="<?php echo esc_attr( $this->collector->id() ); ?>">
		<div id="object-cache-stats">
			<?php
			global $wp_object_cache;
			$wp_object_cache->stats();
			?>
		</div>
		</div>
		<?php
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

		$menu[] = $this->menu( array(
			'id'    => 'qm-object_cache',
			'href'  => '#qm-object_cache',
			'title' => __( 'Object Cache', 'query-monitor' ),
		));

		return $menu;
	}
}
