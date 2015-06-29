<input id="zemanta_options_<?php echo esc_attr( $field ); ?>" name="zemanta_options[<?php echo esc_attr( $field ); ?>]" type="checkbox" value="1" <?php checked( $option ); ?> <?php disabled( $disabled ); ?>  />

<?php if (isset($title)): ?>

  <label for="zemanta_options_<?php echo esc_attr( $field ); ?>">
    <?php echo esc_html( $title ); ?>
  </label>

<?php endif; ?>

<?php if (isset($description)): ?>

  <p>
    <?php echo wp_post_kses( $description ); ?>
  </p>

<?php endif; ?>