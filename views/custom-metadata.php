<?php
/**
 * Parse.ly custom metadata output
 *
 * @package      Parsely\wp-parsely
 * @author       Parse.ly
 * @copyright    2012 Parse.ly
 * @license      GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Parsely;

?>
<meta name="parsely-metadata" content="<?php echo esc_attr( $parsely_page['custom_metadata'] ); ?>" />
