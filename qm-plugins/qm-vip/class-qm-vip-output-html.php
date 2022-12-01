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
		if ( isset( $data['mu-plugins']['commit'] ) && isset( $data['mu-plugins']['date'] ) ) {
			$commit_date_html = '<p><a href="https://github.com/automattic/vip-go-mu-plugins/commit/' . rawurlencode( $data['mu-plugins']['commit'] ) . '" alt="GitHub URL of the commit that the stack was deployed from."><i><strong>Last modified: </strong>' . esc_html( $data['mu-plugins']['date'] ) . '</i></a></p>';
		}
		?>
		<div class="qm qm-non-tabular" id="<?php echo esc_attr( $this->collector->id ); ?>">
			<h3><strong>MU-Plugins Branch: </strong><?php echo esc_html( $data['mu-plugins']['branch'] ); ?></h3>
			<?php
			if ( $commit_date_html ) {
				echo wp_kses_post( $commit_date_html );
			}
			?>
		</div>
		<?php
	}
}
