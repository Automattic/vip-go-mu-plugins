<?php
/**
 * Plugin Name: VIP Codebase Manager
 * Description: Tools for managing the codebase (plugins, themes, etc) within the VIP ecosystem.
 * Version: 1.0.0
 * Author: Automattic
 */

if ( is_admin() && file_exists( __DIR__ . '/codebase-manager/codebase-manager.php' ) ) {
	require_once __DIR__ . '/codebase-manager/codebase-manager.php';
}
