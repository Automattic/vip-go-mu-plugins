<?php

namespace Automattic\VIP\Security;

function remove_xmlrpc_pingback_ping( $methods ) {
    unset( $methods['pingback.ping'] );
    return $methods;
}

add_filter( 'xmlrpc_methods', 'remove_xmlrpc_pingback_ping' );
    