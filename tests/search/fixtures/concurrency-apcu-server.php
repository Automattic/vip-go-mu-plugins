<?php

namespace Automattic\VIP\Search\ConcurrencyLimiter;

// This standalone local HTTP fixture uses owned temporary files as barriers.
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions, WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput, WordPress.WP.AlternativeFunctions.json_encode_json_encode, WordPress.WP.GlobalVariablesOverride.Prohibited
// This test-only boundary can reproduce a fetch/modify/store mutation. The
// baseline delegates to the real APCu atomic primitive.
function apcu_inc( $key, $step, &$success, $ttl ) {
	if ( 'mutant' !== getenv( 'APCU_TEST_MODE' ) ) {
		return \apcu_inc( $key, $step, $success, $ttl );
	}

	$value = \apcu_fetch( $key );
	file_put_contents( getenv( 'APCU_TEST_DIR' ) . '/fetched-' . $_GET['id'], '1' );
	$deadline = microtime( true ) + 15;
	while ( ! file_exists( getenv( 'APCU_TEST_DIR' ) . '/racego' ) && microtime( true ) < $deadline ) {
		usleep( 10000 );
	}
	if ( ! file_exists( getenv( 'APCU_TEST_DIR' ) . '/racego' ) ) {
		throw new \RuntimeException( 'Mutation barrier timed out' );
	}
	$success = \apcu_store( $key, $value + $step, $ttl );
	return $value + $step;
}

require_once __DIR__ . '/concurrency-apcu-zero.php';
require_once __DIR__ . '/../../../search/includes/classes/concurrency-limiter/class-apcu-backend.php';

$dir  = getenv( 'APCU_TEST_DIR' );
$wait = static function ( string $name ) use ( $dir ): void {
	$deadline = microtime( true ) + 15;
	while ( ! file_exists( $dir . '/' . $name ) && microtime( true ) < $deadline ) {
		usleep( 10000 );
	}
	if ( ! file_exists( $dir . '/' . $name ) ) {
		throw new \RuntimeException( 'Actor barrier timed out: ' . $name );
	}
};

if ( '/health' === $_SERVER['REQUEST_URI'] ) {
	echo apcu_enabled() ? 'ready' : 'no-apcu';
	return;
}
if ( '/seed' === $_SERVER['REQUEST_URI'] ) {
	\apcu_store( APCu_Backend::KEY_NAME, 0, 60 );
	\apcu_store( 'vip_apcu_test_marker', $dir, 60 );
	echo 'seeded';
	return;
}
if ( '/count' === $_SERVER['REQUEST_URI'] ) {
	echo json_encode( [ \apcu_fetch( APCu_Backend::KEY_NAME ), \apcu_fetch( 'vip_apcu_test_marker' ) ] );
	\apcu_delete( APCu_Backend::KEY_NAME );
	\apcu_delete( 'vip_apcu_test_marker' );
	return;
}

$id      = $_GET['id'];
$backend = new APCu_Backend();
$backend->initialize( 1, 60 );
file_put_contents( $dir . '/ready-' . $id, '1' );
$wait( 'go' );
$admitted = $backend->inc_value();
file_put_contents( $dir . '/result-' . $id, json_encode( [
	'pid'      => getmypid(),
	'marker'   => \apcu_fetch( 'vip_apcu_test_marker' ),
	'admitted' => $admitted,
] ) );
$wait( 'done' );
$backend->dec_value();
echo 'done';
