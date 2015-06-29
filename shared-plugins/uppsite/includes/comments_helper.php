<?php
/**
 * Helper functions for comments plugins
 */

require_once( dirname(__FILE__) . '/fbcomments_page.inc.php' );

/** Facebook comments url **/
define('UPPSITE_COMMENTS_FACEBOOK_URL', 'http://graph.facebook.com/comments/?limit=%d&ids=%s');
/** Maximum of comments */
define('UPPSITE_COMMENTS_LIMIT', 25);

/**
 * Enum of supported commenting systems
 */
class UppSiteCommentSystem {
    const ORIGINAL = 0;
    const DISQUS = 1;
    const FACEBOOK = 2;
}

/**
 * Builds a transient name for specific post id's comments
 * @param $post_id  int Post ID
 * @return string   The transient name for the post's comments
 */
function uppsite_comments_transient_name($post_id) {
    return "uppsite_comments_" . $post_id;
}

/**
 * Retrieves comments from Disqus's service
 * @note    If an error in communication is encountered, we will return empty array that will be cached in the transient
 *          object for the amount of time. This is to assure we won't generate too much API traffic if there's a problem
 *          with Disqus's service.
 * @param $post_id  int The post id
 * @param $limit    int No. of comments
 * @return array    Array of comments, ordered ASC (empty array if nothing found)
 */
function uppsite_comments_disqus_get($post_id, $limit) {
    global $dsq_api;
    $identifier = dsq_identifier_for_post( get_post( $post_id ) );
    $response = $dsq_api->api->get_thread_posts(null, array(
        'thread_identifier'	=> $identifier,
        'filter' => DISQUS_STATE_APPROVED,
        'limit' => $limit
    ));
    $comments = array();
    if (is_array($response)) {
        foreach ($response as $comment) {
            $commentData = new stdClass();
            $commentData->comment_ID = $comment->id;
            $commentData->comment_post_ID = $post_id;
            $commentData->comment_date = $comment->created_at;
            $commentData->comment_date_gmt = $comment->created_at;
            $commentData->comment_content = apply_filters('pre_comment_content', $comment->message);
            $commentData->comment_approved = 1;
            $commentData->comment_author = $comment->is_anonymous ? $comment->anonymous_author->name : $comment->author->display_name;
            $commentData->comment_author_email = $comment->is_anonymous ? $comment->anonymous_author->email : $comment->author->email;
            $commentData->comment_author_url = $comment->is_anonymous ? $comment->anonymous_author->url : $comment->author->url;
            $comments[] = $commentData;
        }
        $comments = array_reverse($comments);
    }
    return $comments;
}

/**
 * Parses an facebook comment array to comment object
 * @param $post_id  int Post ID
 * @param $commentArr   array   Comment data array
 * @return stdClass Comment object
 */
function uppsite_comments_facebook_parse_array($post_id, $commentArr) {
    $commentData = new stdClass();
    $commentData->comment_ID = $commentArr['id'];
    $commentData->comment_post_ID = $post_id;
    $commentData->comment_date = $commentArr['created_time'];
    $commentData->comment_date_gmt = $commentArr['created_time'];
    $commentData->comment_content = apply_filters('pre_comment_content', $commentArr['message']);
    $commentData->comment_approved = 1;
    $commentData->comment_author = $commentArr['from']['name'];
    $commentData->comment_author_email = '';
    $commentData->comment_author_url = "http://facebook.com/" . $commentArr['from']['id'];
    return $commentData;
}

/**
 * Retrieves comments from Facebook Graph
 * @see https://developers.facebook.com/blog/post/490/
 * @param $post_id  int Post ID
 * @param $limit    int No. of comments
 * @return array    Array of comments, ordered ASC (empty array if nothing found)
 */
function uppsite_comments_facebook_get($post_id, $limit) {
    $permalink = get_permalink( $post_id );
    $comments_link = sprintf( UPPSITE_COMMENTS_FACEBOOK_URL, $limit, $permalink );
    $comment_json = wp_remote_get( $comments_link );
    $comments_arr = null;
    if (!is_wp_error($comment_json)) {
        $comments_arr = json_decode($comment_json['body'], true);
    }
    $comments = array();
    if (is_array($comments_arr) &&
        array_key_exists($permalink, $comments_arr) &&
        array_key_exists('data', $comments_arr[$permalink])) {
        $comments_list = $comments_arr[$permalink]['data'];
        foreach ($comments_list as $comment) {
            $comments[] = uppsite_comments_facebook_parse_array($post_id, $comment);
            if (array_key_exists('comments', $comment)) {
                foreach ($comment['comments']['data'] as $innerComment) {
                    $comments[] = uppsite_comments_facebook_parse_array($post_id, $innerComment);
                }
            }
        }
    }
    return $comments;
}

/**
 * Inserts a comment into Disqus
 * @param $commentData array    Array of comment data
 * @return bool Success of failure
 */
function uppsite_comments_disqus_insert($commentData) {
    global $dsq_api;
    $post = get_post( $commentData['comment_post_ID'] );
    $identifier = dsq_identifier_for_post( $post );
    $thread = $dsq_api->api->thread_by_identifier($identifier, $post->post_title);
    if (!is_object($thread) || !isset($thread->thread) || !isset($thread->thread->id)) {
        return false;
    }
    $threadId = $thread->thread->id;
    $ret = $dsq_api->api->create_post(
        $threadId,
        $commentData['comment_content'],
        $commentData['comment_author'],
        $commentData['comment_author_email'],
        array(
            'author_url' => $commentData['comment_author_url'],
            'state' => 'approved'
        )
    );
    return is_object($ret) && is_null($dsq_api->api->last_error);
}

/**
 * Returns the specific commenting system which is installed and operational in this blog
 * @return int  UppSiteCommentSystem enum
 */
function uppsite_comments_get_system() {
    global $dsq_api;
    if (function_exists('dsq_is_installed') && dsq_is_installed() && // Disqus installed
        class_exists('DisqusWordPressAPI') && $dsq_api instanceof DisqusWordPressAPI && // Disqus API available
        isset($dsq_api->api) && method_exists($dsq_api->api, 'create_post') && method_exists($dsq_api->api, 'get_thread_posts')) { // Required API functions
        return UppSiteCommentSystem::DISQUS;
    }
    if (false) {
        // @TODO Facebook implementation not full yet
        return UppSiteCommentSystem::FACEBOOK;
    }
    return UppSiteCommentSystem::ORIGINAL;
}

/**
 * Returns an array of comments (ordered ASC) and handles saving them in transient if needed
 * @param $post_id  int The post id
 * @param $limit    int Number of comments
 * @return array    Array of comments
 */
function uppsite_comments_get($post_id, $limit = UPPSITE_COMMENTS_LIMIT) {
    global $comments;
    $system = uppsite_comments_get_system();
    if ($system != UppSiteCommentSystem::ORIGINAL) {
        $transientName = uppsite_comments_transient_name( $post_id );
        if ( false === ( $comments = get_transient( $transientName ) ) ) {
            switch ($system) {
                case UppSiteCommentSystem::DISQUS:
                    $comments = uppsite_comments_disqus_get( $post_id, $limit );
                    break;
                case UppSiteCommentSystem::FACEBOOK:
                    $comments = uppsite_comments_facebook_get( $post_id, $limit );
                    break;
            }
            set_transient( $transientName, $comments, 10 * MINUTE_IN_SECONDS );
        }
    }
    return $comments; // The original comments will return in the other cases.
}

/**
 * Hook for comment posting page, so the mobile applications could "post comment" as usual, and the magic will happen
 * here.
 * @param $comment_post_ID
 * @return mixed
 */
function uppsite_comments_pre($comment_post_ID) {
    global $msap;
    if (!$msap->has_custom_theme()) { return $comment_post_ID; }

    if ( ( $system = uppsite_comments_get_system() ) == UppSiteCommentSystem::ORIGINAL ) {
        // Do nothing, let the original code to run
        return $comment_post_ID;
    }

    // The hook is early, we need to build the comment data ourselves.
    $commentData = array(
        'comment_author' => ( isset($_POST['author']) )  ? trim(strip_tags($_POST['author'])) : null,
        'comment_content' => ( isset($_POST['comment']) ) ? trim($_POST['comment']) : null,
        'comment_author_url' => ( isset($_POST['url']) )     ? trim($_POST['url']) : null,
        'comment_author_email' => ( isset($_POST['email']) )   ? trim($_POST['email']) : null,
        'comment_post_ID' => $comment_post_ID
    );

    $comment = null;
    switch ($system) {
        case UppSiteCommentSystem::DISQUS:
            $comment = uppsite_comments_disqus_insert($commentData);
            break;
        case UppSiteCommentSystem::FACEBOOK:
            // @TODO No Implementation YET!
            break;
    }

    if ($comment) {
        // Make the comments refresh next time someone will fetch them
        delete_transient( uppsite_comments_transient_name( $comment_post_ID ) );
        // Let the filters run what they want (UppSite's functions will output the right answer)
        apply_filters('comment_post_redirect', null, $comment);
    }
    wp_die(); // Error occured
}

// Hook comment posting
add_action('pre_comment_on_post', 'uppsite_comments_pre', 1); // Run first! (Disqus run at default priority)