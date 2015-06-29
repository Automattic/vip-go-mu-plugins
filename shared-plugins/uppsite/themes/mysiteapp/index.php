<?php
/**
 * Index page
 */
$show_homepage_display = mysiteapp_should_show_homepage();
$wrap_with_homepage_tags = $show_homepage_display && !mysiteapp_homepage_is_only_show_posts();
$should_hide_posts = mysiteapp_should_hide_posts() || $show_homepage_display;

get_template_part('header');
?><title><![CDATA[]]></title>
<posts>
    <?php if ($wrap_with_homepage_tags): ?><homepage><?php endif; ?>
    <?php if (!$should_hide_posts || $show_homepage_display) { get_template_part( 'the_loop' ); } ?>
    <?php if ($wrap_with_homepage_tags): ?></homepage><?php endif; ?>
</posts>
<?php
get_template_part($show_homepage_display ? 'homepage' : 'sidebar');
get_template_part('footer', 'nav');
