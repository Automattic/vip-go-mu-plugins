<?php

function push_syndicate_encrypt( $data ) {

	$data = serialize( $data );
	return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5(PUSH_SYNDICATE_KEY), $data, MCRYPT_MODE_CBC, md5(md5(PUSH_SYNDICATE_KEY))));

}

function push_syndicate_decrypt( $data ) {

	$data = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5(PUSH_SYNDICATE_KEY), base64_decode($data), MCRYPT_MODE_CBC, md5(md5(PUSH_SYNDICATE_KEY))), "\0");
	if ( !$data )
		return false;

	return @unserialize( $data );

}