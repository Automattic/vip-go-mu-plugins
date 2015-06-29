<?php
		$options = get_option( 'scroll_wp_options', ScrollKit::option_defaults() );
?><!DOCTYPE html>
<html>
	<head>
		<title><?php the_title() ?> | <?php bloginfo( 'name' ) ?></title>
		<meta name="viewport" content="width=980">
		<?php if ( get_post_meta( get_the_ID(), '_scroll_fonts', true ) ): ?>
			<link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=<?php echo join('|', get_post_meta( get_the_ID(), '_scroll_fonts', true ) ) ?>">
		<?php endif ?>
		<?php echo get_post_meta(get_the_ID(), '_scroll_head', true); ?>

		<?php $stylesheets = get_post_meta( get_the_ID(), '_scroll_css', true ) ?>
		<?php if ( is_array ( $stylesheets ) ): ?>
			<?php foreach ( $stylesheets as $stylesheet): ?>
				<link href="<?php echo esc_url( SCROLL_WP_SK_ASSET_URL . $stylesheet ); ?>" media="screen" rel="stylesheet" type="text/css" />
			<?php endforeach; ?>
		<?php endif; ?>

		<style type="text/css">
			<?php echo get_post_meta(get_the_ID(), '_scroll_style', true); ?>
			<?php echo stripslashes( $options['template_style'] ) ?>
		</style>
		<?php wp_head() ?>
	</head>
	<body class="published">

		<?php echo stripslashes( $options['template_header'] ) ?>
		<div id="skrollr-body">
			<?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ): ?>
				<!--
				scroll id: <?php echo get_post_meta(get_the_ID(), '_scroll_id', true) ?>
				-->
			<?php endif ?>
			<?php echo do_shortcode(get_post_meta(get_the_ID(), '_scroll_content', true)); ?>
	
		</div>

		<?php echo stripslashes( $options['template_footer'] ) ?>

		<?php $scripts = get_post_meta( get_the_ID(), '_scroll_js', true); ?>
		<?php if ( is_array($scripts) ) : ?>
			<?php foreach( get_post_meta( get_the_ID(), '_scroll_js', true) as $script): ?>
				<script src="<?php echo esc_url( SCROLL_WP_SK_ASSET_URL . $script ); ?>" type="text/javascript"></script>
			<?php endforeach ?>
		<?php endif ?>

		<?php wp_footer() ?>
	</body>
</html>
