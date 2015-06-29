<?php
/*
Plugin Name: Advanced Blacklist
Description: Plugin to improve blacklist functionality by allowing the banning of more specific terms
Version: 1.0
Author: James Dowd
License: GPL2
*/

function nbcs_user_blacklist( $approved ) {
	global $commentdata;
	
	$blacklist_keys = trim( get_option( 'nbcs-user-blacklist' ) );
	
	if ( '' == $blacklist_keys )
		return $approved;
	$words = explode( "\n", $blacklist_keys );
	
	$commentdata['comment_author_IP'] = preg_replace( '/[^0-9a-fA-F:., ]/', '',$_SERVER['REMOTE_ADDR'] );
	
	foreach ( (array) $words as $word ) {
		$word = trim( strtolower( $word ) );
		if ( empty( $word ) )
			continue;
			
		if (   $word == strtolower( $commentdata['comment_author'] )
			|| $word == strtolower( $commentdata['comment_author_email'] )
			|| ('http://' . $word) == strtolower( $commentdata['comment_author_url'] )
		    || $word == strtolower( $commentdata['comment_author_IP'] )
		 )  {
		    return 'spam';
		 }
	}
	return $approved;
}
add_filter( 'pre_comment_approved', 'nbcs_user_blacklist' );

function nbcs_word_blacklist( $approved ) {
	global $commentdata;
	
	$blacklist_keys = trim( get_option( 'nbcs-word-blacklist' ) );
	
	if ( '' == $blacklist_keys )
		return $approved;
	$words = explode( "\n", $blacklist_keys );
	
	foreach ( (array) $words as $word ) {
		$word = trim( $word );
		if ( empty( $word ) )
			continue;
			
		// Do some escaping magic so that '#' chars in the
		// spam words don't break things:
		$word = preg_quote( $word, '#' );
		
		if ( preg_match( "#\b$word\b#i", $commentdata['comment_content'] ) )
		    return 'spam';
	}
	return $approved;
}
add_filter( 'pre_comment_approved', 'nbcs_word_blacklist' );

function nbcs_advanced_blacklist_settings_api_init() {
	wp_enqueue_script( 'nbcs_advanced_blacklist_script', plugins_url( 'script.js', __FILE__ ) ); 
	wp_enqueue_style( 'nbcs_advanced_blacklist_style', plugins_url( 'style.css', __FILE__ ) );
	
	add_settings_field( 'nbcs-word-blacklist', 'Comment Word Blacklist', 'nbcs_word_blacklist_settings_field', 'discussion', 'default' );
	add_settings_field( 'nbcs-user-blacklist', 'Comment User Blacklist', 'nbcs_user_blacklist_settings_field', 'discussion', 'default' );
	add_settings_field( 'nbcs-advanced-blacklist-tools', 'Advanced Blacklist Tools', 'nbcs_advanced_blacklist_settings_field', 'discussion', 'default' );
	
	register_setting( 'discussion','nbcs-word-blacklist' );
	register_setting( 'discussion','nbcs-user-blacklist' );
}
add_action( 'admin_init', 'nbcs_advanced_blacklist_settings_api_init' );

function nbcs_word_blacklist_settings_field() {
?>
	<p>When a comment contains any of these words in its content, it will be marked as spam. One word or phrase per line. Unlike the general comment blacklist, it will not match inside words, so "press" will <em>not</em> match "WordPress".</p>
	<textarea name="nbcs-word-blacklist" id="nbcs-word-blacklist" rows="10" cols="50" class="large-text code"><?php echo esc_textarea( get_option ( 'nbcs-word-blacklist' ) ); ?></textarea>
<?php
}

function nbcs_user_blacklist_settings_field() {
?>
	<p>When a comment is posted by a user with username, e-mail, site, or IP matching any of these terms, it will be marked as spam. One term per line. It will match complete terms only.</p>
	<textarea name="nbcs-user-blacklist" id="nbcs-user-blacklist" rows="10" cols="50" class="large-text code"><?php echo esc_textarea( get_option ( 'nbcs-user-blacklist' ) ); ?></textarea>
<?php
}

function nbcs_advanced_blacklist_settings_field() {
?>
	<div class="advanced-blacklist-controls">
		<p>Use this form to easily add a term to the appropriate blacklist based on whether you are blocking a word or a user.</p>
		<div>
			Enter the term to be blocked here: <input type="text" name="new-blacklist-term" id="new-blacklist-term" /><br />
			<input type="radio" name="advanced-blacklist-term-type" value="default" checked="checked" /> <span id="advanced-blacklist-default-description">Block any post which contains this term in the post contents or user information</span><br />
			<input type="radio" name="advanced-blacklist-term-type" value="word" /> <span id="advanced-blacklist-word-description">Block any post which contains this exact word in the post contents or user information</span><br />
			<input type="radio" name="advanced-blacklist-term-type" value="user" /> <span id="advanced-blacklist-user-description">Block any post which is made by a user with this username, IP address, or e-mail address</span><br />
			<button id="advanced-blacklist-add-button" disabled="disabled">Add Term to Blacklist</button>
		</div>
	</div>
<?php
}

?>
