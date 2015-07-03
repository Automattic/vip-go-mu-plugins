<?php
// Here is an example test that you can use to get started.

// This test creates a test and a control group.
// The test group will get 30% of qualified users.
// The control group will get the remaining 70%.

$chrome_test = new CheezTest(
	array(
		'name' =>  'chrome-ab',
		'groups' => array(
			'active' => array(
				'threshold' => 30,
			),
			'control' => array(
				'threshold' => 70,
			)
		),
		'is_qualified' =>  'return stripos( $_SERVER[ "HTTP_USER_AGENT" ], "Chrome" ) !== false;'
	)
);

// This will display a fixed position bar at the bottom of the screen to users in the test condition (i.e. 30% of Chrome users)
if ( CheezTest::is_in_group( 'chrome-ab', 'active' ) ) {
	add_action( 'wp_footer', function() {
		?>
		<div id="ab-chrome-bar">
			<span>Howdy, Chrome user!</span>
		</div>
		<style>
		#ab-chrome-bar {
			z-index: 1001;
			background: rgba( 33, 117, 155, 0.8 );
			color: #ddd;
			font-family: 'Helvetica Neue',Arial,Helvetica,sans-serif;
			font-size:14px;
			bottom: 0;
			left: 0;
			position:fixed;
			margin:0;
			padding: 0 20px;
			width: 100%;
			height: 28px;
			line-height: 28px;
		}
		</style>
		<?php
	} );
}