<?php
/**
 * Views: Parse.ly JSON-LD output
 *
 * @package Parsely
 */

declare(strict_types=1);

namespace Parsely;

?>
<script type="application/ld+json">
<?php echo wp_json_encode( $metadata ) . "\n"; ?>
</script>
