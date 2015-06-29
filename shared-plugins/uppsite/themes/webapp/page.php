<?php
/**
 * Page template
 * If homepage is set to pages, redirect to homepage instead.
 */

if (get_option('show_on_front') == "page" && is_front_page()) {
    // is_front_page() already validates that get_the_ID()==get_option('page_on_front') if the option
    // is set to display a page on the front, so no need to check it here.
    include(dirname(__FILE__) . "/index.php");
} else {
    include(dirname(__FILE__) . "/single.php");
}