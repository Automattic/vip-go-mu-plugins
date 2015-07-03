<?php if(isset($title)): ?>
<label class="option-label" for="zemanta_options_<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $title ); ?></label>
<?php endif; ?>

<input id="zemanta_options_<?php echo esc_attr( $field ); ?>" class="regular-text code" name="zemanta_options[<?php echo esc_attr( $field ); ?>]" size="40" type="text" value="<?php echo isset($option) && !empty($option) ? esc_attr( $option ) : (isset($default_value) ? esc_attr( $default_value ) : ''); ?>" <?php disabled( $disabled ); ?>  />

<?php if (isset($description)): ?>

  <p class="description">
    <?php echo wp_post_kses( $description ); ?>
  </p>

<?php endif; ?>