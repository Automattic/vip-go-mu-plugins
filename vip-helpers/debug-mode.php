<?php

function toggle_debug_mode() {
    if ( isset( $_GET['a8c-debug'] )
        && 'true' === $_GET['a8c-debug']
        && is_proxied_automattician() ) {
        setcookie( 'vip-go-cb', '1', time() + 2 * HOUR_IN_SECONDS );
        nocache_headers();
    } elseif ( isset( $_GET['a8c-debug'] )
            && 'false' === $_GET['a8c-debug'] ) {
        setcookie( 'vip-go-cb', '', time() - 2 * HOUR_IN_SECONDS );
    }
}