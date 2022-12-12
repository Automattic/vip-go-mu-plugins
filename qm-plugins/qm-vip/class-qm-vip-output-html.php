<?php

class QM_VIP_Output extends QM_Output_Html {

	public function __construct( \QM_Collector $collector ) {
		parent::__construct( $collector );

		add_filter( 'qm/output/menus', array( $this, 'admin_menu' ), 1 );
	}

	public function admin_menu( array $menu ) {
		$menu[] = $this->menu( array(
			'id'    => 'qm-vip',
			'href'  => '#qm-vip',
			'title' => esc_html__( 'VIP', 'query-monitor' ),
		));

		return $menu;
	}

	public function output() {
		$data = $this->collector->get_data();
		?>
		<div class="qm qm-non-tabular" id="<?php echo esc_attr( $this->collector->id ); ?>">
			<h3><strong>MU-Plugins Branch: </strong><?php echo esc_html( $data['mu-plugins']['branch'] ); ?></h3>
			<?php
			if ( isset( $data['mu-plugins']['commit'] ) && isset( $data['mu-plugins']['date'] ) ) {
				echo '<p><a href="https://github.com/automattic/vip-go-mu-plugins/commit/' . rawurlencode( $data['mu-plugins']['commit'] ) .
				' target="_blank"><i><strong><span class="screen-reader-text">Open in new tab </span>Last modified: </strong>' .
				esc_html( $data['mu-plugins']['date'] ) . '</i></a></p>';
			}
			?>
		</div>
		<?php
	}
}
