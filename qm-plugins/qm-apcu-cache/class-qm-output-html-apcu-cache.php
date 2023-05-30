<?php

class QM_Output_Html_Apcu_Cache extends \QM_Output_Html {

	public function __construct( \QM_Collector $collector ) {
		parent::__construct( $collector );

		add_filter( 'qm/output/menus', array( $this, 'admin_menu' ), 110 );
	}

	public function admin_menu( array $menu ) {

		$menu[] = $this->menu( array(
			'id'    => 'qm-apcu-cache',
			'href'  => '#qm-apcu-cache',
			'title' => esc_html__( 'APCU Hot-Cache', 'query-monitor' ),
		));

		return $menu;
	}

	public function output() {
		?>
		<div class="qm qm-non-tabular" id="qm-<?php echo esc_attr( $this->collector->id ); ?>" role="tabpanel" aria-labelledby="qm-<?php echo esc_attr( $this->collector->id ); ?>-caption" tabindex="-1">
			<div id="apcu-cache-stats">
				<?php
				global $apc_cache_interceptor;
				if ( ! isset( $apc_cache_interceptor ) || ! is_object( $apc_cache_interceptor ) ) {
					echo '<h2>APCU Hot-Caching is currently disabled</h2>';
					return;
				}
				$apc_cache_interceptor->stats();
				?>
			</div>
		</div>
		<?php
	}
}
