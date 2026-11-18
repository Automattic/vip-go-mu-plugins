<?php

// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- Capture raw request fields supplied by the loopback HTTP server.
// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode -- WordPress is not loaded in this standalone receiver.

// Local HTTP receiver used by the real cURL upload integration test.
$body    = file_get_contents( 'php://input' ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsRemoteFile -- Incoming local request.
$capture = [
	'method' => $_SERVER['REQUEST_METHOD'],
	'path'   => $_SERVER['REQUEST_URI'],
	'body'   => base64_encode( $body ),
	'length' => (int) ( $_SERVER['CONTENT_LENGTH'] ?? 0 ),
];
file_put_contents( getenv( 'VIP_TEST_UPLOAD_CAPTURE' ), json_encode( $capture ) ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- Local test fixture.
header( 'Content-Type: application/json' );
echo json_encode( [ 'filename' => $_SERVER['REQUEST_URI'] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response fixture.
