<?php

class VIPV2_SMTP {
	function init() {
		add_action( 'phpmailer_init', array( 'VIPV2_SMTP', 'phpmailer_init' ) );
	}

	function phpmailer_init( $phpmailer ) {
		global $all_smtp_servers;

		$phpmailer->isSMTP();
		$phpmailer->Sender = "donotreply@{$_SERVER['SERVER_NAME']}";

		if ( ! is_array( $all_smtp_servers ) || empty( $all_smtp_servers ) )
			return;

		if ( count( $all_smtp_servers ) > 1 )
			shuffle( $all_smtp_servers );

		$phpmailer->Host = current( $all_smtp_servers );
	}
}

( new VIPV2_SMTP() )->init();
