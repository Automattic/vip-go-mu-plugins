<?php

namespace Automattic\VIP\Debug;

require_once( __DIR__ . '/logger.php' );

function toggle_debug_mode() {
    if ( isset( $_GET['a8c-debug'] )
        && 'true' === $_GET['a8c-debug']
        && \is_proxied_request() ) {
            setcookie( 'vip-go-cb', '1', time() + 2 * HOUR_IN_SECONDS );
			nocache_headers();
			// Redirect to the same page without the activation handler.
			add_action( 'init', function(){
				\wp_safe_redirect( remove_query_arg( 'a8c-debug' ) );
			} );
    } elseif ( isset( $_GET['a8c-debug'] )
            && 'false' === $_GET['a8c-debug'] ) {
                setcookie( 'vip-go-cb', '', time() - 2 * HOUR_IN_SECONDS );
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
            // @todo Enable Query Monitor and Debug Bar.
        }

}
add_action( 'wp_footer', __NAMESPACE__ . '\debug_bar', 100 );
