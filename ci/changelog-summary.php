<?php
// phpcs:disable

require "vendor/autoload.php";

function is_env_set() {
    return isset(
        $_SERVER[ 'PROJECT_USERNAME' ],
        $_SERVER[ 'PROJECT_REPONAME' ],
        $_SERVER[ 'CHANGELOG_POST_TOKEN'],
    );
}

if ( ! is_env_set() ) {
    echo "The following environment variables need to be set:
    \tPROJECT_USERNAME
    \tPROJECT_REPONAME
    \tCHANGELOG_POST_TOKEN\n";
    exit( 1 );
}

$options = getopt( null, [
    "link-to-pr", // Add link to the PR at the button of the changelog entry
    "start-marker:", // Text bellow line matching this param will be considered changelog entry
    "end-marker:", // Text untill this line will be considered changelog entry
    "wp-endpoint:", // Endpoint to wordpress site to create posts for
    "wp-status:", // Status to create changelog post with. Common scenarios are 'draft' or 'published'
    "wp-tag-ids:", // Default tag IDs to add to the changelog post
    "wp-channel-ids:", // Channel IDs to add to the changelog post
    "verify-commit-hash", // Use --verify-commit-hash=false in order to skip hash validation. This is usefull when testing the integration
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
define( 'PR_CHANGELOG_START_MARKER', $options[ 'start-marker' ] ?? '<h2>Changelog Description' );
define( 'PR_CHANGELOG_END_MARKER', $options[ 'end-marker' ] ?? '<h2>' );
define( 'WP_CHANGELOG_ENDPOINT', $options[ 'wp-endpoint' ] );
define( 'WP_CHANGELOG_STATUS', $options[ 'wp-status' ] ?? 'draft' );
define( 'WP_CHANGELOG_TAG_IDS', $options[ 'wp-tag-ids' ] );
define( 'WP_CHANGELOG_CHANNEL_IDS', $options[ 'wp-channel-ids' ] );
define( 'LINK_TO_PR', $options[ 'link-to-pr' ] ?? true );
define( 'VERIFY_COMMIT_HASH', $options[ 'verify-commit-hash' ] ?? true );
define( 'DEBUG', array_key_exists( 'debug', $options ) );

define( 'TAG_NO_FILES_TO_DEPLOY', '[Status] No files to Deploy' );
define( 'TAG_DEPLOYED', '[Status] Deployed to ' . BRANCH );
define( 'TAG_DEPLOYED_STAGING', '[Status] Deployed to staging' );
define( 'MAX_PAGE', 10 );

function debug( $arg ) {
    if ( ! DEBUG ) {
        return;
    }

    echo "DEBUG: " . print_r( $arg, true );
}

function fetch_PRs() {
    $found_deployed_tag = false;
    $filtered_prs = [];

    for ($page = 1; $page <= MAX_PAGE && !$found_deployed_tag; $page++) {
        debug("Fetching page " . $page . "\n");
        $ch = curl_init(GITHUB_ENDPOINT . '/pulls?sort=updated&direction=desc&state=closed&page=' . $page);
        $headers = ['User-Agent: script'];

        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);

        if (isset($_SERVER['GITHUB_TOKEN'])) {
            array_push($headers, 'Authorization:token ' . GITHUB_TOKEN);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER,  $headers);
        $data = curl_exec($ch);
        curl_close($ch);

        $prs_page = json_decode($data, true);
        foreach ($prs_page as $pr) {
            if (!$pr['merged_at'] ?? '') {
                // PR is closed not merged
                continue;
            }
            $label_names = array_map(fn ($label) => $label['name'], $pr['labels']);
            if (in_array(TAG_NO_FILES_TO_DEPLOY, $label_names)) {
                // If there were no files to deploy we need not to put them on changelog
                continue;
            }

            if (in_array(TAG_DEPLOYED, $label_names)) {
                // If we found the deployed tag we should stop searching
                $found_deployed_tag = true;
                break;
            }

            if ( BRANCH === 'production' && !in_array(TAG_DEPLOYED_STAGING, $label_names)) {
                echo('Skipping "' . $pr['title'] . '" when building prod deploy changelog, because it wasnt deployed to staging yet.');
                continue;
            }

            $filtered_prs[] = $pr;
        }
    }

    return $filtered_prs;
}

function mark_prs_deployed( $prs ) {
    foreach( $prs as $pr ) {
        debug("Updating PR labels " . $pr['title'] . "\n");
        $ch = curl_init(GITHUB_ENDPOINT . "/issues/" . $pr['number'] . '/labels');
        $headers = ['User-Agent: script'];
        $body = '{"labels":["' . TAG_DEPLOYED . '"]}';

        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        if (isset($_SERVER['GITHUB_TOKEN'])) {
            array_push($headers, 'Authorization:token ' . GITHUB_TOKEN);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER,  $headers);
        $data = curl_exec($ch);
        debug($data);
    }
}

function get_changelog_section_in_description_html( $description ) {
    $found_changelog_header = false;
    $result = '';
    foreach(preg_split("/\n/", $description) as $line){
        if ( strpos($line, PR_CHANGELOG_START_MARKER) === 0 ) {
            $found_changelog_header = true;
        } else if ( $found_changelog_header ) {

            if ( strpos($line, PR_CHANGELOG_END_MARKER) === 0 ) {
                // We have hit next section
                break;
            }
            $result = $result . "\n" . $line;
        }
    }
    return $result;
}

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

function create_changelog_post( $title, $content, $tags, $channels ) {
    $fields = [
        'title' => $title,
        'content' => $content,
        'excerpt' => $title,
        'status' => WP_CHANGELOG_STATUS,
        'tags' => implode( ',', $tags ),
    ];

    if ( $channels ) {
        $fields['release-channel'] = implode( ',', $channels );
    }

    debug( $fields );

    $ch = curl_init( WP_CHANGELOG_ENDPOINT );
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Authorization:Bearer ' . CHANGELOG_POST_TOKEN ] );
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    echo "Response:\n";
    echo $response;
    echo "\nHttpCode: $http_code";

    if ( $http_code >= 400 ) {
        echo "\n\nFailed to create changelog draft post\n";
        exit( 1 );
    }
}

function get_changelog_tags( $prs ) {
    $tags = [];
    $default_tags = explode( ",", WP_CHANGELOG_TAG_IDS );
    foreach( $default_tags as $default_tag ) {
        // We are leveraging key's being store in hash, so that we dont need to deduplicate later
        $tags[$default_tag] = true;
    }

    foreach( $prs as $pr ) {
        foreach ( $pr['labels'] as $label ) {
            preg_match('/ChangelogTagID:\s*(\d+)/', $label['description'], $matches);
            if ( $matches ) {
                $tags[ $matches[1] ] = true;
            }
        }
    }

    return array_keys( $tags );
}

function get_changelog_channels() {
    return array_filter( explode( ",", WP_CHANGELOG_CHANNEL_IDS), function( $channel ) {
        return !! $channel;
    } );
}

function create_changelog_summary() {
    $prs = fetch_PRs();

    $titles = array_map( fn($pr) => $pr['title'], $prs);
    debug( ['Following PRs found', $titles] );
    if ( ! count($prs) ) {
        echo "No PRs for changelog found.\n";
        exit;
    }

    $changelog_tags = get_changelog_tags( $prs );
    $changelog_channels = get_changelog_channels();
    $changelog_entries = [];
    foreach ( $prs as $pr) {
        $changelog_html = get_changelog_html( $pr );
        if (! empty( $changelog_html ) ) {
            $changelog_entries[] = $changelog_html;
        }
    }

    if ( empty( $changelog_entries ) ) {
        echo "Skipping post. No changelog text found in any of the prs.\n";
        exit( 0 );
    }

    $title = ucfirst( BRANCH ) . ' deploy - ' . date("Y/m/d");
    $content = join( "\n<hr />\n", $changelog_entries );

    create_changelog_post( $title, $content, $changelog_tags, $changelog_channels );
    mark_prs_deployed($prs);
}

create_changelog_summary();
