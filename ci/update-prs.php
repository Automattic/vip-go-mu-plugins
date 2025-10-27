<?php
// phpcs:disable

require "vendor/autoload.php";

$options = getopt( null, [
    "verify-commit-hash", // Use --verify-commit-hash=false in order to skip hash validation. This is useful when testing the integration
    "debug", // Show debug information
    'force', // Force script through validations
    'merge-pr:', // Merge PR number
    'github-project-username:', // Github project username
    'github-project-reponame:', // Github project reponame
    'github-token:', // Github token
] );

$force = array_key_exists( 'force', $options );

function is_env_set() {
    return isset(
        $_SERVER['PROJECT_USERNAME'],
        $_SERVER['PROJECT_REPONAME'],
        $_SERVER['BRANCH'],
    );
}

if ( ! is_env_set() && ! $force ) {
    echo "The following environment variables need to be set:
    \tPROJECT_USERNAME
    \tPROJECT_REPONAME
    \tBRANCH\n";
    exit( 1 );
}

define( 'PROJECT_USERNAME', $_SERVER[ 'PROJECT_USERNAME' ] ?? $options[ 'github-project-username' ] ?? '');
define( 'PROJECT_REPONAME', $_SERVER[ 'PROJECT_REPONAME' ] ?? $options[ 'github-project-reponame' ] ?? '');
define( 'BRANCH', $_SERVER[ 'BRANCH' ] );
define( 'MERGE_PR', $options[ 'merge-pr' ] ?? false );
define( 'GITHUB_TOKEN', $_SERVER[ 'GITHUB_TOKEN' ] ?? $options[ 'github-token' ] ?? '' );
define( 'GITHUB_ENDPOINT', 'https://api.github.com/repos/' . PROJECT_USERNAME . '/' . PROJECT_REPONAME );
define( 'VERIFY_COMMIT_HASH', $options[ 'verify-commit-hash' ] ?? true );
define( 'DEBUG', array_key_exists( 'debug', $options ) );
define( 'FORCE', $force );
define( 'LABEL_NO_FILES_TO_DEPLOY', '[Status] No files to Deploy' );
define( 'LABEL_READY', '[Status] Ready to deploy' );
define( 'LABEL_DEPLOYED_PROD', '[Status] Deployed to production' );
define( 'LABEL_DEPLOYED_STAGING', '[Status] Deployed to staging' );
define( 'LABEL_REVERTED', '[Status] Reverted' );

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
 * @return mixed $merged_pr The PR object
 */
function fetch_pr_merged_to_branch() {
    $missing_label = BRANCH === 'production' ? LABEL_DEPLOYED_PROD : LABEL_DEPLOYED_STAGING;

    $prs = curl_get( GITHUB_ENDPOINT . '/pulls?sort=created&direction=desc&state=closed&base=' . BRANCH );
    echo "Fetching merged PRs - found " . sizeof( $prs ) . " candidates\n";
    foreach( $prs as $pr ) {
        echo "Checking PR #{$pr['number']} for the merged PR...\n";
        if ( ! $pr['merged_at'] ?? '' ) {
            echo "PR #{$pr['number']} is not merged, skipping.\n";
            continue;
        }

        $labels = array_map( fn ($label) => $label['name'], $pr['labels'] );
        if ( ! in_array( $missing_label, $labels ) ) {
            echo "PR #{$pr['number']} - {$pr['title']} {$pr['html_url']} is it!\n";
            return $pr;
        } else {
            echo "PR #{$pr['number']} is not missing the '{$missing_label}' label, skipping.\n";
        }
    }

    return false;
}

/**
 * Update the labels of the PRs being deployed
 *
 * @param array $prs Array of PRs
 * @return void
 */
function update_prs_with_labels( $prs ) {
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
        if ( GITHUB_TOKEN ) {
            array_push( $headers, 'Authorization:token ' . GITHUB_TOKEN );
        }
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

    if ( ! isset($pr['number'] ) ) {
        debug( "\n maybe_remove_label_from_pr(): No number property found for pr" );
        debug( $pr );
        return;
    }

    $ch = curl_init( GITHUB_ENDPOINT . '/issues/' . $pr['number'] . '/labels/' . rawurlencode( $label_to_remove ) );
    $headers = ['User-Agent: script'];

    curl_setopt( $ch, CURLOPT_HEADER, 0 );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'DELETE' );

    if ( GITHUB_TOKEN ) {
        array_push( $headers, 'Authorization:token ' . GITHUB_TOKEN );
    }

    curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
    curl_exec( $ch );
}

/**
 * Get the PR ids based off of the commits of a PR.
 *
 * @param string $commit_url The URL of the PR's commits
 * @return array $pr_ids The IDs pulled from the commits
 */
function get_pr_ids_from_commits( $commit_url ) {
    $commits = curl_get_all( $commit_url );
    $pr_ids = [];

    foreach( $commits as $commit ) {
        $msg = $commit['commit']['message'];

        echo "Checking commit: {$commit['sha']}\n";
        if ( 1 === preg_match( '/\(\#[0-9]+\)/', $msg, $matches ) || 1 === preg_match( '/^Merge pull request #[0-9]+/', $msg, $matches ) ) {
            $id = preg_replace('/[^0-9]/', '', $matches[0] );
            echo "Found PR ID: $id\n";
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
    echo "Getting $url\n";
    $ch = curl_init( $url );
    $headers = ['User-Agent: script'];

    curl_setopt( $ch, CURLOPT_HEADER, 0 );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    // curl_setopt( $ch, CURLOPT_VERBOSE, true );
    if ( defined( 'GITHUB_TOKEN' ) && GITHUB_TOKEN ) {
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
 * Wrapper which paginates the GitHub request to fetch everything. Useful when
 * the endpoint doesn't include pagination information.
 *
 * @param string $url URL to get
 * @return array $data The array with all the data
 */
function curl_get_all( $url ) {
	$all_data = [];
    $pagination_url = $url . '?per_page=50&page=';
	for ( $page = 1; ! empty( $data = curl_get( $pagination_url . $page ) ); $page++ ) {
		$all_data = array_merge( $all_data, $data );
	}
	return $all_data;
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
        $skip_labels = [ LABEL_NO_FILES_TO_DEPLOY, LABEL_DEPLOYED_PROD, LABEL_REVERTED ];
        if ( BRANCH === 'staging' ) {
            $skip_labels[] = LABEL_DEPLOYED_STAGING;
        }
        foreach( $skip_labels as $skip_label ) {
            if ( in_array( $skip_label, $label_names ) ) {
                echo "Skipping PR {$pr['number']} because it has label $skip_label\n";
                // If file was already marked as deployed or no files to deploy, skip
                if ( ! FORCE ) {
                    continue 2;
                }
                echo "Not skipping because of force flag\n";
            }
        }

        $prs[] = $pr;
    }

    return $prs;
}

/**
 * This checks for merged PRs and updates PRs.
 *
 * @return void
 */
function update_prs() {
    $merged_pr = null;

    if ( MERGE_PR ) {
        $merged_pr = curl_get( GITHUB_ENDPOINT . '/pulls/' . MERGE_PR );
    } else {
        $merged_pr = fetch_pr_merged_to_branch();
    }

    if ( ! $merged_pr || ! isset( $merged_pr['_links']['commits']['href'] ) ) {
        echo "No merged PR found, skipping PR label updates.";
        exit;
    }
    echo "Found Merge PR: " . $merged_pr['html_url'] . "\n";

    $pr_ids = get_pr_ids_from_commits( $merged_pr['_links']['commits']['href'] );

    if ( empty( $pr_ids ) ) {
        echo "No PRs found, skipping PR label updates.";
        exit;
    }

    $prs = process_pr_ids( $pr_ids );

    $prs[] = $merged_pr;
    update_prs_with_labels( $prs );
}

update_prs();
