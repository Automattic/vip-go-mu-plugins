<?php

namespace Automattic\VIP\Debug;

require_once( __DIR__ . '/logger.php' );

function toggle_debug_mode() {
    if ( isset( $_GET['a8c-debug'] )
        && 'true' === $_GET['a8c-debug']
        && \is_proxied_request() ) {
			setcookie( 'vip-go-cb', '1', time() + 2 * HOUR_IN_SECONDS );
			nocache_headers();

			// The priority is 0 as the cookie needs to be set before the check in wpcom_vip_qm_enable(), even if the validation takes place much later.
			add_action( 'plugins_loaded', function() {
				$user = get_user_by( 'slug', 'wpcomvip' );
				// The cookie value is generated using the same parameters that QM uses internally in QM_Dispatcher_Html::ajax_on() -> query-monitor/dispatchers/Html.php .
				$cookie = \wp_generate_auth_cookie( $user->ID, time() + ( 2 * DAY_IN_SECONDS ), 'logged_in' );
				setcookie( 'query_monitor_' . COOKIEHASH, $cookie, time() + 2 * HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
			}, 0 );

			add_action( 'init', function() {
				// Redirect to the same page without the activation handler.
				\wp_safe_redirect( remove_query_arg( 'a8c-debug' ) );
				exit;
			});
    } elseif ( isset( $_GET['a8c-debug'] )
            && 'false' === $_GET['a8c-debug'] ) {
				setcookie( 'vip-go-cb', '', time() - 2 * HOUR_IN_SECONDS );

				add_action( 'init', function() {
					setcookie( 'query_monitor_' . COOKIEHASH, time() - 2 * HOUR_IN_SECONDS );
					// Redirect to the same page without the deactivation handler.
					\wp_safe_redirect( remove_query_arg( 'a8c-debug' ) );
					exit;
				});
	}
}

function debug_bar() {
    if ( ! is_user_logged_in()
		&& isset( $_COOKIE['vip-go-cb'] )
		&& '1' === $_COOKIE['vip-go-cb']
        && \is_proxied_request() ) {
			?>
		    <div id="vip-go-debug-bar">
            </div>
            <style>
		    #vip-go-debug-bar {
		    	z-index: 9991;
		    	color: #ddd
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

		    #vip-go-debug-bar:before {
		    	content: 'Debug Mode';
		    	text-transform: uppercase;
		    	background: purple;
		    	color: #fff;
		    	letter-spacing: 0.2em;
		    	text-shadow: none;
		    	font-size: 9px;
		    	font-weight: bold;
		    	padding: 0 10px;
		    	float: right;
		    	cursor: pointer;
		    }

            @media print {
		    	div#vip-go-debug-bar {
		    		display: none !important;
		    	}
		    }
            </style>
            <?php
        }

}
add_action( 'wp_footer', __NAMESPACE__ . '\debug_bar', 100 );
