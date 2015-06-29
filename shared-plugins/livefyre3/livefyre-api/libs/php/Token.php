<?php

define( 'LFTOKEN_MAX_AGE', 86400 );
include( 'JWT.php' );

class Livefyre_Token {
    static function from_user( $user, $max_age=LFTOKEN_MAX_AGE ) {
        $secret = $user->get_domain()->get_key();
        $args = array( 
            'domain' => $user->get_domain()->get_host(), 
            'user_id' => $user->get_uid(),
            'expires' => time() + $max_age
        );
        $dname = $user->get_display_name();
        if ( !empty( $dname ) ) {
            $args[ 'display_name' ] = $dname;
        }
        return JWT::encode( $args, $secret );
    }
}

function lfgetHmacsha1Signature( $key, $data ) {
        //convert binary hash to BASE64 string
    return base64_encode( lfhmacsha1( $key, $data ) );
}

// encrypt a base string w/ HMAC-SHA1 algorithm
function lfhmacsha1( $key, $data ) {
    $blocksize = 64;
    $hashfunc = 'sha1';
    if ( strlen( $key ) > $blocksize ) {
        $key=pack( 'H*', $hashfunc( $key ) );
    }
    $key = str_pad( $key,$blocksize,chr( 0x00 ) );
    $ipad = str_repeat( chr( 0x36 ),$blocksize );
    $opad = str_repeat( chr( 0x5c ),$blocksize );
    $hmac = pack( 'H*', $hashfunc( ( $key^$opad ) . pack( 'H*', $hashfunc( ( $key^$ipad ).$data ) ) ) );
    return $hmac;
}

function lfxor_these( $first, $second ) {
    $results = array();
    for ( $i = 0; $i < strlen( $first ); $i++ ) {
        array_push( $results, $first[ $i ]^$second[ $i ] );
    }
    return implode( $results );
}

function Livefyre_hasNoComma( $str ) {
    return !preg_match( '/\,/', $str );
}

function lftokenCreateData( $now, $duration, $args=array() ) {
    //Create the right data input for Livefyre authorization
    $filtered_args = array_filter( $args, 'Livefyre_hasNoComma' );
    if ( ( count( $filtered_args ) == 0 ) or ( count( $args ) > count( $filtered_args ) ) ) {
        return -1;
    }

    array_unshift( $filtered_args, "lftoken", $now, $duration );
    $data = implode( ',', $filtered_args );
    return $data;
}

function lftokenCreateToken( $data, $key ) {
    //Create a signed token from data
    $clientkey = hmacsha1( $key, "Client Key" );
    $clientkey_sha1 = sha1( $clientkey, true );
    $temp = hmacsha1( $clientkey_sha1, $data );
    $sig = lfxor_these( $temp, $clientkey );
    $base64sig = base64_encode( $sig );
    return base64_encode( implode( ",", array( $data,$base64sig ) ) );
}

function lftokenValidateSystemToken( $token, $key, $domain ) {
    // This replaces the below - it uses JWT to verify that the token is valid for user id = 'system'
    $payload = JWT::decode( $token, $key );
    $required = array( 'expires','user_id','domain' );
    foreach ( $required as $field_name ) {
        if ( !isset( $payload->$field_name ) ) {
            return false;
        }
    }
    if ( $domain != $payload->domain || $payload->expires < time() || $payload->user_id != 'system' ) {
        return false;
    }
    return true;
}

function lftokenValidateServerToken( $token, $key ) {
    $parts = explode( ',', $token );
    $signature = array_pop( $parts );
    $serverkey = hmacsha1( base64_decode( $key ), "Server Key" );
    $temp = base64_encode( hmacsha1( $serverkey, implode( ',', $parts ) ) );
    if ( count( $parts ) > 1 ) {
        $timestamp = strtotime( $parts[ 1 ] );
        $duration = $parts[ 2 ];
    } else {
        $timestamp = time() - 1;
        $duration = 0;
    }
    return ( $signature == $temp ) && ( time() - $timestamp < $duration );
}


function lftokenValidateResponse( $data, $response, $key ) {
    // This was a poorly chosen name, not a great interface.
    // Deprecated but here for backcompat
    return lftokenValidateServerToken( $data . ',' . $response, $key );
}

/*
//Generate a token:
$secret= 'Enter your secret key here';
$args=array( 'auth', 'Enter your fyre.co domain name here ( eg yourdomain.fyre.co )', 'Unique user id of authenticated user from your system' );
$data=lftokenCreateData( gmdate( 'c' ), 86400, $args );
$value= lftokenCreateToken( $data,base64_decode( $secret ) );
*/