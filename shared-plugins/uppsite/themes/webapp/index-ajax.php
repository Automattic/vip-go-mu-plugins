<?php
ob_start();
$all_posts = array();
$i = 0;
$showContent = isset($_GET['view']) && $_GET['view'] == "ffull_rtitle";
while (have_posts()) {
    the_post();
    if ( !uppsite_should_filter( get_permalink() ) ) {
        // Return the post content only if display mode is "first ..." and this is the first post.
        $all_posts[] = uppsite_process_post($showContent && $i == 0);
    }
    $i++;
}

$total_count = wp_count_posts()->publish;
ob_end_clean();

if (isset($_GET['noPagination'])) {
    print json_encode($all_posts);
} else {
    print json_encode(
        array(
            'root' => $all_posts,
            'total_count' => $total_count
        )
);
}