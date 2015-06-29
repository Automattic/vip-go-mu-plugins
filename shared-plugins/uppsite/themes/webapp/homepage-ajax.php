<?php
// Start buffering
ob_start();
$all_posts = array();

/** Carousel items (The 'pre_get_posts' hook was called before, so items are relevant) */
$carouselPosts = array();
while (have_posts()) {
    the_post();
    if ( !uppsite_should_filter( get_permalink() ) ) {
        $carouselPosts[] = uppsite_process_post();
        mysiteapp_homepage_add_post(get_the_ID());
    }
}
$all_posts[] = array(
    'id' => 0,
    'posts' => $carouselPosts,
    'category' => ' _Carousel', // Carousel is getting a special category name, that will precede all other cat names.
    'category_order' => 0
);

/** Categories with posts */
$cats_array = uppsite_homepage_get_categories();

$catOrder = 1;
foreach ($cats_array as $cat) {
    $category_link = get_category_link($cat);
    if ( uppsite_should_filter($category_link) ) {
        // Check if we need to skip some categories.
        continue;
    }
    // Perform query for posts in this category
    $cat_query = array(
        'cat' => $cat,
        'posts_per_page' => mysiteapp_homepage_cat_posts(),
        'order' => 'desc'
    );
    if (!mysiteapp_is_fresh_wordpress_installation()) {
        $cat_query['post__not_in'] = mysiteapp_homepage_get_excluded_posts();
    }
    $query = mysiteapp_set_current_query($cat_query);

    // Print only categories that exist
    if ($query->have_posts()) {
        $current_cat = get_category($cat);
        while ($query->have_posts()) {
            $query->the_post(); // Will populate $GLOBALS['post']
            if (uppsite_should_filter( get_permalink() )) {
                continue;
            }
            $cur_post = uppsite_process_post();

            // Make sure we won't get the same post again.
            mysiteapp_homepage_add_post(get_the_ID());

            $cur_post['category'] = $current_cat->name;
            $cur_post['category_link'] = $category_link;
            $cur_post['category_order'] = $catOrder;
            
            $all_posts[] = $cur_post;
        }

        wp_reset_postdata();
    }
    
    $catOrder++;
}
// End buffering
ob_end_clean();

// Print in a format for the store
print json_encode(
    array(
        'root' => $all_posts
    )
);