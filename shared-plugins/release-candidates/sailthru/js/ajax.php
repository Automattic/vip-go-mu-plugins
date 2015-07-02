<?php

/**
 * Anything we want to post through the API can flow
 * this way. We'll have both WP functions availble to
 * us and the Sailthru Client library
 */
$wp_load = realpath("../../../../wp-load.php");

if(!file_exists($wp_load)) {

	$wp_config = realpath("../../../../wp-config.php");

	if (!file_exists($wp_config)) {
  		exit("Can't find wp-config.php or wp-load.php");
	} else {
  		require_once($wp_config);
	}

} else {

	require_once($wp_load);

}

require_once( '../lib/Sailthru_Util.php' );
require_once( '../lib/Sailthru_Client.php' );

$return = array();
	$return['error'] = false;
	$return['message'] = '';

switch( $_POST['sailthru_action'] )
{

	case "add_subscriber":
		$email = trim( $_POST['email'] );
		if( ! filter_var($email , FILTER_VALIDATE_EMAIL) || empty($email) ) {
			$return['error'] = true;
			$return['message'] = "Please enter a valid email address.";
		} else {
			$email = filter_var($email, FILTER_VALIDATE_EMAIL);
		}

		if( isset($_POST['first_name'] ) && !empty($_POST['first_name'] ) ){
			$first_name = filter_var(trim($_POST['first_name']), FILTER_SANITIZE_STRING);
		} else {
			$first_name = '';
		}

		if( isset($_POST['last_name']) && !empty($_POST['last_name'] ) ){
			$last_name = filter_var(trim($_POST['last_name']), FILTER_SANITIZE_STRING);
		} else {
			$last_name = '';
		}

		if( $first_name || $last_name ) {

			$options = array(
				'vars' => array(
					'first_name'	=> $first_name,
					'last_name'		=> $last_name,
				)
			);

		}

		$subscribe_to_lists = array();
			if( !empty($_POST['sailthru_email_list'] ) ) {

				$lists = explode(',', $_POST['sailthru_email_list']);

				foreach( $lists as $key => $list ) {

					$subscribe_to_lists[ $list ] = 1;

				}

				$options['lists'] = $subscribe_to_lists;

			} else {

				$options['lists'] = array('Sailthru Subscribe Widget' => 1);	// subscriber is an orphan

			}


		$options['vars']['source'] = get_bloginfo('url');


		$return['data'] = array(
			'email'	=> $email,
			'options' => $options
		);

		if( $return['error'] == false ) {

			$sailthru = get_option('sailthru_setup_options');
			$api_key = $sailthru['sailthru_api_key'];
			$api_secret = $sailthru['sailthru_api_secret'];

			$client = new Sailthru_Client( $api_key, $api_secret );
				$res = $client->saveUser($email, $options);

			if( $res['ok'] != true ) {
				$result['error'] = true;
				$result['message'] = "There was an error subscribing you. Please try again later.";
			}

			$return['result'] = $res;

		}

		break;

	default:

		$return['error'] = true;
		$return['message'] = 'No action defined. None taken.';

}


echo json_encode( $return );
die();
