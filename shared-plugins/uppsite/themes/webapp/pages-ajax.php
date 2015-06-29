<?php
if (uppsite_is_business_panel()) {
    $pages = uppsite_get_biz_pages();
    $pages_result = array();
    $i = 0;
    $exclude_parents = array();
    $postsCount = wp_count_posts()->publish;
    $hasBlog = false;
    
    $pageOrder = 1;
    foreach ($pages as $page) {
        if (strpos($page->post_title, "Blog") !== false && $page->post_parent == 0 && $postsCount > 2) {
            // Guessing this is a link to blog, so replace that.
            $exclude_parents[] = $page->ID; // Make its children disappear.
            $page->post_title = __("Blog");
            $hasBlog = true;
        }
        if ($page->post_parent > 0 && in_array($page->post_parent, $exclude_parents)) {
            continue; // Skip this, he has no parent.
        }
        $pages_result[$i]['permalink'] = get_page_link($page->ID);
        $pages_result[$i]['title'] = $page->post_title;

        $pages_result[$i]['menu_order'] = $page->menu_order;
        $pages_result[$i]['post_parent'] = $page->post_parent;

        $i++;
    }

    if (!$hasBlog && uppsite_get_type() == UPPSITE_TYPE_BOTH) {
        // Add blog, if not present in pages list
        $pages_result[$i] = array(
            'permalink' => '',
            'title' => 'Blog',
            'menu_order' => 999,
            'post_parent' => 0
        );
    }

    print json_encode($pages_result);
} else {
    uppsite_posts_list('wp_list_pages');
}

