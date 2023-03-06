<?php

if ( ! defined( 'VIP_GO_APP_ENVIRONMENT' ) || 'production' === constant( 'VIP_GO_APP_ENVIRONMENT' ) ) {
	return;
}

add_action( 'wp_footer', 'vip_do_non_prod_bar', 10000 );
add_action( 'admin_footer', 'vip_do_non_prod_bar', 10000 );

function vip_do_non_prod_bar() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	if ( apply_filters( 'vip_show_non_prod_bar', true ) ) :
		?>
		<div id="vip-non-prod-bar">
			<span>
				<strong><?php echo esc_html( constant( 'VIP_GO_APP_ENVIRONMENT' ) ); ?></strong>
			</span>
		</div>
		<script>
			const nonProdBar = document.querySelector('#vip-non-prod-bar');
			nonProdBar.addEventListener( 'click', function() {
				this.classList.toggle('which-env');
			} );

			const sandboxedBar = document.querySelector('#wpcom-sandboxed-bar');
			const debugBar = document.querySelector('#a8c-debug-flag');
			if ( ( sandboxedBar && debugBar ) || ( ! sandboxedBar && debugBar ) ) {
				// Account for proper stacking of the debug bar
				nonProdBar.style.bottom = '85px';
			}
		</script>
		<style>
		#vip-non-prod-bar {
			z-index: 9991;
			font-family: 'Helvetica Neue',Arial,Helvetica,sans-serif;
			font-size:14px;
			left: 0;
			bottom: 55px;
			position:fixed;
			margin:0;
			padding: 0 20px;
			width: 100%;
			height: 28px;
			line-height: 28px;
		}
		#vip-non-prod-bar span {
			display: none;
			float: left;
			padding: 0 10px;
			background: #fff;
			color: #333;
		}

		#vip-non-prod-bar:before {
			content: 'Non-production';
			text-transform: uppercase;
			background: #4c2c92;
			color: #fff;
			letter-spacing: 0.2em;
			text-shadow: none;
			font-size: 9px;
			font-weight: bold;
			padding: 0 10px;
			float: left;
			cursor: pointer;
		}
		#vip-non-prod-bar.which-env span {
			display: inline-block;
		}
		@media print {
			div#vip-non-prod-bar {
				display: none !important;
			}
		}
		</style>
		<?php
	endif;
}
