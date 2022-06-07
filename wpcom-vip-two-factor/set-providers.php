<?php

function wpcom_vip_have_twilio_keys() {
	return defined( 'TWILIO_SID' ) && ! empty( TWILIO_SID )
		&& defined( 'TWILIO_SECRET' ) && ! empty( TWILIO_SECRET );
}

add_filter( 'two_factor_providers', function( $p ) {
	if ( wpcom_vip_have_twilio_keys() ) {
		$p['Two_Factor_SMS'] = __DIR__ . '/sms-provider.php';
	}

	unset( $p['Two_Factor_Dummy'] );
	return $p;
} );
