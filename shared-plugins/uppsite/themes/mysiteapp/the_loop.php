<?php
/**
 * Iterates over a query loop and prints the results.
 * @note    Because WP_Query->the_post() stores the WP_Post object in $GLOBALS, including the "post.php" template
 *          of us will make all the global functions (e.g. 'the_ID()', 'the_title()') work just fine.
 */
$query = mysiteapp_get_current_query();

if ($query->have_posts()) {
    $iterator = 0;

    // `mysiteapp_clean_output` - Avoid 'loop_end' output, if any
    while (mysiteapp_clean_output(array($query, 'have_posts'))) {
        // Avoid 'loop_start' output, if any (some plugins make it)
        // and make $GLOBALS['post'] populated for the next function (get_the_ID())
        mysiteapp_clean_output(array($query, 'the_post'));

        // Adds the post to the list of excluded posts (will not be used if not in "homepage" view mode)
        mysiteapp_homepage_add_post(get_the_ID());

        mysiteapp_print_post($iterator);

        $iterator++;
    }

    wp_reset_postdata();
}
?>