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
class ShoutemApiCredentials {

	function __construct($data,$session_id) {
		$this->data = $data;
		$this->session_id = $session_id;
	}

	function is_valid() {
		$user = $this->get_user();
		$password = $this->data['password'];
		return $password == $user->user_pass;
	}

	function get_user() {
		return get_userdata((int)$this->data['uid']);
	}

	function get_data() {
		return $this->data;
	}

}

class ShoutemApiAuthentication {

	function __construct($encryption_key) {
		$this->session_id_header = 'shoutem_api_session_id';
		$this->encryptor = new ShoutemApiEncryption($encryption_key);
	}

	function create_session_id($user) {
		$session_id_data = array(
			'header'	=>	$this->session_id_header,
			'username'	=> 	$user->user_login,
			'password'	=>	$user->user_pass,
			// 'timestamp'	=>	time()
			'uid'			=> $user->ID
		);
		$unencrypted_session_id = http_build_query($session_id_data);
		return $this->encryptor->encrypt($unencrypted_session_id);
	}

	function get_credentials($session_id) {
		$unencrypted_session_id = $this->encryptor->decrypt($session_id);
		$session_id_data = $this->parse_data_from_session_id($unencrypted_session_id);

		if(!isset($session_id_data['header']) || $session_id_data['header'] != $this->session_id_header) {
			return false;
		}
		return new ShoutemApiCredentials($session_id_data,$session_id);
	}

	function parse_data_from_session_id($unencrypted_session_id) {
		$exploded_unencrypted_session_id = explode('&',$unencrypted_session_id);
		$session_id_data = array();

		foreach($exploded_unencrypted_session_id as $keyval_pair) {
			$exploded_keyval_pair =	explode('=',$keyval_pair);
			$key = $exploded_keyval_pair[0];
			$val = null;
			if(count($exploded_keyval_pair)>1) {
				$val = urldecode($exploded_keyval_pair[1]);
			}
			$session_id_data[$key] = $val;
		}
		return $session_id_data;
	}

}