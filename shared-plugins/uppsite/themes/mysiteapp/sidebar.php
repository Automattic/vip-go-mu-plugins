<?php
/**
 * Sidebar
 */
?>
<sidebar>
    <?php if (mysiteapp_should_show_sidebar()): ?>
	<categorys>
		<?php wp_list_categories(); ?>
	</categorys>
	<archives>
		<?php wp_get_archives(); ?>
	</archives>
	<pages>
		<?php wp_list_pages(); ?>
	</pages>
	<links>
		<?php wp_list_bookmarks(); ?>
	</links>
	<tags><?php
		if (function_exists('wp_tag_cloud')){
			 wp_tag_cloud('number=100&echo=true');
		}
	?></tags>
    <?php endif; // mysiteapp_should_show_sidebar ?>
	<logout>
		<url><![CDATA[<?php echo mysiteapp_logout_url_wrapper() ?>]]></url>
	</logout>
</sidebar>