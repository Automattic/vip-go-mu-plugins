<?php

// Don't allow Sailthru to override wp_mail
add_filter( 'pre_option_sailthru_override_wp_mail', '__return_false', 99999 ); // This should never happen

// Don't track logins
remove_action( 'wp_login', 'sailthru_user_login', 10 );
