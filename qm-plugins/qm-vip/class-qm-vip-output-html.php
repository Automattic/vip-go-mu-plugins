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
		$data       = $this->collector->get_data();
		$commit_url = 'https://github.com/automattic/vip-go-mu-plugins/commit/' . $data['version']['commit'];
		?>
		<div class="qm qm-non-tabular" id="<?php echo esc_attr( $this->collector->id ); ?>">
			<h3><strong>MU-Plugins Stack: </strong><?php echo esc_html( $data['mu-plugins-stack'] ); ?></h3>
			<p><a href="<?php echo esc_url( $commit_url ); ?>" alt="GitHub URL of the commit that the stack was deployed from."><i><strong>Last modified: </strong><?php echo esc_html( $data['version']['date'] ); ?></i></a></p>
		</div>
		<?php
	}
}
