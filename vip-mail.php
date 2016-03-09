<?php

/*
Plugin Name: VIP Mail
Description: Routes mail via Automattic mail servers
Author: Automattic
Version: 1.0
License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

class VIP_SMTP {
	function init() {
		add_action( 'phpmailer_init',    array( $this, 'phpmailer_init' ) );
		add_action( 'bp_phpmailer_init', array( $this, 'phpmailer_init' ) );
	}

	function phpmailer_init( $phpmailer ) {
		global $all_smtp_servers;

		$phpmailer->isSMTP();
		$phpmailer->Sender = "donotreply@wordpress.com";

		if ( ! is_array( $all_smtp_servers ) || empty( $all_smtp_servers ) )
			return;

		if ( count( $all_smtp_servers ) > 1 )
			shuffle( $all_smtp_servers );

		$phpmailer->Host = current( $all_smtp_servers );
	}
}

( new VIP_SMTP() )->init();
