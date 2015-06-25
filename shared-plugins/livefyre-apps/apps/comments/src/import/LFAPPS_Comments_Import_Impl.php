<?php
require_once 'LFAPPS_Comments_Import.php';

global $livefyre_comment_filter_enabled;
global $wpdb;

class LFAPPS_Comments_Import_Impl implements LFAPPS_Comments_Import {

    function __construct($lf_core) {

        $this->lf_core = $lf_core;
        $this->ext = $lf_core->ext;
        $this->ext->setup_import($this);
    }

    static function skip_trackback_filter($c) {

        if ($c->comment_type == 'trackback' || $c->comment_type == 'pingback') {
            return false;
        }
        return true;
    }

    function admin_import_notice() {

        return; //todo: re-enable this
        if (!is_admin() || $_GET["page"] != "livefyre" ||
                get_option('livefyre_apps-livefyre_import_status', '') != '' ||
                get_option('livefyre_apps-livefyre_site_id', '') == '') {
            return;
        }
        echo "<div id='livefyre-import-notice' class='updated fade'><p><a href='?page=livefyre&livefyre_import_begin=true'>Click here</a> to import your comments.</p></div>";
    }

    function begin() {

        if (!isset($_GET['page']) || $_GET['page'] != 'livefyre_apps_comments' || !isset($_GET['livefyre_import_begin'])) {
            return;
        }
        $siteId = get_option('livefyre_apps-livefyre_site_id', '');
        if ($siteId == '') {
            return;
        }
        $url = LFAPPS_Comments_Core::$quill_url . '/import/wordpress/' . $siteId . '/start';
        $http = new LFAPPS_Http_Extension;
        $resp = $http->request($url, array('method' => 'POST'));

        if (is_wp_error($resp)) {
            $status = 'error';
            $message = $resp->get_error_message();
        } else {
            $json = json_decode($resp['body']);
            $status = $json->status;
            $message = $json->message;
        }

        if ($status == 'error') {
            update_option('livefyre_apps-livefyre_import_status', 'error');
            update_option('livefyre_apps-livefyre_import_message', $message);
            $this->ext->delete_option('livefyre_v3_notify_installed');
        } else {
            update_option('livefyre_apps-livefyre_import_status', 'pending');
            $this->ext->delete_option('livefyre_v3_notify_installed');
        }
    }

    function check_activity_map_import() {

        if (!isset($_POST['activity_map'])) {
            return;
        }

        global $wpdb;
        $activity_map = $_POST['activity_map'];
        $rows = explode("\n", $activity_map);
        $i = 0;

        foreach ($rows as $row) {
            $rowparts = explode(",", $row);
            $this->ext->activity_log($rowparts[0], $rowparts[1], $rowparts[2]);
            $i++;
        }
        $this->ext->update_option('livefyre_activity_id', $rowparts[0]);
        update_option('livefyre_apps-livefyre_import_status', 'complete');
        $date_formatted = 'Completed on ' . date('d/m/Y') . ' at ' . date('h:i a');
        update_option('livefyre_apps-livefyre_import_message', $date_formatted);
        $this->ext->delete_option('livefyre_v3_notify_installed');
        echo "ok";
        exit;
    }

    function check_import() {
        // Make sure we don't check import on every page load as it may be pretty resource demanding
		if ( !isset($_GET['page']) || $_GET['page'] != 'livefyre_apps_comments' ) {
            return;
        }
		if ( get_option('livefyre_apps-livefyre_import_status', 'uninitialized') == 'uninitialized' && $this->detect_default_comment() ) {
            update_option('livefyre_apps-livefyre_import_status', 'complete');
            $this->ext->delete_option('livefyre_v3_notify_installed');
            return;
        }
        // Make sure we're allowed to import comments
        if (!isset($_GET['livefyre_comment_import']) || !isset($_GET['offset'])) {
            return;
        }
        // Get the decoded sig values from the $_POST object
        $sig = $_POST['sig'];
        $sig_created = urldecode($_POST['sig_created']);
        // Check the signature
        $key = get_option('livefyre_apps-livefyre_site_key');
        $string = 'import|' . sanitize_text_field($_GET['offset']) . '|' . $sig_created;
        if (getHmacsha1Signature(base64_decode($key), $string) != $sig || abs($sig_created - time()) > 259200) {
            echo 'sig-failure';
            exit;
        } else {
            $siteId = get_option('livefyre_apps-livefyre_site_id', '');
            if ($siteId != '') {
                $response = $this->extract_xml($siteId, intval(sanitize_text_field($_GET['offset'])));
                echo esc_html($response);
                exit;
            } else {
                echo 'missing-blog-id';
                exit;
            }
        }
    }

    function check_utf_conversion() {

        global $livefyre_comment_filter_enabled;

        if (!isset($livefyre_comment_filter_enabled)) {
            $test_string = 'Testing 1 2 3!! ?/#@';
            $converted = $this->comment_data_filter($test_string, true);
            if ($converted != $test_string) {
                $livefyre_comment_filter_enabled = false;
            } else {
                $livefyre_comment_filter_enabled = true;
            }
        }
        return $livefyre_comment_filter_enabled;
    }

    function comment_data_filter($comment, $test = false) {

        if ($test || $this->check_utf_conversion()) {
            $before = $comment;
            if (function_exists('iconv')) {
                $unicode = array_filter($this->utf8_to_unicode_code($comment), array(&$this, 'filter_unicode_longs'));
                $comment = $this->unicode_code_to_utf8($unicode);
            }
            $after = $comment;
            if ($this->ext->get_option('livefyre_cleaned_data', 'no') == 'no' && $before != $after) {
                $this->ext->update_option('livefyre_cleaned_data', 'yes');
                $this->report_error("before and after are different when exporting content, this means we saw bad data and cleaned it up\nbefore:\n$before\n\nafter:\n$after");
            }
        }
        $comment = preg_replace('/\&/', '&amp;', $comment);
        $comment = preg_replace('/\>/', '&gt;', $comment);
        $comment = preg_replace('/\</', '&lt;', $comment);
        return $comment;
    }

    function extract_xml($siteId, $offset = 0) {

        $maxqueries = 50;
        $maxlength = 500000;
        $index = $offset;
        $next_chunk = false;
        $total_queries = 0;
        do {
            $total_queries++;
            if ($total_queries > $maxqueries) {
                $next_chunk = true;
                break;
            }
            $args = array(
                'post_type' => 'any',
                'numberposts' => 20,
                'offset' => $index
            );
            $myposts = new WP_Query($args);
            if (!isset($articles)) {
                $articles = '';
            }
            $inner_idx = 0;
            if ($myposts->have_posts()) {
                while ($myposts->have_posts()) {
                    $myposts->the_post();
                    if (($parent_id = wp_is_post_revision(get_the_ID()))) {
                        $post_id = $parent_id;
                    } else {
                        $post_id = get_the_ID();
                    }
                    $newArticle = '<article id="' . $post_id . '"><title>' . $this->comment_data_filter(get_the_title()) . '</title><source>' . get_permalink(get_the_ID()) . '</source>';
                    if (get_post_time('c', true) != null && !strstr(get_post_time('c', true), '0000-00-00')) {
                        $newArticle .= '<created>' . preg_replace('/\s/', 'T', get_post_time('c', true)) . 'Z</created>';
                    }
                    $comment_array = get_approved_comments(get_the_ID());
                    $comment_array = array_filter($comment_array, array('LFAPPS_Comments_Import_Impl', 'skip_trackback_filter'));
                    foreach ($comment_array as $comment) {
                        $comment_content = $this->comment_data_filter($comment->comment_content);
                        if ($comment_content == "") {
                            continue; #don't sync blank
                        }
                        $commentParent = ($comment->comment_parent ? " parent-id=\"$comment->comment_parent\"" : '');
                        $commentXML = "<comment id=\"$comment->comment_ID\"$commentParent>";
                        $commentXML .= '<author format="html">' . $this->comment_data_filter($comment->comment_author) . '</author>';
                        $commentXML .= '<author-email format="html">' . $this->comment_data_filter($comment->comment_author_email) . '</author-email>';
                        $commentXML .= '<author-url format="html">' . $this->comment_data_filter($comment->comment_author_url) . '</author-url>';
                        $commentXML .= '<body format="wphtml">' . $comment_content . '</body>';
                        $use_date = $comment->comment_date_gmt;
                        if ($use_date == '0000-00-00 00:00:00Z') {
                            $use_date = $comment->comment_date;
                        }
                        if ($use_date != null && !strstr($use_date, '0000-00-00')) {
                            $commentXML .= '<created>' . preg_replace('/\s/', 'T', $use_date) . 'Z</created>';
                        } else {
                            // We need to supply a datetime so the XML parser does not fail
                            $now = new DateTime;
                            $commentXML .= '<created>' . $now->format('Y-m-d\TH:i:s\Z') . '</created>';
                        }
                        $commentXML .= '</comment>';
                        $newArticle .= $commentXML;
                    }
                    $newArticle .= '</article>';
                    if (strlen($newArticle) + strlen($articles) > $maxlength && strlen($articles)) {
                        $next_chunk = true;
                        break;
                    } else {
                        $inner_idx += 1;
                        $articles .= $newArticle;
                    }
                    unset($newArticle);
                }
            }
        } while ($myposts->found_posts != 0 && !$next_chunk && ($index = $index + 10));
        if (strlen($articles) == 0) {
            return 'no-data';
        } else {
            return 'to-offset:' . ($inner_idx + $index) . "\n" . $this->wrap_xml($articles);
        }
    }

    function filter_unicode_longs($long) {

        return ($long == 0x9 || $long == 0xa || $long == 0xd || ($long >= 0x20 && $long <= 0xd7ff) || ($long >= 0xe000 && $long <= 0xfffd) || ($long >= 0x10000 && $long <= 0x10ffff));
    }

    function report_error($message) {

        $args = array('data' => array('message' => $message, 'method' => 'POST'));
        $url = $this->lf_core->http_url . '/site/' . get_option('livefyre_apps-livefyre_site_id');
        $this->lf_core->lf_domain_object->http->request($url . '/error', $args);
    }

    function unicode_code_to_utf8($unicode_list) {

        $result = "";
        foreach ($unicode_list as $key => $value) {
            $one_character = pack("L", $value);
            $result .= iconv("UTF-32", "UTF-8", $one_character);
        }
        return $result;
    }

    function utf8_to_unicode_code($utf8_string) {

        $expanded = iconv("UTF-8", "UTF-32", $utf8_string);
        return unpack("L*", $expanded);
    }

    function wrap_xml(&$articles) {

        return '<?xml version="1.0" encoding="UTF-8"?><site xmlns="http://livefyre.com/protocol" type="wordpress">' . $articles . '</site>';
    }

    function detect_default_comment() {
        // Checks to see if the site only has the default WordPress comment
        // If the site has 0 comments or only has the default comment, we skip the import
        if (wp_count_comments()->total_comments > 1) {
            // If the site has more than one comment, show import button like normal
            return False;
        }
        // We take all the comments from post id 1, because this post has the default comment if it was not deleted
        $comments = get_comments('post_id=1');
        if (count($comments) == 0 || ( count($comments) == 1 && $comments[0]->comment_author == 'Mr WordPress' )) {
            // If there are 0 approved comments or if there is only the default WordPress comment, return True
            return True;
        }
        // If there is 1 comment but it is not the default comment, return False
        return False;
    }

}
