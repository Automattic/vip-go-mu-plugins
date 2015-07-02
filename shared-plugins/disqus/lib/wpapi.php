<?php
/**
 * Implementation of the Disqus API designed for WordPress.
 *
 * @author		Disqus <team@disqus.com>
 * @copyright	2007-2010 Big Head Labs
 * @link		http://disqus.com/
 * @package		Disqus
 * @subpackage	DisqusWordPressAPI
 * @version		2.0
 */

require_once(ABSPATH.WPINC.'/http.php');
require_once(dirname(__FILE__).'/api/disqus/disqus.php');
/** @#+
 * Constants
 */
/**
 * Base URL for Disqus.
 */
define('DISQUS_ALLOWED_HTML', '<b><u><i><h1><h2><h3><code><blockquote><br><hr>');

/**
 * Helper methods for all of the Disqus v2 API methods.
 *
 * @package		Disqus
 * @subpackage	DisqusWordPressAPI
 * @author		DISQUS.com <team@disqus.com>
 * @copyright	2007-2008 Big Head Labs
 * @version		1.0
 */
class DisqusWordPressAPI {
	var $short_name;
	var $forum_api_key;

	function DisqusWordPressAPI($short_name=null, $forum_api_key=null, $user_api_key=null) {
		$this->short_name = $short_name;
		$this->forum_api_key = $forum_api_key;
		$this->user_api_key = $user_api_key;
		$this->api = new DisqusAPI($user_api_key, $forum_api_key, DISQUS_API_URL);
	}

	function get_last_error() {
		return $this->api->get_last_error();
	}

	function get_user_api_key($username, $password) {
		$response = $this->api->call('get_user_api_key', array(
			'username'	=> $username,
			'password'	=> $password,
		), true);
		return $response;
	}

	function get_forum_list($user_api_key) {
		$this->api->user_api_key = $user_api_key;
		return $this->api->get_forum_list();
	}

	function get_forum_api_key($user_api_key, $id) {
		$this->api->user_api_key = $user_api_key;
		return $this->api->get_forum_api_key($id);
	}
	
	function get_thread_info($post) {
		$identifier = dsq_identifier_for_post($post);
		
		$title = dsq_title_for_post($post);
		
		$response = $this->api->thread_by_identifier(array(
			'identifier'	=> $identifier,
			'title'			=> $title,
		));
		return $response->thread;
	}

	function get_thread($post) {
		$identifier = dsq_identifier_for_post($post);

		$response = $this->api->get_thread_posts(null, array(
			'thread_identifier'	=> $identifier,
			'filter' => 'approved',
		));
		return $response;
	}

	function import_wordpress_comments($wxr) {
		$http = new WP_Http();
		$response = $http->request(
			DISQUS_IMPORTER_URL . 'api/import-wordpress-comments/',
			array(
				'method' => 'POST',
				'body' => array(
					'forum_url' => $this->short_name,
					'forum_api_key' => $this->forum_api_key,
					'response_type'	=> 'php',
					'wxr' => $wxr,
				)
			)
		);
		if ($response->errors) {
			return -1;
		}
		$data = unserialize($response['body']);
		if (!$data || $data['stat'] == 'fail') {
			return -1;
		}
		return $data['import_id'];
	}

	function get_import_status($import_id) {
		$http = new WP_Http();
		$response = $http->request(
			DISQUS_IMPORTER_URL . 'api/get-import-status/',
			array(
				'method' => 'POST',
				'body' => array(
					'forum_url' => $this->short_name,
					'forum_api_key' => $this->forum_api_key,
					'import_id' => $import_id,
					'response_type'	=> 'php',
				)
			)
		);
		if ($response->errors) {
			return -1;
		}
		$data = unserialize($response['data']);
		if(!$data || $data['stat'] == 'fail') {
			return -1;
		}
		return $data;
	}

}

?>
