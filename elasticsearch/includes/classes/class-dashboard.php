<?php

add_action( 'admin_init', function() {
	remove_menu_page( 'elasticpress' );
} );
