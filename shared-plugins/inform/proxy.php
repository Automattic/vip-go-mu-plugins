<?php

/**
 * Inform Tagger
 * 
 * Inform API PHP client for Wordpress plugin
 * 
 * @author	Inform Technologies, Inc.
 * @link	http://www.inform.com
 * 
 */

if (!class_exists('inform_api_service_request')) {
	
	class inform_api_service_request {
		
		public $extracted_tags;
		public $extracted_entity;
		public $extracted_iabs;
		
		private $api_inform_extract_host = 'text.inform.com';
		private $api_inform_extract_uri = '/extract/dmurray/Extract.asmx';
		private $api_connection_timeout = 10;
		private $api_connection_port = 80;
		private $inform_token = 319035974;
		
		private $s_error_msg = 'Please contact techsupport@inform.com if you need any assistance.';
		
		// constructor
		public function __construct() {
			
			// process request
			if (isset($_POST['inform_action']) && $_POST['inform_action'] === 'extract') {
				$this -> process();
			}
		}
		
		// request and process
		public function soap_request($s_content, $s_search_prefix) {
			
			// create SOAP markup of request
			$s_soap = $this -> serialize_extract($s_content, $s_search_prefix, $this -> inform_token);
			
			// get response
			$s_response = $this -> request_extract($s_soap);
			
			// parse response
			$this -> deserialize_extract($s_response);
		}
		
		// POST request for article processing
		private function request_extract($s_soap) {
			
			// require data
			if (empty($s_soap)) {
				return FALSE;
			}
			
			// connect
			$fp = @fsockopen($this -> api_inform_extract_host, $this -> api_connection_port, $err_no, $s_error_msg, $this -> api_connection_timeout);
			
			// abort if no stream resource
			if (!(get_resource_type($fp) === 'stream')) {
				$this -> s_error_msg = '[Error:10001] Request type is not stream resource: '.$s_error_msg."\n".$this -> s_error_msg;
				echo $this -> s_error_msg;
				return FALSE;
			}
			
			// form request
			$a_headers = array(
				'POST '.$this -> api_inform_extract_uri.' HTTP/1.1',
				'Content-Type: text/xml',
				'Host: '.$this -> api_inform_extract_host.':'.$this -> api_connection_port,
				'Content-Length: '.strlen($s_soap),
				'Connection: close'
			);
			$s_request = implode("\r\n", $a_headers)."\r\n\r\n".$s_soap;
			
			// attempt request
			if (!fwrite($fp, $s_request)) {
				fclose($fp);
				$this -> s_error_msg = '[Error:10002] Request failed: '.$s_error_msg."\n".$this -> s_error_msg;
				echo $this -> s_error_msg;
				return FALSE;
			}
			
			// response
			$s_response = '';
			while (!feof($fp)) {
				$s_response .= fgets($fp, 8192);
			}
			fclose($fp);
			
			return $this -> parse_response($s_response);
		}
		
		// process request via AJAX
		private function process() {
			
			// require post content
			$s_content = $_POST['content'];
			if (empty($s_content)) {
				exit();
			}
			
			// perform request
			$this -> soap_request($s_content, $_POST['searchPrefix']);
			
			// form response
			$s_response = 'INFORM_TAGS';
			
			// add Inform tags
			$a_tags = $this -> extracted_tags;
			if (!empty($a_tags)) {
				foreach ($a_tags as $a_tag) {
					$s_response .= '~'.$a_tag[1].'::'.$a_tag[0];
				}
			}
			
			// add IAB tags
			$a_tags = $this -> extracted_iabs;
			if(!empty($a_tags)) {
				$s_response .= '~IAB_TOPICS';
				foreach ($a_tags as $a_tag) {
					$s_response .= '~'.$a_tag[1].'::'.$a_tag[0];
				}
			}
			
			// respond
			echo $s_response;
			exit(); // important - '0' gets appended otherwise
		}
		
		// check headers, return body
		private function parse_response($s_response = NULL) {
			
			$b_err = FALSE;
			
			// require response
			if (empty($s_response)) {
				$this -> s_error_msg = '[Error:10003] Server did not respond.'."\n".$this -> s_error_msg;
				$b_err = TRUE;
			}
			
			// split headers and content
			$a_response = explode("\r\n\r\n", trim($s_response));
			if (!is_array($a_response) || count($a_response) < 2) {
				$b_err = TRUE;
			}
			$s_header  = $a_response[count($a_response) - 2];
			$s_body    = trim($a_response[count($a_response) - 1]);
			$a_headers = explode("\n", $s_header);
			unset($a_response);
			unset($s_header);
			
			// verify headers
			$b_err = !$this -> verify_response($a_headers);
			
			// abort
			if ($b_err) {
				echo $this -> s_error_msg;
				return FALSE;
			}
			
			// success
			return $s_body;
		}
		
		// check response headers
		private function verify_response($a_headers = NULL) {
			
			// require header array
			if (!is_array($a_headers) || empty($a_headers)) {
				$this -> s_error_msg = '[Error:10004] Server did not send a proper response.'."\n".$this -> s_error_msg;
				return FALSE;
			}
			
			// HTTP status okay
			if (preg_match('/^HTTP\/1.(0|1) (1|2)00 OK\s*$/', $a_headers[0])) {
				return TRUE;
			}
			
			$this -> s_error_msg = '[Error:10005] Request not successful: '.$a_headers[0].'.'."\n".$this -> s_error_msg;
			return FALSE;
		}
		
		// create SOAP markup for request
		private function serialize_extract($s_content, $s_search_prefix, $i_token) {
			
			$s_soap = '<soap:Envelope '.
					'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '.
					'xmlns:xsd="http://www.w3.org/2001/XMLSchema" '.
					'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'.
				'<soap:Body>'.
					'<ExtractAllWithIABTopics xmlns="urn:inform:api:text">'.
						'<iToken>'.htmlspecialchars($i_token).'</iToken>'.
						'<sArticleText>'.htmlspecialchars($s_content).'</sArticleText>'.
						'<sSearchPrefix>'.htmlspecialchars($s_search_prefix).'</sSearchPrefix>'.
					'</ExtractAllWithIABTopics>'.
				'</soap:Body>'.
			'</soap:Envelope>';
			
			return $s_soap;
		}
		
		function deserialize_extract($s_response) {
			
			$xmlp = xml_parser_create('UTF-8');
			if (xml_parse_into_struct($xmlp, $s_response, $a_vals) > 0) {
				xml_parser_free($xmlp);
				
				$a_entities_topics = array();
				$a_iab_topics = array();
				
				//starting with entity
				$b_is_entity = FALSE;
				$b_is_topic = FALSE;
				$b_is_iab_topic = FALSE;
				
				for ($i = 0; $i < sizeof($a_vals) - 1; $i += 1) {
					
					$a_val = $a_vals[$i];
					
					// determine node type
					if($a_val['tag'] === 'TOPICS' || $a_val['tag'] === 'INDUSTRIES') {
						$b_is_topic = TRUE;
						$b_is_entity = FALSE;
						$b_is_iab_topic = FALSE;
					} else if($a_val['tag'] === 'ENTITIES') {
						$b_is_entity = TRUE;
						$b_is_topic = FALSE;
						$b_is_iab_topic = FALSE;
					} else if($a_val['tag'] === 'IABTOPICS') {
						$b_is_iab_topic = TRUE;
						$b_is_entity = FALSE;
						$b_is_topic = FALSE;
					}
					
					if (sizeof($a_val) === 4 && $a_val['tag'] === 'NAME' && !empty($a_val['value'])) {
						
						// get tag and relevance
						$s_name = $a_val['value'];
						$i_score = intval($a_vals[$i + 1]['value']);
						
						// add to arrays
						if($b_is_entity) {
							$a_entities_topics[] = array($i_score, $s_name);
						} else if($b_is_topic) {
							$a_entities_topics[] = array($i_score, $s_name);
						} else if($b_is_iab_topic) {
							$a_iab_topics[] = array($i_score, $s_name);
						}
						
					} else if (sizeof($a_val) === 4 && $a_val['tag'] === 'SECTION' && !empty($a_val['value'])) {
						
						// skip if no score
						if (!$a_vals[$i + 2]) {
							continue;
						}
						
						// get tag and relevance
						$s_name = str_replace(',', '', $a_val['value']);
						$i_score = intval($a_vals[$i + 2]['value']);
						
						//check if the iab section not added already
						$b_found = FALSE;
						foreach ($a_iab_topics as $k => $a_topic) {
							if($a_topic[1] === $s_name) {
								$b_found = TRUE;
								
								//increment topic value
								if($a_topic[0] < $i_score) {
									$a_topic[0] = $i_score;
								}
								break;
							}
						}
						
						if (!$b_found) {
							$a_iab_topics[] = array($i_score, $s_name);
						}
					}
				}
				
				// sort by relevance, descending
				rsort($a_entities_topics);
				rsort($a_iab_topics);
				
				// add to main arrays
				foreach ($a_entities_topics as $value) {
					$this -> extracted_tags[] = $value;
				}
				foreach ($a_iab_topics as $value) {
					$this -> extracted_iabs[] = $value;
				}
				
			} else {
				$this -> deserialize_simple_xml($s_response);
			}
		}
		
		// PHP5 or later
		private function deserialize_simple_xml($s_response) {}
	}
	
	// start
	new inform_api_service_request();
}

?>
