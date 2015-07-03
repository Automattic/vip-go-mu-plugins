<?php
/**
 * Don't show the warning message about permalinks and rewrite rules
 * because the rewrite rules are auto-flushed on deploy for approved themes
 */
add_filter( 'vapp_display_rewrite_rules_notice', '__return_false' );