<?php

if ( ! WPCOM_SANDBOXED ) {
	return;
}

add_action( 'wp_footer', 'wpcom_do_sandbox_bar', 100 );
add_action( 'admin_footer', 'wpcom_do_sandbox_bar', 100 );

function wpcom_do_sandbox_bar() {
	if ( is_user_logged_in() && ! is_admin_bar_showing() ) {
		return;
	}

	if ( apply_filters( 'wpcom_show_sandbox_bar', false ) ) :
		$template   = get_template();
		$stylesheet = get_stylesheet();
		$theme      = ( $stylesheet != $template ) ? sprintf( '%s => %s', $stylesheet, $template ) : $template;

		$debug_info = array(
			'sandbox' => str_replace( '.wordpress.com', '', php_uname( 'n' ) ),
			'theme'   => $theme,
			'your ip' => $_SERVER['REMOTE_ADDR'] ?? '', // phpcs:ignore
		);

		$debug_info = apply_filters( 'wpcom_sandbox_bar_debug_info', $debug_info );

		?>
		<div id="wpcom-sandboxed-bar">
			<?php foreach ( $debug_info as $debug_name => $debug_value ) : ?>
				<span>
					<strong><?php echo esc_html( $debug_name ); ?></strong>
					<?php echo esc_html( $debug_value ); ?>
				</span>
			<?php endforeach; ?>
		</div>
		<script>
			document.querySelector('#wpcom-sandboxed-bar').addEventListener( 'click', function() {
				this.classList.toggle('sbx-debug');
			} );
		</script>
		<style>
		#wpcom-sandboxed-bar {
			z-index: 9991;
			color:<?php echo esc_html( apply_filters( 'wpcom_sandbox_bar_debug_info_color', '#ddd' ) ); ?>;
			font-family: 'Helvetica Neue',Arial,Helvetica,sans-serif;
			font-size:14px;
			bottom: 15px;
			left: 0;
			position:fixed;
			margin:0;
			padding: 0 20px;
			width: 100%;
			height: 28px;
			line-height: 28px;
		}
		#wpcom-sandboxed-bar span {
			display: none;
			float: left;
			padding: 0 10px;
			background: #fff;
			color: #333;
		}

		#wpcom-sandboxed-bar:before {
			content: 'Sandboxed';
			text-transform: uppercase;
			background: #d54e21;
			color: #fff;
			letter-spacing: 0.2em;
			text-shadow: none;
			font-size: 9px;
			font-weight: bold;
			padding: 0 10px;
			float: left;
			cursor: pointer;
		}
		#wpcom-sandboxed-bar.sbx-debug span {
			display: inline-block;
		}
		@media print {
			div#wpcom-sandboxed-bar {
				display: none !important;
			}
		}
		</style>
		<?php
	endif;
}
add_filter( 'wpcom_show_sandbox_bar', '__return_true' );
