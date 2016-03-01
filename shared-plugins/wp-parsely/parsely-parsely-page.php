<!-- BEGIN wp-parsely Plugin Version <?php echo esc_html(Parsely::VERSION); ?> -->
<meta name='wp-parsely_version' id='wp-parsely_version' content='<?php echo esc_html(Parsely::VERSION); ?>' />
<?php if (!empty($parselyPage) && isset($parselyPage["headline"])) : ?>
   <script type="application/ld+json">
   <?php echo json_encode($parselyPage); ?>
   </script>
<?php else: ?>
    <!-- parsleyPage is not defined / has no attributes.  What kind of page are you loading? -->
<?php endif; ?>
<!-- END wp-parsely Plugin Version <?php echo esc_html(Parsely::VERSION); ?> -->
<?php
