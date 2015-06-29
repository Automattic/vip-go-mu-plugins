<?php
/**
 * Post tag
 */
$options = get_option('uppsite_options');

// Avatar
$avatar = null;
if (function_exists('get_the_author_meta')) {
	$avatar = get_avatar(get_the_author_meta('user_email'));
} elseif (function_exists('get_the_author_id')) {
	$avatar = get_avatar(get_the_author_id());
}
$avatar_url = uppsite_extract_src_url($avatar);

$show_post_content = get_query_var('mysiteapp_should_show_post');

if ($show_post_content) {
    ob_start(); // Catch any filter that uses "print()";
	$content = apply_filters('the_content',get_the_content());
	ob_end_clean(); // Cleans any output made.
	$content_replacements = array('// <![CDATA[', '//<![CDATA[', '<![CDATA[', '// ]]>', '// ]]&gt;', '//]]>', ']]&gt;','/*<![CDATA[*/','/*]]>*/',']]>');
	$content = str_replace($content_replacements, NULL, $content);
}
?><post
	ID="<?php the_ID(); ?>"
	comments_num="<?php echo get_comments_number(); ?>"
	comments_open="<?php echo comments_open() ? "true" : "false" ?>"
	facebook_comments="<?php echo isset($options['fbcomment']) ? "true" : "false" ?>"
    require_name_email="<?php echo get_option('require_name_email') == 1 ? "true" : "false"?>">
	<permalink><![CDATA[<?php the_permalink(); ?>]]></permalink>
	<thumb_url><![CDATA[<?php echo mysiteapp_extract_thumbnail(); ?>]]></thumb_url>
	<title><![CDATA[<?php echo html_entity_decode(get_the_title(), ENT_QUOTES, 'UTF-8'); ?>]]></title>
	<time><![CDATA[<?php the_time('m/d/y G:i'); ?>]]></time>
	<unix_time><![CDATA[<?php the_time('U'); ?>]]></unix_time>
	<member>
		<name><![CDATA[<?php the_author();?>]]></name>
		<member_link><![CDATA[<?php the_author_link(); ?>]]></member_link>
		<avatar><![CDATA[<?php echo $avatar_url; ?> ]]></avatar>
	</member>
	<excerpt><![CDATA[<?php echo html_entity_decode(get_the_excerpt(), ENT_QUOTES, 'UTF-8'); ?>]]></excerpt>
	<?php if ($show_post_content): ?>
	<content>
		<![CDATA[<?php echo html_entity_decode(mysiteapp_fix_videos($content), ENT_QUOTES, 'UTF-8'); ?>]]>
	</content>
	<?php endif;?>
	<comments_link><![CDATA[<?php comments_link(); ?>]]></comments_link>
	<?php if ($show_post_content): ?>
		<tags><?php if(function_exists('the_tags')) the_tags(); ?></tags>
	<?php endif; ?>
	<categorys><?php if(function_exists('the_category')) the_category(); ?></categorys>
</post>