<div class="fork-select">
<label for="branches"><?php _e( 'Branch:', 'post-forking' ); ?></label> <select name="branches" id="branches" class="branches">
	<option id="original" value="<?php echo esc_attr( $post->ID ); ?>" class="original"><?php _e( 'Original', 'original' ); ?></option>
<?php foreach ( $branches as $branch ) { ?>
	<option id="<?php echo esc_attr( $branch->post_name ); ?>" value="<?php echo esc_attr( $branch->ID ); ?>" <?php selected( $post->ID, $branch->ID ); ?>><?php echo $branch->post_title; ?></option>
<?php } ?>
</select>
<div class="clear"></div>
</div>
