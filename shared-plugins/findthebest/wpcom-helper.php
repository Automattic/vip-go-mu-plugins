<?php

//findthebest initializes on `plugins_loaded` which was already been fired on wpcom, let's hook the init to `after_setup_theme`
add_action( 'after_setup_theme', array( 'FindTheBest_VisualSearch', 'init' ) );
