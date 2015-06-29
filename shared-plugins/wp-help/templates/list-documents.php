<?php if ( !defined( 'ABSPATH' ) ) die(); ?>

<?php $pages = $this->get_help_topics_html( true ); ?>
<div id="cws-wp-help-listing">
<?php if ( current_user_can( 'publish_pages' ) || current_user_can( 'manage_options' ) ) : ?>
	<div id="cws-wp-help-actions">
	<?php if ( current_user_can( 'manage_options' ) ) : ?><a href="#" id="cws-wp-help-settings-on"><?php _ex( 'Settings', 'Button with limited space', 'wp-help' ); ?></a><?php endif; ?>
	<?php if ( current_user_can( 'publish_pages' ) ) : ?><a href="<?php echo admin_url( 'post-new.php?post_type=wp-help' ); ?>" id="cws-wp-help-add-new"><?php _ex( 'Add New', 'Button with limited space', 'wp-help' ); ?></a><a href="<?php echo admin_url( 'edit.php?post_type=wp-help' ); ?>" id="cws-wp-help-manage"><?php _ex( 'Manage', 'verb. Button with limited space', 'wp-help' ); ?></a><?php endif; ?>
	<div class="clear"></div>
	</div>
<?php endif; ?>
<div id="cws-wp-help-listing-labels"><input type="text" id="cws-wp-help-listing-label" value="<?php echo esc_attr( $this->get_option( 'h3' ) ); ?>" /></div>
<h3><?php echo esc_html( $this->get_option( 'h3' ) ); ?></h3>
<?php if ( !trim( $pages ) ) : ?>
	<?php if ( current_user_can( 'manage_options' ) ) : ?>
		<p id="cws-wp-help-nodocs"><?php _e( 'There are no help documents.', 'wp-help' ); ?></p>
	<?php else : ?>
		<p><?php _e( 'There are no help documents yet.', 'wp-help' ); ?></p>
	<?php endif; ?>
<?php endif; ?>
<div id="cws-wp-help-listing-wrap">
<ul<?php if ( current_user_can( $this->get_cap( 'publish_posts' ) ) ) { echo " class='can-sort' data-nonce='" . wp_create_nonce( 'cws-wp-help-reorder' ) . "'"; } ?>>
<?php echo $pages; ?>
</ul>
</div>
</div>

<?php if ( current_user_can( 'manage_options' ) ) : ?>
	<?php include( dirname( __FILE__ ) . '/settings.php' ); ?>
<?php endif; ?>

<div id="cws-wp-help-document">
<?php if ( $document_id ) : ?>
	<?php $document = new WP_Query( array( 'post_type' => self::POST_TYPE, 'p' => $document_id ) ); ?>
	<?php if ( $document->have_posts() ) : $document->the_post(); ?>
		<h2><?php the_title(); ?><?php edit_post_link( __( 'edit', 'wp-help' ), ' <small>', '</small>' ); ?><?php $this->explain_slurp( $document_id ); ?></h2>
		<?php the_content(); ?>
	<?php else : ?>
		<p><?php _e( 'The requested help document could not be found.', 'wp-help' ); ?></p>
	<?php endif; ?>
<?php endif; ?>
</div>
