<?php

require 'vendor/automattic/vip-build-tools/lib/github-changelog.php';

define( 'PROJECT_USERNAME', 'Automattic' );
define( 'PROJECT_REPONAME', 'vip-go-mu-plugins' );

$server = getenv( 'GITHUB_SERVER_URL' ) ?: 'https://github.com';
$cases  = array(
	"Two Factor: Keep the two-factor form visible (#7699)\n\nSee $server/WordPress/two-factor/pull/1009" => array( '7699' ),
	'Merge pull request #7725 from Automattic/staging' => array( '7725' ),
	"See $server/Automattic/vip-go-mu-plugins/pull/7699 and $server/Automattic/vip-go-mu-plugins/pull/7700" => array( '7699', '7700' ),
	"See $server/WordPress/two-factor/pull/7699"       => array(),
);

foreach ( $cases as $message => $expected ) {
	$actual = array_values( get_pr_ids_from_message( $message ) );
	if ( $expected !== $actual ) {
		throw new RuntimeException( sprintf( 'For "%s" expected [%s], got [%s]', $message, implode( ', ', $expected ), implode( ', ', $actual ) ) );
	}
}
