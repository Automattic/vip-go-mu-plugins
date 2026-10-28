<?php
/**
 * Isolate the contact handler's terminating response while recording mail attempts.
 */

$mail_attempts = 0;
register_shutdown_function( static function () use ( &$mail_attempts ) {
	// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite -- Child process result channel.
	fwrite( STDERR, 'mail_attempts:' . $mail_attempts . PHP_EOL );
} );

function add_action() {}
function add_filter() {}
function remove_action() {}
function remove_filter() {}
function __( $text ) {
	return $text;
}
function wp_json_encode( $value ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- WordPress is deliberately absent in this fixture.
	return json_encode( $value );
}
function wp_send_json( $value ) {
	echo wp_json_encode( $value );
	die();
}
function wp_create_nonce( $purpose ) {
	return 'nonce:' . $purpose;
}
function wp_verify_nonce( $nonce, $purpose ) {
	return wp_create_nonce( $purpose ) === $nonce;
}
function wp_get_current_user() {
	return (object) [
		'display_name' => 'Requester',
		'user_email'   => 'requester@example.org',
		'user_login'   => 'requester',
	];
}
function wp_strip_all_tags( $value ) {
	// phpcs:ignore WordPressVIPMinimum.Functions.StripTags.StripTagsOneParameter -- Test payload contains plain text only.
	return strip_tags( $value );
}
function is_email( $email ) {
	return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
}
function apply_filters( $hook, $value ) {
	return $value;
}
function wp_get_theme() {
	return new class() {
		public function get() {
			return 'Test Theme';
		}
	};
}
function get_bloginfo() {
	return 'Test Site';
}
function site_url() {
	return 'https://example.org';
}
function admin_url() {
	return 'https://example.org/wp-admin';
}
function get_option() {
	return 'test-theme';
}
function home_url() {
	return 'https://example.org';
}
function wp_mail() {
	++$GLOBALS['mail_attempts'];
	return true;
}

require_once __DIR__ . '/../../../vip-dashboard/vip-dashboard.php';

$_POST = [
	'name'     => 'Requester',
	'email'    => 'requester@example.org',
	'subject'  => 'Support request',
	'body'     => 'Support request body',
	'priority' => 'Medium',
];

switch ( $argv[1] ) {
	case 'invalid':
		$_GET['_wpnonce'] = 'invalid';
		break;
	case 'wrong-purpose':
		$_GET['_wpnonce'] = wp_create_nonce( 'another-action' );
		break;
	case 'missing':
		break;
	default:
		throw new RuntimeException( 'Unknown nonce case' );
}

vip_contact_form_handler();
