<?php

namespace Automattic\VIP\Split_Home_Site_URLs;

/**
 * Generic utilities
 */
require_once __DIR__ . '/split-home-site-urls/utils.php';

/**
 * Rewrite static asset URLs back to the home URL, as Core normally relies on site URL
 */
require_once __DIR__ . '/split-home-site-urls/asset-urls.php';

/**
 * Canonical and other redirect handling
 */
require_once __DIR__ . '/split-home-site-urls/redirects.php';

/**
 * Login screen handling
 */
require_once __DIR__ . '/split-home-site-urls/login.php';

/**
 * Other URL fixes
 */
require_once __DIR__ . '/split-home-site-urls/misc.php';
