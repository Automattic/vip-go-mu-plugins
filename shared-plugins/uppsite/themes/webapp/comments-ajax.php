<?php
/**
 * Comments array in ajax
 */
$comments = array();
if (isset($_GET['id'])) {
    ob_start();
    $comments  = get_comments(array('post_id' => intval($_GET['id'])));
    ob_end_clean();
}
print json_encode($comments);