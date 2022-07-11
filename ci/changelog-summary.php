<?php
// phpcs:disable

require "vendor/autoload.php";

function is_env_set() {
    return isset(
        $_SERVER['PROJECT_USERNAME'],
        $_SERVER['PROJECT_REPONAME'],
        $_SERVER['CHANGELOG_POST_TOKEN'],
        $_SERVER['BRANCH'],
    );
}

if ( ! is_env_set() ) {
    echo "The following environment variables need to be set:
    \tPROJECT_USERNAME
    \tPROJECT_REPONAME
    \tCHANGELOG_POST_TOKEN
    \tBRANCH\n";
    exit( 1 );
}

$options = getopt( null, [
    "link-to-pr", // Add link to the PR at the button of the changelog entry
    "start-marker:", // Text below line matching this param will be considered changelog entry
    "end-marker:", // Text until this line will be considered changelog entry
    "wp-endpoint:", // Endpoint to wordpress site to create posts for
    "wp-status:", // Status to create changelog post with. Common scenarios are 'draft' or 'published'
    "wp-tag-ids:", // Default tag IDs to add to the changelog post
    "verify-commit-hash", // Use --verify-commit-hash=false in order to skip hash validation. This is useful when testing the integration
    "debug", // Show debug information
] );

if ( ! isset( $options[ "wp-endpoint" ] ) ) {
    echo "Argument --wp-endpoint is mandatory.\n";
    exit( 1 );
}

define( 'PROJECT_USERNAME', $_SERVER[ 'PROJECT_USERNAME' ] );
define( 'PROJECT_REPONAME', $_SERVER[ 'PROJECT_REPONAME' ] );
define( 'BRANCH', $_SERVER[ 'BRANCH' ] );
define( 'CHANGELOG_POST_TOKEN', $_SERVER[ 'CHANGELOG_POST_TOKEN' ] );
define( 'GITHUB_TOKEN', $_SERVER[ 'GITHUB_TOKEN' ] ?? '' );
define( 'GITHUB_ENDPOINT', 'https://api.github.com/repos/' . PROJECT_USERNAME . '/' . PROJECT_REPONAME );
define( 'PR_CHANGELOG_START_MARKER', '<h2>Changelog Description' );
define( 'PR_CHANGELOG_END_MARKER', '<h2>' );
define( 'WP_CHANGELOG_ENDPOINT', $options[ 'wp-endpoint' ] );
define( 'WP_CHANGELOG_STATUS', $options[ 'wp-status' ] ?? 'draft' );
define( 'WP_CHANGELOG_TAG_ID', '1784989' );
define( 'LINK_TO_PR', $options[ 'link-to-pr' ] ?? true );
define( 'VERIFY_COMMIT_HASH', $options[ 'verify-commit-hash' ] ?? true );
define( 'DEBUG', array_key_exists( 'debug', $options ) );
define( 'LABEL_NO_FILES_TO_DEPLOY', '[Status] No files to Deploy' );
define( 'LABEL_READY', '[Status] Ready to deploy' );
define( 'LABEL_DEPLOYED_PROD', '[Status] Deployed to production' );
define( 'LABEL_DEPLOYED_STAGING', '[Status] Deployed to staging' );

/**
 * Utility function for debugging.
 *
 * @param mixed $arg Whatever needs to be outputted for debugging purposes
 */
function debug( $arg ) {
    if ( ! DEBUG ) {
        return;
    }

    echo "DEBUG: " . print_r( $arg, true );
}

/**
 * Get the latest PR merged to branch.
 *
 * @return int $merged_pr The PR object
 */
function fetch_pr_merged_to_branch() {
    $merged_pr = false;
    $missing_label = BRANCH === 'production' ? LABEL_DEPLOYED_PROD : LABEL_DEPLOYED_STAGING;

    $prs = curl_get( GITHUB_ENDPOINT . '/pulls?sort=created&direction=desc&state=closed&base=' . BRANCH );
    foreach( $prs as $pr ) {
        if ( ! $pr['merged_at'] ?? '' ) {
            // PR is closed but not merged, skip to next one.
            continue;
        }

        $labels = array_map( fn ($label) => $label['name'], $pr['labels'] );
        if ( ! in_array( $missing_label, $labels ) ) {
            // Look for PR with missing label.
            $merged_pr = $pr;
        }
    }

    return $merged_pr;
}

/**
 * Update the labels of the PRs being deployed
 *
 * @param array $prs Array of PRs
 * @return void
 */
function update_prs( $prs ) {
    foreach( $prs as $pr ) {
        maybe_remove_label_from_pr( $pr );

        // Tack new labels onto each PR
        $ch = curl_init( GITHUB_ENDPOINT . "/issues/" . $pr['number'] . '/labels' );
        $headers = ['User-Agent: script'];
        $deploy_label = BRANCH === 'production' ? LABEL_DEPLOYED_PROD : LABEL_DEPLOYED_STAGING;
        $body = '{"labels":["' . $deploy_label . '"]}';
        curl_setopt( $ch, CURLOPT_HEADER, 0 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
        // if ( isset($_SERVER['GITHUB_TOKEN'] ) ) {
        //     array_push( $headers, 'Authorization:token ' . GITHUB_TOKEN );
        // }
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
        $data = curl_exec( $ch );

        debug( $data );
    }
}

/**
 * If old label exists, remove it.
 *
 * @param object $pr PR
 * @return void
 */
function maybe_remove_label_from_pr( $pr ) {
    $label_to_remove = BRANCH === 'production' ? LABEL_DEPLOYED_STAGING : LABEL_READY;

    $ch = curl_init( GITHUB_ENDPOINT . "/issues/" . $pr['number'] . '/labels' . rawurlencode( $label_to_remove ) );
    $headers = ['User-Agent: script'];

    curl_setopt( $ch, CURLOPT_HEADER, 0 );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_CUSTOM_REQUEST, 'DELETE' );

    if ( isset( $_SERVER['GITHUB_TOKEN'] ) ) {
        array_push( $headers, 'Authorization:token ' . GITHUB_TOKEN );
    }

    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
    $data = curl_exec( $ch );
}

/**
 * Grab the changelog section from the PR's "Changelog Description".
 *
 * @param string $description Description of PR
 * @return string $result Changelog section from PR
 */
function get_changelog_section_in_description_html( $description ) {
    $found_changelog_header = false;
    $result = '';
    foreach( preg_split("/\n/", $description ) as $line ) {
        if ( strpos( $line, PR_CHANGELOG_START_MARKER ) === 0 ) {
            $found_changelog_header = true;
        } else if ( $found_changelog_header ) {
            if ( strpos( $line, PR_CHANGELOG_END_MARKER ) === 0 ) {
                // We have hit next section
                break;
            }
            $result = $result . "\n" . $line;
        }
    }
    return $result;
}

/**
 * Generate HTML from PR for changelog post body.
 *
 * @param object $pr PR
 * @return string $changelog_html Generated HTML from changelog for post body.
 */
function get_changelog_html( $pr ) {
    $Parsedown = new Parsedown();
    $body = preg_replace( '/<!--(.|\s)*?-->/', '', $pr['body'] );
    $description_html =  $Parsedown->text( $body );

    $changelog_html = get_changelog_section_in_description_html( $description_html );

    if ( empty( $changelog_html ) ) {
        return NULL;
    }

    if ( LINK_TO_PR && strpos($changelog_html, $pr['html_url']) === false ) {
        $changelog_html = $changelog_html . "\n\n" . $Parsedown->text( $pr['html_url'] );
    }
    return $changelog_html;
}

/**
 * P2 changelog post.
 *
 * @param string $title Title of changelog post
 * @param string $content Body of changelog post
 * @param array $tags Changelog post tags
 */
function create_changelog_post( $title, $content, $tags ) {
    if ( BRANCH === 'production' ) {
        $channel_id = 5905;
    } else {
        $channel_id = 267076;
    }

    $fields = [
        'title'           => $title,
        'content'         => $content,
        'excerpt'         => $title,
        'status'          => WP_CHANGELOG_STATUS,
        'tags'            => implode( ',', $tags ),
        'release-channel' => $channel_id,
    ];

    debug( $fields );

    $ch = curl_init( WP_CHANGELOG_ENDPOINT );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_POST, true );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, [ 'Authorization:Bearer ' . CHANGELOG_POST_TOKEN ] );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $fields );
    $response = curl_exec( $ch );
    $http_code = curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
    curl_close( $ch );

    echo "Response:\n";
    echo $response;
    echo "\nHttpCode: $http_code";

    if ( $http_code >= 400 ) {
        echo "\n\nFailed to create changelog draft post\n";
        exit( 1 );
    }
}

/**
 * Get changelog tags from a PR.
 *
 * @param object $pr PR
 * @return array $tags Tags from PR (if there are any)
 */
function get_changelog_tags( $pr ) {
    $tags = [];

    foreach ( $pr['labels'] as $label ) {
        preg_match( '/ChangelogTagID:\s*(\d+)/', $label['description'], $matches );
        if ( $matches ) {
            $tags[] = $matches[1];
        }
    }

    return $tags;
}

/**
 * Get the PR ids based off of the commits of a PR.
 *
 * @param string $commit_url The URL of the PR's commits
 * @return array $pr_ids The IDs pulled from the commits
 */
function get_pr_ids_from_commits( $commit_url ) {
    $commits = curl_get( $commit_url );
    $pr_ids = [];

    foreach( $commits as $commit ) {
        $msg = $commit['commit']['message'];

        if ( 1 === preg_match( '/\(\#[0-9]*\)/', $msg, $matches ) ) {
            $id = preg_replace('/[^0-9]/', '', $matches[0] );
            $pr_ids[] = $id;
        }
    }

    return $pr_ids;
}

/**
 * Wrapper for cURL GET request.
 *
 * @param string $url URL to GET
 * @return mixed $data Decoded JSON response
 */
function curl_get( $url ) {
    $ch = curl_init( $url );
    $headers = ['User-Agent: script'];

    curl_setopt( $ch, CURLOPT_HEADER, 0 );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_VERBOSE, true );

    if ( isset( $_SERVER['GITHUB_TOKEN'] ) ) {
        array_push( $headers, 'Authorization:token ' . GITHUB_TOKEN );
    }

    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
    $data = curl_exec( $ch );
    $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ( $response_code !== 200 ) {
        echo "\nInvalid response code received: $response_code";
        exit();
    }
    curl_close( $ch );

    return json_decode( $data, true );
}

/**
 * Process an array of PR ids and return an array of PR objects
 *
 * @param array $pr_ids Array of PR ids
 * @return array $prs Array of PR objects
 */
function process_pr_ids( $pr_ids ) {
    $prs = [];
    foreach( $pr_ids as $pr_id ) {
        $pr = curl_get( GITHUB_ENDPOINT . '/pulls/' . $pr_id );

        $label_names = array_map( fn($label) => $label['name'], $pr['labels'] );

        if ( in_array( LABEL_NO_FILES_TO_DEPLOY, $label_names ) ) {
            // If no files to deploy, skip
            continue;
        }

        $prs[] = $pr;
    }

    return $prs;
}

/**
 * This creates the changelog summary and updates the PRs afterwards with the appropriate labels.
 *
 * @return void
 */
function build_changelog_and_update_prs() {
    $merged_pr = fetch_pr_merged_to_branch();

    if ( ! $pr || ! isset( $merged_pr['_links']['commits']['href'] ) ) {
       exit;
    }

    $pr_ids = get_pr_ids_from_commits( $merged_pr['_links']['commits']['href'] );

    if ( empty( $pr_ids ) ) {
        exit;
    }

    $prs = process_pr_ids( $pr_ids );

    $tags = [ WP_CHANGELOG_TAG_ID ];
    $changelog_entries = [];
    foreach( $prs as $pr ) {
        $tags = array_merge( get_changelog_tags( $pr ), $tags );
        $changelog_html = get_changelog_html( $pr );
        if ( ! empty( $changelog_html ) ) {
            $changelog_entries[] = $changelog_html;
        }
    }
    array_unique( $tags ); // Dedupe tags

    if ( empty( $changelog_entries ) ) {
        echo "Skipping post. No changelog text found in any of the prs.\n";
        exit( 0 );
    }

    $title = ucfirst( BRANCH ) . ' release - ' . date( 'Y/m/d' );
    $content = join( "\n<hr />\n", $changelog_entries );
    create_changelog_post( $title, $content, $tags, BRANCH );

    update_prs( array_merge( $prs, $merged_pr ), BRANCH );
}

build_changelog_and_update_prs();
