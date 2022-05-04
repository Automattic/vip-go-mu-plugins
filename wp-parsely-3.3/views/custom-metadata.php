<?php
/**
 * Views: Parse.ly custom metadata output
 *
 * @package Parsely
 */

declare(strict_types=1);

namespace Parsely;

?>
<meta name="parsely-metadata" content="<?php echo esc_attr( $metadata['custom_metadata'] ); ?>" />
