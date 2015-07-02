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
class ShoutemApiCaching {

	public function __construct($options) {
		$this->options = $options;
		//action that gets called when a post gets published
		add_action('publish_post', array($this,'on_post_published'), -9999);
		add_action('shoutem_save_options', array($this,'on_save_options'), -9999);
		$this->all_keys_uid = 'se-cache-all-keys';
	}

	public function on_post_published() {
		$this->clear_cache();
	}

	public function on_save_options() {
		$this->clear_cache();
	}

	private function is_cache_enabled() {
		$options = 	$this->options->get_options();
		$expiration = $options['cache_expiration'];

		if (isset($params['session_id'])) {
			return false; //don't use for signed in user
		} else if ($expiration == 0) {
			return false; //don't use if the user dissabled in options
		}
		return true;
	}

	public function get_cached($uid) {
		$cached = false;
		$use_cache = $this->is_cache_enabled();
		if (!$use_cache) {
			return false;
		}

		$cached = $this->get_from_cache($uid);
		return $cached;
	}

	public function store_to_cache($uid, $cached) {
		if ($this->is_cache_enabled() === false) {
			return false;
		}
		$options = 	$this->options->get_options();
		$expiration = $options['cache_expiration'];
		$this->set_to_cache($uid, $cached, $expiration);
	}

	/**
	 * Note, this can only be used with shoutem controller methods because it relies on specific naming scheme in params
	 * Returnes cached data when found, otherwise calls method and caches result
	 * @param method array(class_instance, method_name)
	 * @param params params to be passed to method (also used to create unique id for caching data)
	 * @return data (cached or fresh)
	 */
	public function use_cache($method, $params) {
		$cached = false;

		$options = 	$this->options->get_options();
		$expiration = $options['cache_expiration'];
		$use_cache = true;
		if (isset($params['session_id'])) {
			$use_cache = false; //don't use for signed in user
		} else if ($expiration == 0) {
			$use_cache = false; //don't use if the user dissabled in options
		}

		if (!$use_cache) {
			return $method[0]->$method[1]($params);
		}

		$uid = $this->unique_id($params);
		$cached = $this->get_from_cache($uid);
		if ($cached === false) {
			$cached = $method[0]->$method[1]($params);
			$this->set_to_cache($uid, $cached, $expiration);
		}

		return $cached;
	}

	public function unique_id($params) {
		ksort($params);

		if (!array_key_exists('method',$params)) {
			//at least method must exist to create unique id
			throw new ShoutemApiException('intertal_server_error');
		}

		$unique_id = '';
		foreach($params as $value) {
			$unique_id .= $value;
		}
		return $unique_id;
	}

	private function get_from_cache($uid) {
		return get_transient($uid);
	}

	private function set_to_cache($uid, $value, $expiration) {

		$success = set_transient($uid, $value, $expiration);
		if ($success) {
			$this->add_to_used_keys($uid, $expiration);
		}
		return $success;
	}

	/**
	 * Keep track of all uid's in the cache,
	 * needed to clear the cache later on
	 */
	private function add_to_used_keys($uid, $expiration) {
		$all_keys_uid = $this->all_keys_uid;
		$all_keys = get_transient($all_keys_uid);
		if (!$all_keys) {
			$all_keys = array();
		}
		$all_keys[$uid] = array(
			'uid' => $uid,
			'expiration' => $expiration,
			'inserted' => time()
		);
		set_transient($all_keys_uid, $all_keys);
	}

	private function clear_cache() {
		$all_keys_uid = $this->all_keys_uid;
		$all_keys = get_transient($all_keys_uid);
		if (!$all_keys) {
			//nothing was cached
			return;
		}
		foreach($all_keys as $cache_entry_desc) {
			$cache_entry_uid = $cache_entry_desc['uid'];
			delete_transient($cache_entry_uid);
		}
		delete_transient($all_keys_uid);
	}
}