<?php
class Publishthis_API {

	private $_api_url;

	/**
	 * Publishthis_API constructor
	 */
	function __construct() {
		global $publishthis;

		// Set api url
		switch ( $publishthis->get_option ( 'api_version' ) ) {
		case '3.0' :
		default :
			$this->_api_url = 'http://webapi.publishthis.com/rest';
			break;
		}
	}

	/**
	 *
	 *
	 * @desc Get API url value
	 * @return string API url
	 */
	function api_url() {
		return $this->_api_url;
	}

	/*
	 * Publishthis Feeds functions
	*/

	/**
	 * Use this method to get all of your published feeds.
	 * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-feeds/
	 */
	function get_feeds() {
		global $publishthis;

		$params = array ( 'results' => 50, 'skip' => 0, 'token' => $publishthis->get_option ( 'api_token' ) );

		$feeds = array ();

		while ( true ) {
			$url = $this->_compose_api_call_url( '/feeds/', $params );

			try {
				$response = $this->_request ( $url );

				$result_list = ( array ) $response->resultList;
				if ( empty ( $result_list ) )
					break;

				foreach ( $result_list as $feed ) {
					$_feed = array ( 'feedId' => $feed->feedId, 'displayName' => $feed->title, 'templateId' => $feed->templateId );
					$feeds [] = $_feed;
				}

				$params ['skip'] += $params ['results'];
			} catch ( Exception $ex ) {
				$publishthis->log->add ( $ex->getMessage () );
				break;
			}
		}

		return $feeds;
	}

	/**
	 * Allows you to return content based on the Feed Id from the PublishThis system.
	 * The Auto Publishing settings of the feed are used to constrain your results.
	 * It will use the Source Bundles and other Source settings for filtering, as well as
	 * the search criteria set in auto publishing to return automated content.
	 *
	 * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-feedid/
	 */
	function get_feed_content_by_id( $feed_id, $params = array() ) {
		global $publishthis;

		$params = $params + array ( 'results' => 10, 'sort' => 'most_recent', 'token' => $publishthis->get_option ( 'api_token' ) );

		$url = $this->_compose_api_call_url( '/content/feed/'.$feed_id, $params );

		try {
			$response = $this->_request ( $url );
			return ( array ) $response->resultList;
		} catch ( Exception $ex ) {
			$publishthis->log->add ( $ex->getMessage () );
		}
	}

	/**
	 * Allows you to return content based on the Feed Id from the PublishThis system.
	 * The Auto Publishing settings of the feed are used to constrain your results.
	 * It will use the Source Bundles and other Source settings for filtering, as well as
	 * the search criteria set in auto publishing to return automated content.
	 *
	 * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-feedid/
	 */
	function get_custom_data_by_feed_id( $feed_id, $params = array() ) {
		global $publishthis;

		$params = $params + array ( 'token' => $publishthis->get_option ( 'api_token' ) );

		$url = $this->_compose_api_call_url( '/feeds/'.$feed_id.'/custom-data/', $params );

		try {
			$response = $this->_request ( $url );
			return ( array ) $response->resultList;
		} catch ( Exception $ex ) {
			$publishthis->log->add ( $ex->getMessage () );
		}
	}

	/**
	 * This returns all active feed templates available for this client.
	 * The feed template defines the custom fields and template sections that are available for
	 * the feeds that are generated from this template.
	 *
	 * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-feedsfeed-templates/
	 */
	function get_feed_templates( $params = array() ) {
		global $publishthis;

		$params = $params + array ( 'token' => $publishthis->get_option ( 'api_token' ) );

		$url = $this->_compose_api_call_url( '/feeds/feed-templates/', $params );

		try {
			$response = $this->_request ( $url );
			return ( array ) $response->resultList;
		} catch ( Exception $ex ) {
			$publishthis->log->add ( $ex->getMessage () );
		}
	}

	/**
	 * This is the primary method for developers to find newly created or published feeds.
	 * Usually, developers will poll this method with a last timestamp every XX amount of
	 * minutes. Depending on how many API calls you are allowed depends on how frequently
	 * you will want to check for newly published content.
	 *
	 * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-feedssincetimestamp/
	 */
	function get_feeds_since_timestamp( $timestamp = 0, $template_id = 0, $params = array() ) {
		global $publishthis;

		if ( empty ( $timestamp ) ) {
			$timestamp = $this->_generateTimestamp();
		}
		$timestamp = number_format( $timestamp, 0, '', '' );

		// add zeros until we have it set correctly.
		// TODO to get this fixed correctly
		// 1355679388000
		while ( strlen( $timestamp ) < 13 ) {
			$timestamp = $timestamp . '0';
		}

		$params = $params + array ( 'results' => 20, 'skip' => 0, 'token' => $publishthis->get_option ( 'api_token' ) );

		$feeds = array ();

		while ( true ) {
			$url = $this->_compose_api_call_url( '/feeds/since/'.$timestamp, $params );

			try {
				$response = $this->_request ( $url );

				$result_list = ( array ) $response->resultList;
				if ( empty ( $result_list ) ) {
					$publishthis->log->addWithLevel ( "getFeedsSince::ResultList empty::" . $url, "2" );
					break;
				}
				$publishthis->log->addWithLevel ( "getFeedsSince::ResultList size=" . count( $result_list ) . " for::" . $url, "2" );

				foreach ( $result_list as $feed ) {
					$_feed = array ( 'feedId' => $feed->feedId, 'displayName' => $feed->title, 'templateId' => $feed->templateId );

					if ( $template_id && $template_id == $feed->templateId || !$template_id ) {
						$feeds[] = $_feed;
					}
				}

				// we got back as much as we needed and do not need to make another call
				if ( count( $result_list ) < $params['results'] ) {
					break;
				}

				$params ['skip'] += $params ['results'];
			} catch ( Exception $ex ) {
				$publishthis->log->add ( $ex->getMessage () );
				break;
			}
		}

		return $feeds;
	}

	/*
	 * Publishthis Topics functions
	 */

	/**
	 * Runs a search against our topics based on the query string name passed in.
	 * This is much like if you were using our tools where we provide a Search Suggest
	 * when trying to find topics.
	 *
	 * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-topicsnamename/
	 */
	function get_topic_content_by_id( $topic_id, $params = array() ) {
		global $publishthis;

		$params = $params + array ( 'results' => 50,
			'skip' => 0,
			'token' => $publishthis->get_option ( 'api_token' ) );

		$url = $this->_compose_api_call_url( '/content/topic/'.$topic_id, $params );

		try {
			$response = $this->_request ( $url );
			return ( array ) $response->resultList;
		} catch ( Exception $ex ) {
			$publishthis->log->add ( $ex->getMessage () );
		}
	}

	/**
	 * Runs a search against our topics based on the query string name passed in.
	 * This is much like if you were using our tools where we provide a Search Suggest
	 * when trying to find topics.
	 *
	 * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-topicsnamename/
	 */
	function get_topics( $name, $params = array() ) {
		global $publishthis;

		$params = $params + array ( 'token' => $publishthis->get_option ( 'api_token' ) );

		$url = $this->_compose_api_call_url( '/topics/name/'.$name, $params );

		try {
			$response = $this->_request ( $url );
			return ( array ) $response->resultList;
		} catch ( Exception $ex ) {
			$publishthis->log->add ( $ex->getMessage () );
		}
	}

	/*
	 * Publishthis Saved Searches functions
	 */

	/**
	 * Returns all Saved Searches for your client.
	 *
	 * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-savedsearches/
	 */
	function get_saved_searches( $params = array() ) {
		global $publishthis;

		$params = $params + array ( 'results' => 1000, 'token' => $publishthis->get_option ( 'api_token' ) );

		$url = $this->_compose_api_call_url( '/savedsearches/', $params );

		try {
			$response = $this->_request ( $url );
			return ( array ) $response->resultList;
		} catch ( Exception $ex ) {
			$publishthis->log->add ( $ex->getMessage () );
		}
	}

	/**
	 * Allows you to return content based on Saved Search Ids from the PublishThis system.
	 *
	 * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-saved-searchids/
	 */
	function get_saved_search_content( $bundle_ids, $params ) {
		global $publishthis;

		$ids = implode( ',', $bundle_ids );

		$params = $params + array (
			'results'      => 10,
			'sort'         => 'most_recent',
			'token'        => $publishthis->get_option ( 'api_token' ) );

		$url = $this->_compose_api_call_url( '/content/savedsearch/'.$ids, $params );

		try {
			$response = $this->_request ( $url );
			return ( array ) $response->resultList;
		} catch ( Exception $ex ) {
			$publishthis->log->add ( $ex->getMessage () );
		}
	}

	/*
	 * Publishthis Sections functions
	 */

	/**
	 * Returns the curated content from a feeds template section.
	 *
	 * http://docs.publishthis.com/edcenter/developer-resources/api-reference/api-method-contentfeedfeedidsectionsectionid/
	 */
	function get_section_content( $feed_id, $section_id, $params = array() ) {
		global $publishthis;

		$params = $params + array ( 'results' => 50, 'skip' => 0, 'token' => $publishthis->get_option ( 'api_token' ) );

		$content = array ();
		$total_available = 0;

		while ( true ) {
			$url = $this->_compose_api_call_url( '/content/feed/'.$feed_id.'/section/'.$section_id, $params );

			try {
				$response = $this->_request ( $url );

				$result_list = ( array ) $response->resultList;
				if ( empty ( $result_list ) )
					break;

				$total_available = $response->totalAvailable;

				$content = array_merge( $content, $result_list );

				$params ['skip'] += $params ['results'];
			} catch ( Exception $ex ) {
				$publishthis->log->add ( $ex->getMessage () );
				break;
			}
		}

		return $content;
	}



	/*
	 * Private methods
	 */

	/**
	 *
	 *
	 * @desc Generates timestapm value. Used for some API calls.
	 */
	private function _generateTimestamp() {
		$year = ( 60 * 60 * 24 * 365 );
		$timestamp = ( time() - $year ) * 1000;
		return $timestamp;
	}

	/**
	 *
	 *
	 * @desc Compose request url
	 * @param string  $method API call-specific url part
	 * @param array   $params Additional params to append to url
	 * @return API request URL
	 */
	private function _compose_api_call_url( $method, $params=array() ) {
		global $publishthis;

		if ( empty( $params ) ) {
			$params = array();
			$params['results'] = 50;
			$params['skip'] = 0;
			$params['token'] = $publishthis->get_option( 'api_token' );
		}

		$url = $this->_api_url . $method . '?' . http_build_query( $params );

		return $url;
	}

	/**
	 *
	 *
	 * @desc process API request
	 * we call our API method, then return the correct JSON object or thrown an exception
	 * if the API had an error, or there was an error in parsing, or there was an error in
	 * the fetch call itself.
	 */
	private function _request( $url ) {

		$cache_key = $url;
		// get cached data if exists
		$data = wp_cache_get( $cache_key );


		if ( false === $data || empty( $data ) ) {
			// process request
			$response = wp_remote_get( $url );

			// check for failure
			if ( !$response || is_wp_error( $response ) || 200 != $response['response']['code'] ) {
				throw new Exception( "PublishThis API error ({$url})." );
			}

			$data = $response['body'];

			//set the cache to 50 seconds.
			//our cron events can run every 1 minute, and our widgets lowest cache that they
			//have are 1 minute. So, we don't want to mess with any of those higher cache level
			//limits and return no results when there should be.
			wp_cache_set( $cache_key, $data, '', 50 );

		}

		$json = "";

		try {
			$json = json_decode( $data );

			if ( ! $json ) {
				throw new Exception( "inner JSON conversion error ({$url})." );
			}

		} catch ( Exception $ex ) {
			// try utf encoding it and then capturing it again.
			// we have seen problems in some wordpress/server installs where the json_decode
			//doesn't actually like the utf-8 response that is returned
			$publishthis->log->add( "Issue in decoding the json. ::" . $ex->getMessage () );
			try {
				$tmpBody = utf8_encode( $data );
				$json = json_decode( $tmpBody );
			} catch ( Exception $exc ) {
				$publishthis->log->add( "Issue in utf8 encoding and then decoding the json. ::" . $exc->getMessage() );
				throw new Exception( "Your wordpress install is not correctly decoding our API response, please contact your client service representative" );
			}
		}

		if ( ! $json ) {
			throw new Exception( "JSON conversion error ({$url})." );
		}

		if ( 200 != $json->resp->code ) {
			$message = !empty( $response->resp->errorMessage ) ? $response->resp->errorMessage : "PublishThis API error ({$url})";
			throw new Exception ( $message );
		}

		return $json->resp->data;
	}

}
