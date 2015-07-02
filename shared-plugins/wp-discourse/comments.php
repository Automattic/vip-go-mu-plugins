<?php
$custom             = get_post_custom();
$options            = get_option( 'discourse' );
$permalink          = (string) $custom['discourse_permalink'][0];
$discourse_url_name = preg_replace( '(https?://)', '', $options['url'] );
if ( isset( $custom['discourse_comments_raw'] ) ) {
	$discourse_info = json_decode( $custom['discourse_comments_raw'][0] );
} else {
	$discourse_info = array();
}
$defaults = array(
	'posts_count'  => 0,
	'posts'        => array(),
	'participants' => array(),
);

// Add some protection in the event our metadata doesn't look how we expect it to
$discourse_info = (object) wp_parse_args( (array) $discourse_info, $defaults );

$more_replies = ( $discourse_info->posts_count - count( $discourse_info->posts ) - 1 );
$more         = count( $discourse_info->posts ) == 0 ? '' : 'more ';

if ( $more_replies == 0 ) {
	$more_replies = '';
} elseif ( $more_replies == 1 ) {
	$more_replies = '1 ' . $more . 'reply';
} else {
	$more_replies = $more_replies . ' ' . $more . 'replies';
}

$discourse_html    = '';
$comments_html     = '';
$participants_html = '';
if ( count( $discourse_info->posts ) > 0 ) {
	foreach ( $discourse_info->posts as &$post ) {
		$comment_html = wp_kses_post( Discourse::comment_html() );
		$comment_html = str_replace( '{discourse_url}', esc_url( $options['url'] ), $comment_html );
		$comment_html = str_replace( '{discourse_url_name}', esc_html( $discourse_url_name ), $comment_html );
		$comment_html = str_replace( '{topic_url}', esc_url( $permalink ), $comment_html );
		$comment_html = str_replace( '{avatar_url}', esc_url( Discourse::avatar( $post->avatar_template, 64 ) ), $comment_html );
		$comment_html = str_replace( '{user_url}', esc_url( Discourse::homepage( $options['url'], $post ) ), $comment_html );
		$comment_html = str_replace( '{username}', esc_html( $post->username ), $comment_html );
		$comment_html = str_replace( '{fullname}', esc_html( $post->name ), $comment_html );
		$comment_html = str_replace( '{comment_body}', wp_kses_post( $post->cooked ), $comment_html ); // emoticons don't have absolute urls
		$comment_html = str_replace( '{comment_created_at}', mysql2date( get_option( 'date_format' ), $post->created_at ), $comment_html );
		$comments_html .= $comment_html;
	}
	foreach ( $discourse_info->participants as &$participant ) {
		$participant_html = wp_kses_post( Discourse::participant_html() );
		$participant_html = str_replace( '{discourse_url}', esc_url( $options['url'] ), $participant_html );
		$participant_html = str_replace( '{discourse_url_name}', esc_html( $discourse_url_name ), $participant_html );
		$participant_html = str_replace( '{topic_url}', esc_url( $permalink ), $participant_html );
		$participant_html = str_replace( '{avatar_url}', esc_url( Discourse::avatar( $participant->avatar_template, 64 ) ), $participant_html );
		$participant_html = str_replace( '{user_url}', esc_url( Discourse::homepage( $options['url'], $participant ) ), $participant_html );
		$participant_html = str_replace( '{username}', esc_html( $participant->username ), $participant_html );
		$participant_html = str_replace( '{fullname}', esc_html( $participant->name ), $participant_html );
		$participants_html .= $participant_html;
	}
	$discourse_html = wp_kses_post( Discourse::replies_html() );
	$discourse_html = str_replace( '{more_replies}', esc_html( $more_replies ), $discourse_html );
} else {
	$discourse_html = wp_kses_post( Discourse::no_replies_html() );
}
$discourse_html = str_replace( '{discourse_url}', esc_url( $options['url'] ), $discourse_html );
$discourse_html = str_replace( '{discourse_url_name}', esc_html( $discourse_url_name ), $discourse_html );
$discourse_html = str_replace( '{topic_url}', esc_url( $permalink ), $discourse_html );
$discourse_html = str_replace( '{comments}', wp_kses_post( $comments_html ), $discourse_html );
$discourse_html = str_replace( '{participants}', wp_kses_post( $participants_html ), $discourse_html );
echo $discourse_html;
