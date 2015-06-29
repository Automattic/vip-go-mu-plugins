
<div class="wrap">
  
  <h2><?php _e('Zemanta Plugin Status', 'zemanta'); ?></h2>

  <p>
    <?php _e('API key (if you have one, your WordPress can talk to Zemanta)', 'zemanta'); ?>: <strong><?php echo esc_html( $api_key ); ?></strong>
  </p>

  <p>
    <?php _e('Zemanta API Status', 'zemanta'); ?>
     
    <strong>
    <?php if ($api_test == 'ok'): ?>
      
      <span style="color: green;"><?php _e('OK', 'zemanta'); ?></span>

    <?php else: ?>

      <span style="color: red;"><?php _e('Failure', 'zemanta'); ?></span>
      
    <?php endif; ?>  
    </strong>
  </p>
  
</div>