<?php
/**
 * Options panel template
 */
?>
<div class="wrap">
	<h2><?php _e( 'Fork Options', 'post-forking' ); ?></h2>
	<form method="post" action="options.php">
		<?php settings_fields( 'fork' ); ?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e( 'Post Types', 'post-forking' ); ?></th>
				<td><ul>
					<?php foreach ( $this->get_potential_post_types() as $post_type ) { ?>
					<li><label><input type="checkbox" name="fork[post_types][<?php echo esc_attr( $post_type->name ); ?>]" <?php  checked( post_type_supports( $post_type->name, $this->post_type_support ) ); ?>> <?php echo esc_html( $post_type->labels->name ); ?></label></li>
				<?php } ?>
				</ul></td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
</div>