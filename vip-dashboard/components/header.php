<?php

function render_vip_dashboard_header() {
	?>

	<div class="top-header">
		<h1>
			<a href="https://wpvip.com" target="_blank">
				<img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . '../assets/img/wpcom-vip-logo.svg' ); ?>"
					alt="WordPress VIP" class="top-header__logo"/>
			</a>
		</h1>
	</div>

	<?php
}
