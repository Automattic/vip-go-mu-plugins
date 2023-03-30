<?php
/**
 * This file contains types information which is needed by wp-parsely plugin.
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

namespace Automattic\VIP\WP_Parsely_Integration;

/**
 * Class which represents configuration of Parsely (only for PHPDoc).
 *
 * @property bool is_pinned_version
 * @property string site_id
 * @property bool have_api_secret
 * @property bool is_javascript_disabled
 * @property bool is_autotracking_disabled
 * @property bool should_track_logged_in_users
 * @property array<ParselyTrackedPostTypes> tracked_post_types
 */
class ParselyConfigs {}

/**
 * Class which represents Parsely tracked post types (only for PHPDoc).
 *
 * @property string name
 * @property string post_type
 */
class ParselyTrackedPostTypes {}
