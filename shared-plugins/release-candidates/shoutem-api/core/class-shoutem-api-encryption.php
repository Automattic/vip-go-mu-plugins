<?php
/*
  Copyright 2011 by ShoutEm, Inc. (www.shoutem.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
class DummyEncryptor {

	function __construct() {
		//TODO generate warrning that plain text encription is used!!
	}

	function encrypt($data, $key) {
		return $data;
	}

	function decrypt($data, $key) {
		return $data;
	}
}

class AesEncryptor {

	function __construct() {
	}

	function encrypt($data, $key) {
		return SEAesCtr::encrypt($data, $key, 128);
	}

	function decrypt($data, $key) {
		return SEAesCtr::decrypt($data, $key, 128);
	}
}

class MyCryptEncryptor {

	function __construct() {
	}

	function encrypt($text, $key) {
		 $data = mcrypt_encrypt( MCRYPT_RIJNDAEL_128, $key, $text, MCRYPT_MODE_ECB, $this->get_iv() );
		 return base64_encode( $data );
	}

	function decrypt($data, $key) {
		$data = base64_decode( $data );
        return mcrypt_decrypt( MCRYPT_RIJNDAEL_128, $key, $data, MCRYPT_MODE_ECB, $this->get_iv() );
	}

	function get_iv() {
		if (isset($this->iv) == false) {
			$ivs = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB);
			$this->iv = mcrypt_create_iv( $ivs );
		}
		return $this->iv;
	}
}

class ShoutemApiEncryption {

	function __construct($key) {
		$this->key = $key;

		if(function_exists('mcrypt_list_algorithms')) {
			$this->encryptor = new MyCryptEncryptor();
		} else {
			$this->encryptor = new AesEncryptor();
		}

	}

	function encrypt($data) {
		return $this->encryptor->encrypt($data,$this->key);
	}

	function decrypt($data) {
		return $this->encryptor->decrypt($data,$this->key);
	}
}