<?php
/**
 * Removes any actions and filters that might
 * interrupt the standard behavior of the plugin.
 */
// Filters
remove_all_filters('get_sidebar');
remove_all_filters('get_header');
remove_all_filters('get_footer');
remove_all_filters('comments_template'); // Comment plugins
// Actions
remove_all_actions('loop_start');
remove_all_actions('loop_end');
remove_all_actions('the_excerpt');
remove_all_actions('wp_footer');
remove_all_actions('wp_print_footer_scripts');
remove_all_actions('comments_array');

/**
 * Helper class to print MySiteApp XML
 */
class MysiteappXmlParser {
    /**
     * The main function for converting to an XML document.
     * Pass in a multi dimensional array and this recursively loops through and builds up an XML document.
     *
     * @param array $data
     * @param string $rootNodeName - what you want the root node to be - defaults to data.
     * @param SimpleXMLElement $xml - should only be used recursively
     * @return string XML
     */
    public static function array_to_xml($data, $rootNodeName = 'data', $xml=null)
    {
        // turn off compatibility mode as simple xml throws a wobbly if you don't.
        if (ini_get('zend.ze1_compatibility_mode') == 1) {
            ini_set ('zend.ze1_compatibility_mode', 0);
        }

        if ($xml == null) {
            $xml = simplexml_load_string("<?xml version='1.0' encoding='utf-8'?><$rootNodeName />");
        }

        $childNodeName = substr($rootNodeName, 0, strlen($rootNodeName)-1);
        // loop through the data passed in.
        foreach($data as $key => $value) {
            // no numeric keys in our xml
            if (is_numeric($key)) {
                // make string key...
                $key = $childNodeName;
            }
            // if there is another array found recursively call this function
            if (is_array($value)) {
                $node = $xml->addChild($key);
                // recursive call.
                self::array_to_xml($value, $key, $node);
            } else  {
                // add single node.
                if (is_string($value)) {
                    $value = htmlspecialchars($value);
                    $xml->addChild($key,$value);
                } else {
                    $xml->addAttribute($key,$value);
                }
            }
        }
        // pass back as string. or simple xml object if you want!
        return $xml->asXML();
    }

    public static function print_xml($parsed_xml) {
        header("Content-type: text/xml");
        print $parsed_xml;
    }
}

/**
 * Prints an array in XML format
 * @param array $arr
 */
function mysiteapp_print_xml($arr) {
    $result = MysiteappXmlParser::array_to_xml($arr, "mysiteapp");
    MysiteappXmlParser::print_xml($result);
}

/**
 * Calls the specific function while discarding any output in the process
 * @param string $func    Function name
 * @return mixed    The function return value (if any)
 */
function mysiteapp_clean_output($func) {
    ob_start();
    $ret = call_user_func($func);
    ob_end_clean();
    return $ret;
}

/**
 * Should the plugin hide the posts?
 *
 * @return boolean
 */
function mysiteapp_should_hide_posts() {
    return isset($_REQUEST['posts_hide']) && $_REQUEST['posts_hide'] == '1';
}
/**
 * Should the plugin show the sidebar?
 *
 * @return boolean
 */
function mysiteapp_should_show_sidebar() {
    return isset($_REQUEST['sidebar_hide']) && $_REQUEST['sidebar_hide'] == '0';
}


/**
 * Tries to fetch picture from facebook profile
 * @param string $fb_profile    Profile link
 * @return string    URL to the image
 */
function mysiteapp_get_pic_from_fb_profile($fb_profile){
    if(stripos($fb_profile,'facebook') === FALSE) {
        return false;
    }
    $user_id = basename($fb_profile);

    return sprintf('http://graph.facebook.com/%s/picture?type=small', $user_id);
}


/**
 * Prints a member object for a comment
 */
function mysiteapp_get_member_for_comment() {
    $user = array(
        'author' => get_comment_author(),
        'link' => get_comment_author_url()
    );

    if (uppsite_comments_get_system() == UppSiteCommentSystem::FACEBOOK) {
        // Facebook profile pic
        $user['avatar'] = mysiteapp_get_pic_from_fb_profile($user['link']);
    }
    if (empty($user['avatar']) && function_exists('get_avatar')) {
        if (function_exists('htmlspecialchars_decode')){
            $user['avatar']  = htmlspecialchars_decode( uppsite_extract_src_url( get_avatar( get_comment_author_email() ) ) );
        }
    }
    return $user;
}

/**
 * Fixes youtube <embed> tags to fit mobile
 * @param array $matches
 * @return string Content with YouTube links fixed.
 */
function mysiteapp_fix_youtube_helper(&$matches) {
    $new_width = 270;

    $toreturn = $matches['part1']."%d".$matches['part2']."%d".$matches['part3'];
    $height = is_numeric($matches['objectHeight']) ? $matches['objectHeight'] : $matches['embedHeight'];
    $width = is_numeric($matches['objectWidth']) ? $matches['objectWidth'] : $matches['embedWidth'];
    $new_height = ceil(($new_width / $width) * $height);
    return sprintf($toreturn, $new_width, $new_height);
}

/**
 * Searches for youtube links and fixes them
 * @param array $matches
 * @return string Content with fixed YouTube objects
 */
function mysiteapp_fix_helper(&$matches) {
    if (strpos($matches['url1'], "youtube.com") !== false) {
        return mysiteapp_fix_youtube_helper($matches);
    }
    return $matches['part1'].$matches['objectWidth'].$matches['part2'].$matches['objectHeight'].$matches['part3'];
}

/**
 * Fix youtube embed videos, to show on mobile
 * @param string $subject    Text to search for youtube links
 * @return array    Matches
 */
function mysiteapp_fix_videos(&$subject) {
    $matches = preg_replace_callback("/(?P<part1><object[^>]*width=['\"])(?P<objectWidth>\d+)(?P<part2>['\"].*?height=['\"])(?P<objectHeight>\d+)(?P<part3>['\"].*?value=['\"](?P<url1>[^\"]+)['|\"].*?<\/object>)/ms", "mysiteapp_fix_helper", $subject);
    return $matches;
}

/**
 * Tells whether there is a need to display the post content.
 * Will display the content in these situations:
 * - No post layout defined
 * - In post page ('full')
 * - First post & in 'First full, Rest title' / 'First full, Rest excerpt'
 *
 * @param int $iterator    Number of the post (zero-based)
 */
function mysiteapp_should_show_post_content($iterator = 0) {
    $posts_layout = mysiteapp_get_posts_layout();
    if (
        empty($posts_layout) || // Not set
        $posts_layout == 'full' || // Full post
        ( $iterator < MYSITEAPP_BUFFER_POSTS_COUNT && ($posts_layout == 'ffull_rexcerpt' || $posts_layout == 'ffull_rtitle')) // First post of "First Full, rest X"
    ) {
        return true;
    }
    return false;
}

/**
 * Prints the post according to the layout
 *
 * @param int $iterator    Post number in the loop
 */
function mysiteapp_print_post($iterator = 0) {
    set_query_var('mysiteapp_should_show_post', mysiteapp_should_show_post_content($iterator));
    get_template_part('post');
}

/**
 * Wrapper function for 'wp_logout_url', as WP below 2.7.0 doesn't support it.
 *
 * @return string    Logout url
 */
function mysiteapp_logout_url_wrapper() {
    return wp_logout_url();
}

/**
 * Prints multiple errors
 * @param mixed $wp_error    WP error
 */
function mysiteapp_print_error($wp_error){
    ?><mysiteapp result="false">
    <?php foreach ($wp_error->get_error_codes() as $code): ?>
    <error><![CDATA[<?php echo esc_html($code) ?>]]></error>
    <?php endforeach; ?>
</mysiteapp><?php
    exit();
}

/**
 * Login hook
 * Performs login with username and password, and prints the userdata to the screen if login was successful
 * @param mixed $user    User object
 * @param string $username    Username
 * @param string $password    Password
 */
function mysiteapp_login($user, $username, $password){
    $user = wp_authenticate_username_password($user, $username, $password);
    if (is_wp_error($user)) {
        mysiteapp_print_error($user);
    } else {
        set_query_var('mysiteapp_user', $user);
        get_template_part('user');
    }
    exit();
}

/**
 * Helper function for converting html lists to xml
 * @param string $thelist  Output of the list
 * @param string $nodeName List type (category / tag / archive)
 * @param string $idParam Search for /$idParam-(\d+)/ for id
 * @return string   A XML-formatted list
 */
function mysiteapp_list($thelist, $nodeName, $idParam = "") {
    preg_match_all('/(?:class="[^"]*'.$idParam.'-(\d+)[^"]*".*?)?href=["\'](.*?)["\'].*?>(.*?)<\/a>/ms', $thelist, $result);
    $total = count($result[1]);
    $thelist = "";
    for ($i=0; $i<$total; $i++) {
        $curId = $result[1][$i];
        $thelist .= "\t<" . $nodeName . ( $curId ? " id=\"" . $curId ."\"" : "" ) .">\n\t\t";
        $thelist .= "<title><![CDATA[" . $result[3][$i] . "]]></title>\n\t\t";
        $thelist .= "<permalink><![CDATA[" . $result[2][$i] ."]]></permalink>\n\t";
        $thelist .= "</" . $nodeName .">\n";
    }
    return $thelist;
}

/**
 * Lists the categories
 * @param string $thelist Category list
 * @return string    XML List of categories
 */
function mysiteapp_list_cat($thelist){
    return mysiteapp_list($thelist, 'category', 'cat-item');
}

/**
 * List of tags
 * @param string $thelist Tags list
 * @return string    XML containing the tags
 */
function mysiteapp_list_tags($thelist){
    return mysiteapp_list($thelist, 'tag');
}
/**
 * List of archives
 * @param string $thelist Archives list
 * @return string Returns the list of archives as XML, if required.
 */
function mysiteapp_list_archive($thelist){
    return mysiteapp_list($thelist, 'archive');
}

/**
 * Pages list
 * @param string $thelist HTML pages list
 * @return string XML output
 */
function mysiteapp_list_pages($thelist){
    return mysiteapp_list($thelist, 'page', 'page-item');
}
/**
 * Links list
 * @param string $thelist HTML Links list
 * @return string XML output
 */
function mysiteapp_list_links($thelist){
    return mysiteapp_list($thelist, 'link');
}
/**
 * Next links
 * @param string $thelist Next list
 * @return string The list of navigation links in XML, if needed
 */
function mysiteapp_navigation($thelist){
    return mysiteapp_list($thelist, 'navigation');
}

/**
 * Gracefully shows an XML error
 * Performs as an error handler
 * @param string $message    The message
 * @param string $title    Title
 * @param mixed $args    Arguments
 */
function mysiteapp_error_handler($message, $title = '', $args = array()) {
    ?><mysiteapp result="false">
    <error><![CDATA[<?php echo esc_html($message) ?>]]></error>
</mysiteapp>
<?php
    die();
}
/**
 * Redirects to UppSite's error handler
 * @param string $function Die handling function
 */
function mysiteapp_call_error( $function ) {
    return 'mysiteapp_error_handler';
}

/**
 * Helper function for posting from a mobile app
 */
function mysiteapp_post_new() {
    global $msap;
    global $temp_ID, $post_ID, $form_action, $post, $user_ID;
    if ($msap->is_app) {
        if (!$post) {
            remove_action('save_post', 'mysiteapp_post_new_process');
            $post = get_default_post_to_edit( 'post', true );
            add_action('save_post', 'mysiteapp_post_new_process');
            $post_ID = $post->ID;
        }
        $arr = array(
            'user'=>array('ID'=>$user_ID),
            'postedit'=>array()
        );

        if ( 0 == $post_ID ) {
            $form_action = 'post';
        } else {
            $form_action = 'editpost';
        }
        $arr['postedit'] = array(
            'wpnonce' => wp_create_nonce( 0 == $post_ID ? 'add-post' : 'update-post_' .  $post_ID ),
            'user_ID' => (int)$user_ID,
            'original_post_status'=>esc_attr($post->post_status),
            'action'=>esc_attr($form_action),
            'originalaction'=>esc_attr($form_action),
            'post_type'=>esc_attr($post->post_type),
            'post_author'=>esc_attr( $post->post_author ),
            'referredby'=>esc_url(stripslashes(wp_get_referer())),
            'hidden_post_status'=>'',
            'hidden_post_password'=>'',
            'hidden_post_sticky'=>'',
            'autosavenonce'=>wp_create_nonce( 'autosave'),
            'closedpostboxesnonce'=>wp_create_nonce( 'closedpostboxes'),
            'getpermalinknonce'=>wp_create_nonce( 'getpermalink'),
            'samplepermalinknonce'=>wp_create_nonce( 'samplepermalink'),
            'meta_box_order_nonce'=>wp_create_nonce( 'meta-box-order'),
            'categories'=>array(),
        );
        if ( 0 == $post_ID ) {
            $arr['postedit']['temp_ID'] = esc_attr($temp_ID);
        } else {
            $arr['postedit']['post_ID'] = esc_attr($post_ID);
        }
        mysiteapp_print_xml($arr);
        exit();
    }
}
/**
 * After post is being saved
 * @param int $post_id    The newly / updated post_id
 */
function mysiteapp_post_new_process($post_id) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
        return;

    if ( wp_is_post_revision( $post_id ) )
        return;

    global $msap;
    if ($msap->is_app) {
        $arr = array(
            'user' => array('ID' => get_current_user_id()),
            'postedit' => array(
                'success'=>true,
                'post_ID'=>$post_id,
                'is_revision' => var_export(wp_is_post_revision($post_id), true),
                'permalink' => get_permalink($post_id)
            ),
        );
        mysiteapp_print_xml($arr);
        exit();
    }
}

/**
 * Mobile logout
 */
function mysiteapp_logout() {
    global $msap;
    global $user_ID;
    if ($msap->is_app) {
        $arr = array(
            'user'=>array('ID'=>$user_ID),
            'logout'=>array('success'=> !empty($user_ID))
        );
        mysiteapp_print_xml($arr);
        exit();
    }
}

/**
 * If surfing from mobile, turn the 'more' to 3 dots.
 * @param string $more    Current more text
 */
function mysiteapp_fix_content_more($more){
    return '(...)';
}

/**
 * Cleans the author name of the comment
 * @param int $comment_ID    Comment id
 * @return string    Stripped author name
 */
function mysiteapp_comment_author($comment_ID = 0)
{
    $author = html_entity_decode($comment_ID) ;
    $stripped = strip_tags($author);
    echo $stripped;
}

/**
 * Displays comments
 */
function mysiteapp_comment_form() {
    ob_start();
    do_action('comment_form');
    $dump = ob_get_clean();
    if (preg_match_all('/name="([a-zA-Z0-9\_]+)" value="([a-zA-Z0-9\_\'&@#]+)"/', $dump, $matches)) {
        $total = count($matches[1]);
        for ($i=0; $i<$total; $i++) {
            echo "<".$matches[1][$i]."><![CDATA[".$matches[2][$i]."]]></".$matches[1][$i].">\n";
        }
    }
}

/**
 * Comment using Facebook
 */
function mysiteapp_comment_to_facebook(){
    $options = get_option('uppsite_options');
    $val = isset($_REQUEST['msa_facebook_comment_page']) ? $_REQUEST['msa_facebook_comment_page'] : NULL;
    if ($val) {
        if (isset($options['fbcomment']) && !isset($_POST['comment'])) {
            print mysiteapp_facebook_comments_page();
            exit;
        }
    }
}

/**
 * @note Used by the mobile applications to calculate the deltas after data refresh.
 * @return bool  Show only posts without grouped categories
 */
function mysiteapp_homepage_is_only_show_posts() {
    return isset($_REQUEST['onlyposts']);
}

function mysiteapp_comment_post_redirect($_location, $_comment) {
    mysiteapp_print_xml(array(
        'success' => true
    ));
    exit;
}

/** List of categories **/
add_filter('the_category','mysiteapp_list_cat');
add_filter('wp_list_categories','mysiteapp_list_cat');
/** List of tags **/
add_filter('the_tags','mysiteapp_list_tags');
/** Archive list **/
add_filter('get_archives_link','mysiteapp_list_archive');
/** Pages list **/
add_filter('wp_list_pages','mysiteapp_list_pages');
/** Links list **/
add_filter('wp_list_bookmarks','mysiteapp_list_links');
/** Tags **/
add_filter('wp_tag_cloud','mysiteapp_list_tags');
/** Next links **/
add_filter('next_posts_link','mysiteapp_navigation');
/** Comment using facebook (set the template)  **/
add_action('template_redirect','mysiteapp_comment_to_facebook', 10);
/** Fatal error handler */
add_filter('wp_die_handler','mysiteapp_call_error');
/** Getting post-new.php params **/
add_action('load-post-new.php', 'mysiteapp_post_new');
/** Actual save */
add_action('save_post', 'mysiteapp_post_new_process');
/** Logout hook */
add_action('wp_logout', 'mysiteapp_logout', 30);
/** Author of comment **/
add_action('comment_author', 'mysiteapp_comment_author');
/** Login hook for mobile **/
add_filter('authenticate', 'mysiteapp_login', 2, 3);
/** Fixing the "more..." for mobile **/
add_filter('the_content_more_link','mysiteapp_fix_content_more', 10, 1);
/** Comment redirect */
add_filter('comment_post_redirect', 'mysiteapp_comment_post_redirect', 10, 2);


/**
 * Fix Facebook's social button which corrupts the view in mobile
 * @param string $content    The content
 */
function mysiteapp_fix_content_fb_social($content){
    $fixed_content = preg_replace('/<p class=\"FacebookLikeButton\">.*?<\/p>/','',$content);
    $fixed_content = preg_replace('/<iframe id=\"basic_facebook_social_plugins_likebutton\" .*?<\/iframe>/','',$fixed_content);
    return $fixed_content;
}

/** Content filter - fix facebook social **/
add_filter('the_content','mysiteapp_fix_content_fb_social',20,1);
