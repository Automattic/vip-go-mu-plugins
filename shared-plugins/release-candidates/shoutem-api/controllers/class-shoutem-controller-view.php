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
class ShoutemControllerView {

	public function ShoutemControllerView($request, $response) {
		$this->request = $request;
		$this->response = $response;
	}

	function get_avatar_url( $email, $size = '48') {
		if ( ! get_option('show_avatars') ) {
			//TODO what if show avatars is false??
		}

		$avatar_default = get_option('avatar_default');
		if ( empty($avatar_default) )
			$default = 'mystery';
		else
			$default = $avatar_default;



		$email_hash = md5( strtolower( $email ) );

		$host = sprintf( "http://%d.gravatar.com", ( hexdec( $email_hash{0} ) % 2 ) );

		if ( 'mystery' == $default )
			$default = "$host/avatar/ad516503a11cd5ca435acc9bb6523536?s={$size}"; // ad516503a11cd5ca435acc9bb6523536 == md5('unknown@gravatar.com')
		elseif ( 'blank' == $default )
			$default = includes_url('images/blank.gif');
		elseif ( !empty($email) && 'gravatar_default' == $default )
			$default = '';
		elseif ( 'gravatar_default' == $default )
			$default = "$host/avatar/s={$size}";
		elseif ( empty($email) )
			$default = "$host/avatar/?d=$default&amp;s={$size}";
		elseif ( strpos($default, 'http://') === 0 )
			$default = add_query_arg( 's', $size, $default );

		$out = "$host/avatar/";
		$out .= $email_hash;
		$out .= '?s='.$size;
		$out .= '&amp;d=' . urlencode( $default );

		$rating = get_option('avatar_rating');
		if ( !empty( $rating ) )
			$out .= "&amp;r={$rating}";

		return $out;
}


	protected function format_record_property(&$name, &$value) {

		if (is_array($value)) {
			$value = $this->format_record($value);
			return;
		}

		//this is used so that the _ends_with "able" rule is not applied to commentable field, since commentable is 'yes', 'no', 'dissabled'
		if(strpos($name, "commentable") !== false) {
			return;
		}

		if(strpos($name, "author_image_url") !== false) {
			$value = $this->get_avatar_url($value);
			return;
		}

		if (string_ends_with($name, "able")) {
			$value = (boolean)$value;
			return;
		}

		if(strpos($name, "allowed") !== false) {
			$value = (boolean)$value;
			return;
		}

		if(strpos($name, "approved") !== false) {
			$value = (boolean)$value;
			return;
		}

		if(string_ends_with($name, "latitude")
		|| string_ends_with($name, "longitude")) {
			$value = (float)$value;
			return;
		}

		if (string_ends_with($name, "_ids")) {
			$ids = explode(",", $value);
			$value = array();
			foreach($ids as $i => $id) {
				$value[] = (int)$id;
			}
			return;
		}

		if (string_ends_with($name, "_at") || string_ends_with($name, "time")) {
			$value = date(DATE_RSS, strtotime($value));
			return;
		}

		if (is_numeric($value)) {
			$value = (int)$value;
			return;
		} else if (is_string($value)) {
			//$value = html_entity_decode($value, ENT_QUOTES, 'UTF-8'); //why was this added
		}
	}

	protected function get_summary($content) {
		if(strlen($content) > SHOUTEM_API_SUMMARY_LENGTH) {
			return substr($content, 0, SHOUTEM_API_SUMMARY_LENGTH).'...';
		}
		return $content;
	}

	public function format_record($record) {
		$output = array();
		foreach($record as $name => $value) {
			$this->format_record_property($name, $value);
			$output[$name] = $value;
		}

		return $output;
	}

	private function get_next_page_uri($params) {
		//Moved this from format_recordset method becaouse it threw error.
		if (!isset($this->_uri_without_offset_and_limit)) {
			$this->_uri_without_offset_and_limit = preg_replace(array("/&offset=[0-9]+/",
				"/&limit=[0-9]+/"), "", $_SERVER['REQUEST_URI']);
		}

		$url = $this->_uri_without_offset_and_limit . "&offset=$params[offset]&limit=$params[limit]";
		return $url;
	}

	protected function format_recordset($data) {

		$output = array();

		$output['data'] = array();
		foreach ($data['data'] as $key => $value) {
			$output['data'][] = $this->format_record($value);
		}

		$output['paging'] = (object)array(); // create an empty object

		if (isset($data['paging']['previous'])) {
			$output['paging']->previous = $this->get_next_page_uri($data['paging']['previous']);
		}

		if (isset($data['paging']['next'])) {
			$output['paging']->next = $this->get_next_page_uri($data['paging']['next']);
		}

		return $output;
	}

	public function show_recordset($data) {
		return $this->response->send_json_ok($this->format_recordset($data));
	}

	public function encode_record_as_json($data) {
		return $this->response->encode_json($this->format_record($data));
	}

	public function encode_recordset_as_json($data) {
		return $this->response->encode_json($this->format_recordset($data));
	}

	public function show_record($data) {
		return $this->response->send_json_ok($this->format_record($data));
	}

	private function filter_html($html) {
		$filtered_html = "";
		$forbiten_elements = "/<(script|iframe).*?>.*?<\/\1>/ig";

		return $filtered_html;
	}
}

$shoutem_api_response_err_codes = array (
	'400' => 'Bad Request',
	'401' => 'Authorization Required',
	'403' => 'Forbidden',
	'404' => 'Not Found',
	'501' => 'Method Not Implemented'
);

?>