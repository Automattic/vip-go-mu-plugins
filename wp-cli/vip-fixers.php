<?php

class VIP_Go_OneTimeFixers_Command extends WPCOM_VIP_CLI_Command {
	// Nothing to see here...
}

WP_CLI::add_command( 'vip fixers', 'VIP_Go_OneTimeFixers_Command' );
