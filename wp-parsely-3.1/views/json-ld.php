<?php
/**
 * Parse.ly JSON-LD output
 *
 * @package      Parsely\wp-parsely
 * @author       Parse.ly
 * @copyright    2012 Parse.ly
 * @license      GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Parsely;

?>
<script type="application/ld+json">
<?php echo wp_json_encode( $parsely_page ) . "\n"; ?>
</script>
