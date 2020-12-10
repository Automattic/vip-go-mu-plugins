<?php

namespace Automattic\VIP\Files\Acl;

use WP_Error;

class API_Client_Test extends \WP_UnitTestCase {

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../../files/acl.php' );
	}

	public function get_data__validate_request__invalid() {
		return [
			'null-uri' => [
				null,
				[ 400, 'missing-uri' ],
			],
	
			'empty-uri' => [
				'',
				[ 400, 'missing-uri' ],
			],
	
			'path-no-wp-content' => [
				'/a/path/to/a/file.jpg',
				[ 400, 'invalid-path' ],
			],
	
			'url-no-wp-content' => [
				'https://example.com/path/file.jpg',
				[ 400, 'invalid-path' ],
			],
	
			'relative-url-with-wp-content' => [
				'en/wp-content/uploads/file.png',
				[ 400, 'relative-path' ],
			],
		];
	}
	
	public function get_data__validate_request__valid() {
		return [
			'valid-path' => [
				'/wp-content/uploads/kittens.gif',
				'kittens.gif',
			],
	
			'valid-path-nested' => [
				'/wp-content/uploads/subfolder/2099/12/cats.jpg',
				'subfolder/2099/12/cats.jpg',
			],
	
			'valid-path-subsite' => [
				'/subsite/wp-content/uploads/puppies.png',
				'puppies.png',
			],
	
			'valid-path-querystring' => [
				'/wp-content/uploads/dogs.gif?w=100&h=200',
				'dogs.gif',
			],
	
			'valid-path-subsite-and-querystring' => [
				'/sub/wp-content/uploads/fish.png?crop=100,200',
				'fish.png',
			],
	
			'full-url' => [
				'https://example.com/wp-content/uploads/birds.gif',
				'birds.gif',
			],

			/* TODO: not supported yet
			'resized-image' => [
				'/wp-content/uploads/2021/01/dinos-100x100.jpg',
				'dinos.jpg',
			],
			*/
		];
	}
	
	/**
	 * @dataProvider get_data__validate_request__invalid
	 */
	public function test__pre_wp_validate_request__invalid( $file_request_uri, $expected_error ) {
		$actual_error = pre_wp_validate_request( $file_request_uri );
	
		$this->assertEquals( $expected_error, $actual_error );
	}

	/**
	 * @dataProvider get_data__validate_request__valid
	 */
	public function test__pre_wp_validate_request__valid( $file_request_uri, $expected_validated_path ) {
		$actual_validated_path = pre_wp_validate_request( $file_request_uri );
	
		$this->assertEquals( $actual_validated_path, $expected_validated_path );

	}
}
