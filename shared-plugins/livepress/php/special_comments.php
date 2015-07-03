<?php
$allowed_tags = array(
	'div' => array(
		'id' => array(),
		),
	);
echo wp_kses( LivePress_Comment::$comments_template_tag['start'], $allowed_tags );
// Use the included comments form if available, otherwise use built in comment form
if ( file_exists( LivePress_Comment::$comments_template_path ) ) {
	include(LivePress_Comment::$comments_template_path);
} else {
	// Build default form, showing only author and email (ommit default url field)
	$commenter = wp_get_current_commenter();
	$req       = get_option( 'require_name_email' );
	$aria_req  = ( $req ? " aria-required='true'" : '' );
	$args      = array(
		'fields' => apply_filters(
			'comment_form_default_fields', array(
				'author' => '<p class="comment-form-author">' .
					'<label for="author">' . esc_html__( 'Name', 'livepress' ) . '</label> ' .
					( $req ? '<span class="required">*</span>' : '' ) .
					'<input id="author" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) .
					'" size="30"' . $aria_req . ' /></p>',
				'email' => '<p class="comment-form-email"><label for="email">' . esc_html__( 'Email', 'livepress' ) . '</label> ' .
					( $req ? '<span class="required">*</span>' : '' ) .
					'<input id="email" name="email" type="text" value="' . esc_attr(  $commenter['comment_author_email'] ) .
					'" size="30"' . $aria_req . ' /></p>',
					)
			),
	);
	comment_form( $args );
}
echo wp_kses( LivePress_Comment::$comments_template_tag['end'], $allowed_tags );
