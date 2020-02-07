<?php

add_filter( 'ep_skip_query_integration', function() {
	if ( $_GET[ 'es' ] ) {
		return false;
	}

	if ( true === get_option( 'vip_enable_elasticsearch'  ) ) {
		return false;
	}

	return true;
} );
