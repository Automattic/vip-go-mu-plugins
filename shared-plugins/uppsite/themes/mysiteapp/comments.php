<?php
/**
 * Comments block
 */
$allComments = uppsite_comments_get( get_the_ID() );

if (is_array($allComments)) : ?>
	<comments comment_total="<?php echo count($allComments)?>">
		<?php //print !empty($comments_xml) ? $comments_xml : null; ?>
		<?php foreach ($allComments as $comment) : $GLOBALS['comment'] = $comment; ?>
	    <comment ID="<?php comment_ID() ?>" post_ID="<?php the_ID(); ?>" isApproved="<?php echo $comment->comment_approved == '0' ? "false" : "true" ?>">
			<permalink><![CDATA[<?php the_permalink() ?>]]></permalink>
			<time><![CDATA[<?php comment_date() ?>]]></time>
			<unix_time><![CDATA[<?php comment_date('U'); ?>]]></unix_time>
			<?php $member = mysiteapp_get_member_for_comment(); ?>
            <member>
                <name><![CDATA[<?php echo esc_html($member['author']) ?>]]></name>
                <member_link><![CDATA[<?php echo esc_html($member['link']) ?>]]></member_link>
                <avatar><![CDATA[<?php echo esc_html($member['avatar']) ?>]]></avatar>
            </member>
			<text><![CDATA[<?php comment_text() ?>]]></text>
        </comment>
		<?php endforeach; ?>
	</comments>
<?php endif; ?>
<newcommentfields>
	<?php mysiteapp_comment_form(); ?>
</newcommentfields>
