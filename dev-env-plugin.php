<?php

add_filter( 'set_url_scheme', function( $url ) {
    $proto = $_SERVER[ 'HTTP_X_FORWARDED_PROTO' ] ?? '';

    if ( 'https' == $proto ) {
        return str_replace( 'http://', 'https://', $url );
    }
    return $url;
});
