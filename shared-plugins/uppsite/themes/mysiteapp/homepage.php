<categories>
<?php
    if (!mysiteapp_homepage_is_only_show_posts()) {
        $cats_ar = uppsite_homepage_get_categories();

        foreach ($cats_ar as $cat) {
            $cat_query = array(
                'cat' => $cat,
                'posts_per_page' =>  mysiteapp_homepage_cat_posts(),
                'order' => 'desc'
            );

            if (!mysiteapp_is_fresh_wordpress_installation()) {
                $cat_query['post__not_in'] = mysiteapp_homepage_get_excluded_posts();
            }
            $query = mysiteapp_set_current_query($cat_query);

            if ($query->post_count > 0) {
                // Print only categories that exist
                $catText = wp_list_categories(array( 'include' => $cat, 'echo' => 0 ));
                print str_replace("</category>", "", $catText);
                get_template_part('the_loop');
                print "</category>";
            }
        }
    }
    ?>
</categories>