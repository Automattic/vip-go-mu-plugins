<?php
/**
 * "Page" page
 */
get_template_part('header');
?><title><![CDATA[]]></title>
<posts>
<?php
    $curQuery = mysiteapp_get_current_query();
    if (!mysiteapp_should_hide_posts() && $curQuery->have_posts()) {
        $iterator = 0;
        // Avoid 'loop_end' output, if any
        while (mysiteapp_clean_output(array($curQuery, 'have_posts'))) {
            // Avoid 'loop_start' output, if any (some plugins make it)
            mysiteapp_clean_output(array($curQuery, 'the_post'));

            mysiteapp_print_post($iterator);

            $iterator++;
        }
        // Comments
        comments_template();

        wp_reset_postdata();
    }
?>
</posts>
<?php
get_template_part('sidebar');
get_template_part('footer', 'nav');