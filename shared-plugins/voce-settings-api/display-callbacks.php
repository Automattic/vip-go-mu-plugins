<?php

function vs_display_dropdown($value, $setting, $args) {
	if(!isset($args['options'])) {
		?>
		<p class="error">An options argument is required in the $args array to use vs_display_dropdown()</p>
		<?php
		return;
	} else {
		?>
		<select id="<?php echo $setting->get_field_id() ?>" name="<?php echo $setting->get_field_name() ?>">
			<?php
			foreach($args['options'] as $option_value => $option_text) {
				$selected = ($option_value == $value) ? 'selected="selected"' : '';
				echo "<option value='{$option_value}' $selected>{$option_text}</option>";
			}
			?>
		</select>
		<?php if(!empty($args['description'])) : ?>
			<span class="description"><?php echo $args['description'] ?></span>
		<?php endif; ?>
		<?php
	}
}

function vs_display_textarea($value, $setting, $args) {
	?>
	<textarea id="<?php echo $setting->get_field_id() ?>" name="<?php echo $setting->get_field_name() ?>" rows='7' cols='50' type='textarea'><?php echo esc_html($value) ?></textarea>
	<?php if(!empty($args['description'])) : ?>
		<span class="description"><?php echo $args['description'] ?></span>
	<?php endif; ?>
	<?php
}

function vs_display_checkbox($value, $setting, $args) {
	?>
	<input type="checkbox" id="<?php echo $setting->get_field_id() ?>" name="<?php echo $setting->get_field_name() ?>"<?php echo $value ? ' checked="checked"' : '' ?> />
	<?php if(!empty($args['description'])) : ?>
		<span class="description"><?php echo $args['description'] ?></span>
	<?php endif; ?>
	<?php
}
