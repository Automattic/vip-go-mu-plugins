<?php

/**
 * Wrapper class to Cloud_Config_API to cache certain methods
 */
class Lift_Cloud_Config_API extends Cloud_Config_API {

	private $cached_methods;
	private $clear_cache_methods;

	public function __construct( $access_key, $secret_key, $http_api ) {
		parent::__construct( $access_key, $secret_key, $http_api );

		$this->cached_methods = array(
			'DescribeDomains', 'DescribeServiceAccessPolicies', 'DescribeIndexFields'
		);

		$this->clear_cache_methods = array(
			'CreateDomain' => array( 'DescribeDomains', 'DescribeServiceAccessPolicies' ),
			'UpdateServiceAccessPolicies' => array( 'DescribeServiceAccessPolicies' ),
			'DefineIndexField' => array( 'DescribeIndexFields' )
		);
	}

	public function _make_request( $method, $payload = array( ), $flatten_keys = true, $region = false ) {

		if ( in_array( $method, $this->cached_methods ) ) {
			if ( is_array( $cache = get_transient( 'lift_request_' . $method ) ) ) {
				$key = substr( md5( serialize( $payload ) ), 0, 25 );
				if ( isset( $cache[$key] ) ) {
					$this->set_last_error( $cache[$key]['set_last_error'] );
					$this->set_last_status_code( $cache[$key]['last_status_code'] );
					return $cache[$key]['response'];
				}
			}
		}

		$result = parent::_make_request( $method, $payload, $flatten_keys, $region );

		if ( in_array( $method, $this->cached_methods ) ) {
			$cache = get_transient( 'lift_request_' . $method );
			if ( !is_array( $cache ) )
				$cache = array( );

			$key = substr( md5( serialize( $payload ) ), 0, 25 );
			$cache[$key] = array(
				'set_last_error' => $this->get_last_error(),
				'last_status_code' => $this->last_status_code,
				'response' => $result
			);
			set_transient( 'lift_request_' . $method, $cache, 60 );
		} elseif ( isset( $this->clear_cache_methods[$method] ) ) {
			foreach ( $this->clear_cache_methods[$method] as $clear_methods ) {
				delete_transient( 'lift_request_' . $clear_methods );
			}
		}

		return $result;
	}

}

class Lift_Domain_Manager {

	/**
	 *
	 * @var Lift_Cloud_Config_API
	 */
	private $config_api;

	public function __construct( $access_key, $secret_key, $http_api ) {
		$this->config_api = new Lift_Cloud_Config_API( $access_key, $secret_key, $http_api );
	}

	public function get_last_error() {
		return $this->config_api->get_last_error();
	}

	public function credentials_are_valid() {
		delete_transient('lift_request_DescribeDomains'); //make sure we're not getting a cached copy
		return ( bool ) $this->config_api->DescribeDomains();
	}

	public function domain_exists( $domain_name, $region = false ) {
		return ( bool ) $this->get_domain( $domain_name, $region );
	}

	public function initialize_new_domain( $domain_name, $region = false ) {
		if ( $this->domain_exists( $domain_name, $region ) ) {
			return new WP_Error( 'domain_exists', 'There was an error creating the domain.  The domain already exists.' );
		}

		if ( is_wp_error( $error = $this->config_api->CreateDomain( $domain_name, $region ) ) )
			return $error;

		Lift_Search::set_search_domain_name( $domain_name );
		Lift_Search::set_domain_region( $region );

		TAE_Async_Event::WatchWhen( array( $this, 'domain_is_created' ), array( $domain_name, $region ), 60, 'lift_domain_created_'. $domain_name )
			->then( array( $this, 'apply_schema' ), array( $domain_name, null, null, $region ), true )
			->then( array( $this, 'apply_access_policy' ), array( $domain_name, false, $region ), true )
			->commit();

		return true;
	}

	public function apply_schema( $domain_name, $schema = null, &$changed_fields = array( ), $region = false ) {
		if ( is_null( $schema ) )
			$schema = apply_filters( 'lift_domain_schema', Cloud_Schemas::GetSchema() );

		if ( !is_array( $schema ) ) {
			return false;
		}

		$result = $this->config_api->DescribeIndexFields( $domain_name, $region );
		if ( false === $result ) {
			return new WP_Error( 'bad-response', 'Received an invalid repsonse when trying to describe the current schema' );
		}

		$current_schema = $result->IndexFields;
		if ( count( $current_schema ) ) {
			//convert to hashtable by name for hash lookup
			$current_schema = array_combine( array_map( function($field) {
						return $field->Options->IndexFieldName;
					}, $current_schema ), $current_schema );
		}

		foreach ( $schema as $index ) {
			$index = array_merge( array( 'options' => array( ) ), $index );
			if ( !isset( $current_schema[$index['field_name']] ) || $current_schema[$index['field_name']]->Options->IndexFieldType != $index['field_type'] ) {
				$response = $this->config_api->DefineIndexField( $domain_name, $index['field_name'], $index['field_type'], $index['options'] );

				if ( false === $response ) {
					Lift_Search::event_log( 'There was an error while applying the schema to the domain.', $this->config_api->get_last_error(), array( 'schema', 'error' ) );
					continue;
				} else {
					$changed_fields[] = $index['field_name'];
				}
			}
		}
		if ( count( $changed_fields ) ) {
			TAE_Async_Event::WatchWhen( array( $this, 'needs_indexing' ), array( $domain_name, $region ), 60, 'lift_needs_indexing_'. $domain_name )
				->then( array( $this, 'index_documents' ), array( $domain_name, $region ), true )
				->then( array( 'Lift_Batch_Handler', 'queue_all' ) )
				->commit();
		}

		return true;
	}

	public function get_default_access_policies( $domain_name, $region = false ) {
		$domain = $this->get_domain( $domain_name, $region );

		$search_service = $domain->SearchService;
		$doc_service = $domain->DocService;

		$services = array( $search_service, $doc_service );
		$statement = array( );
		$net = '0.0.0.0/0';
		$warn = true; // for future error handling to warn of wide open access
		// try to get the IP address external services see to be more restrictive
		if ( $ip = $this->config_api->http_api->get( 'http://ifconfig.me/ip' ) ) {
			$net = sprintf( '%s/32', str_replace( "\n", '', $ip ) );
			$warn = false;
		}

		foreach ( $services as $service ) {
			if ( $service ) {
				$statement[] = array(
					'Effect' => 'Allow',
					'Action' => '*',
					'Resource' => $service->Arn,
					'Condition' => array(
						'IpAddress' => array(
							'aws:SourceIp' => array( $net ),
						)
					)
				);
			}
		}

		if ( !$statement ) {
			return false;
		}

		$policies = array( 'Statement' => $statement );

		return $policies;
	}

	public function apply_access_policy( $domain_name, $policies = false, $region = false ) {
		if ( !$policies ) {
			$policies = $this->get_default_access_policies( $domain_name, $region );
			if ( !$policies ) {
				return false;
			}
		}

		if ( !$this->config_api->UpdateServiceAccessPolicies( $domain_name, $policies, $region ) ) {
			Lift_Search::event_log( 'There was an error while applying the default access policy to the domain.', $this->config_api->get_last_error(), array( 'access policy', 'error' ) );
			return new WP_Error('There was an error while applying the default access policy to the domain.');
		}

		return true;
	}

	public function domain_is_created( $domain_name, $region = false ) {
		if ( $domain = $this->get_domain( $domain_name, $region ) ) {
			return $domain->Created;
		}
		return false;
	}

	public function needs_indexing( $domain_name, $region = false ) {
		if ( $domain = $this->get_domain( $domain_name, $region ) ) {
			return $domain->RequiresIndexDocuments;
		}
		return false;
	}

	public function index_documents( $domain_name, $region = false ) {
		return ( bool ) $this->config_api->IndexDocuments( $domain_name, $region );
	}

	/**
	 * Returns the DomainStatus object for the given domain
	 * @param string|stdClass $domain_name
	 * @return DomainStatus|boolean
	 */
	public function get_domain( $domain_name, $region = false ) {
		if ( is_object( $domain_name ) ) {
			//allow a domain object to be passed around instead of re-fetching
			return $domain_name;
		}

		$response = $this->config_api->DescribeDomains( array( $domain_name ), $region );
		if ( $response ) {
			$domain_list = $response->DomainStatusList;
			if ( is_array( $domain_list ) && count( $domain_list ) ) {
				return $domain_list[0];
			} else {
				return false;
			}
		}
		return false;
	}

	/**
	 * Returns the List of DomainStatus objects
	 * @param string|stdClass $domain_name
	 * @return DomainStatus|boolean
	 */
	public function get_domains( $region = false ) {
		$response = $this->config_api->DescribeDomains( array(), $region );
		if ( $response ) {
			return $response->DomainStatusList;
		}
		return false;
	}

	/**
	 * Returns the document endpoint for the domain
	 * @param type $domain_name
	 * @return string|boolean
	 */
	public function get_document_endpoint( $domain_name ) {
		if ( $domain = $this->get_domain( $domain_name ) ) {
			return $domain->DocService->Endpoint;
		}
		return false;
	}

	/**
	 * Returns the search endpoint for the domain
	 * @param type $domain_name
	 * @return string|boolean
	 */
	public function get_search_endpoint( $domain_name ) {
		if ( $domain = $this->get_domain( $domain_name ) ) {
			return $domain->SearchService->Endpoint;
		}
		return false;
	}

	public function can_accept_uploads( $domain_name ) {
		$domain = $this->get_domain( $domain_name );
		if ( $domain ) {
			return ( bool ) (!$domain->Deleted && !$domain->Processing && !$domain->RequiresIndexDocuments && $domain->SearchInstanceCount > 0 );
		}
		return false;
	}

}

//cache domain
//run index asap


/*
 * @todo, convert Cloud_Config_API to object instance
 *
 * Plugin ->
 *	Search
 *		Search Form
 *		WP_Query/Query_Vars/Etc
 *		WP_Query -> Boolean Converter
 *			Boolean -> CloudSearch Converter
 *
 *	Document Submission
 *		Update Watcher
 *		Update Queue
 *		Update Submission
 *			Update to CloudSearch Converter
 *
 *	Configuration/Status
 *		Setup
 *		Search Status
 *		Document Update Status
 *
 */