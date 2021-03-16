#!/usr/bin/env bash

# Utils script to unify code used by other scripts

install_db() {
	local DB_NAME=$1
	local DB_USER=$2
	local DB_PASS=$3
	local DB_HOST=${4-localhost}
	# parse DB_HOST for port or socket references
	local PARTS=(${DB_HOST//\:/ })
	local DB_HOSTNAME=${PARTS[0]};
	local DB_SOCK_OR_PORT=${PARTS[1]};
	local EXTRA=""

	if ! [ -z $DB_HOSTNAME ] ; then
		if [ $(echo $DB_SOCK_OR_PORT | grep -e '^[0-9]\{1,\}$') ]; then
			EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
		elif ! [ -z $DB_SOCK_OR_PORT ] ; then
			EXTRA=" --socket=$DB_SOCK_OR_PORT"
		elif ! [ -z $DB_HOSTNAME ] ; then
			EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
		fi
	fi

	# create database
	mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA
}

exclude_core_tests() {
	local FILE=$1
	local PREFIX=$2 || ""
	local TO_EXCLUDE=(
		"tests/admin/includesPlugin.php"
		"tests/admin/includesScreen.php"
		"tests/adminbar.php"
		"tests/canonical/sitemaps.php"
		"tests/comment/wpCountComments.php"
		"tests/customize/custom-css-setting.php"
		"tests/customize/widgets.php"
		"tests/date/getFeedBuildDate.php"
		"tests/dependencies/scripts.php"
		"tests/feed/atom.php"
		"tests/feed/rss2.php"
		"tests/filesystem/base.php"
		"tests/filesystem/findFolder.php"
		"tests/import/import.php"
		"tests/import/parser.php"
		"tests/import/postmeta.php"
		"tests/mail.php"
		"tests/multisite/getSpaceAllowed.php"
		"tests/multisite/site.php"
		"tests/option/updateOption.php"
		"tests/post/getLastPostModified.php"
		"tests/privacy/wpPrivacySendErasureFulfillmentNotification.php"
		"tests/privacy/wpPrivacySendPersonalDataExportEmail.php"
		"tests/privacy/wpPrivacySendRequestConfirmationNotification.php"
		"tests/query/isTerm.php"
		"tests/query/search.php"
		"tests/query/vars.php"
		"tests/rest-api/rest-attachments-controller.php"
		"tests/rest-api/rest-posts-controller.php"
		"tests/rest-api/rest-schema-setup.php"
		"tests/rest-api/rest-users-controller.php"
		"tests/sitemaps/functions.php"
		"tests/sitemaps/sitemaps.php"
		"tests/taxonomy.php"
		"tests/term.php"
		"tests/term/cache.php"
		"tests/term/getTerm.php"
		"tests/term/getTerms.php"
		"tests/term/getTheTerms.php"
		"tests/term/query.php"
		"tests/term/splitSharedTerm.php"
		"tests/term/termCounts.php"
		"tests/term/wpDeleteTerm.php"
		"tests/term/wpGetObjectTerms.php"
		"tests/user/capabilities.php"
		"tests/user/wpSendUserRequest.php"
		"tests/xmlrpc/basic.php"
		"tests/xmlrpc/wp/getUser.php"
		"tests/basic.php"
	);

	for testFile in "${TO_EXCLUDE[@]}"; do
		sed -i "/<testsuite name=\"default\">/a <exclude>${PREFIX}${testFile}</exclude>" "$FILE"
	done
}

update_core_tests() {
	local wp_tests_dir="$1"

	if [ -z "$wp_tests_dir" ]; then
		echo "WP_TESTS_DIR was not passed in";
		exit 1;
	fi


	local header_handling_locations=(
		"$wp_tests_dir/tests/xmlrpc/wp/newComment.php:Tests_XMLRPC_wp_newComment"
		"$wp_tests_dir/tests/xmlrpc/mt/getRecentPostTitles.php:Tests_XMLRPC_mt_getRecentPostTitles"
		"$wp_tests_dir/tests/xmlrpc/wp/restoreRevision.php:Tests_XMLRPC_wp_restoreRevision"
		"$wp_tests_dir/tests/xmlrpc/wp/newTerm.php:Tests_XMLRPC_wp_newTerm"
		"$wp_tests_dir/tests/xmlrpc/wp/newPost.php:Tests_XMLRPC_wp_newPost"
		"$wp_tests_dir/tests/xmlrpc/wp/getUsers.php:Tests_XMLRPC_wp_getUsers"
		"$wp_tests_dir/tests/xmlrpc/wp/getTerms.php:Tests_XMLRPC_wp_getTerms"
		"$wp_tests_dir/tests/xmlrpc/wp/getTerm.php:Tests_XMLRPC_wp_getTerm"
		"$wp_tests_dir/tests/xmlrpc/wp/getTaxonomy.php:Tests_XMLRPC_wp_getTaxonomy"
		"$wp_tests_dir/tests/xmlrpc/wp/getTaxonomies.php:Tests_XMLRPC_wp_getTaxonomies"
		"$wp_tests_dir/tests/xmlrpc/wp/getRevisions.php:Tests_XMLRPC_wp_getRevisions"
		"$wp_tests_dir/tests/xmlrpc/wp/getProfile.php:Tests_XMLRPC_wp_getProfile"
		"$wp_tests_dir/tests/xmlrpc/wp/getPosts.php:Tests_XMLRPC_wp_getPosts"
		"$wp_tests_dir/tests/xmlrpc/wp/getPostTypes.php:Tests_XMLRPC_wp_getPostTypes"
		"$wp_tests_dir/tests/xmlrpc/wp/getPostType.php:Tests_XMLRPC_wp_getPostType"
		"$wp_tests_dir/tests/xmlrpc/wp/getPost.php:Tests_XMLRPC_wp_getPost"
		"$wp_tests_dir/tests/xmlrpc/wp/getPages.php:Tests_XMLRPC_wp_getPages"
		"$wp_tests_dir/tests/xmlrpc/wp/getPageList.php:Tests_XMLRPC_wp_getPageList"
		"$wp_tests_dir/tests/xmlrpc/wp/getPage.php:Tests_XMLRPC_wp_getPage"
		"$wp_tests_dir/tests/xmlrpc/wp/getOptions.php:Tests_XMLRPC_wp_getOptions"
		"$wp_tests_dir/tests/xmlrpc/wp/getMediaItem.php:Tests_XMLRPC_wp_getMediaItem"
		"$wp_tests_dir/tests/xmlrpc/wp/getComments.php:Tests_XMLRPC_wp_getComments"
		"$wp_tests_dir/tests/xmlrpc/wp/getComment.php:Tests_XMLRPC_wp_getComment"
		"$wp_tests_dir/tests/xmlrpc/wp/editTerm.php:Tests_XMLRPC_wp_editTerm"
		"$wp_tests_dir/tests/xmlrpc/wp/editProfile.php:Tests_XMLRPC_wp_editProfile"
		"$wp_tests_dir/tests/xmlrpc/wp/editPost.php:Tests_XMLRPC_wp_editPost"
		"$wp_tests_dir/tests/xmlrpc/wp/deleteTerm.php:Tests_XMLRPC_wp_deleteTerm"
		"$wp_tests_dir/tests/xmlrpc/wp/deletePost.php:Tests_XMLRPC_wp_deletePost"
		"$wp_tests_dir/tests/xmlrpc/mw/newPost.php:Tests_XMLRPC_mw_newPost"
		"$wp_tests_dir/tests/xmlrpc/mw/getRecentPosts.php:Tests_XMLRPC_mw_getRecentPosts"
		"$wp_tests_dir/tests/xmlrpc/mw/getPost.php:Tests_XMLRPC_mw_getPost"
		"$wp_tests_dir/tests/xmlrpc/mw/editPost.php:Tests_XMLRPC_mw_editPost"
		"$wp_tests_dir/tests/xmlrpc/wp/getMediaItem.php:Tests_XMLRPC_wp_getMediaItem"
	)

	local header_handling='\/**\n * @runTestsInSeparateProcesses\n * @preserveGlobalState disabled\n *\/'

	for location in "${header_handling_locations[@]}"; do
		local location_data=(${location//:/ })
		local file="${location_data[0]}"
		local test_class_name="${location_data[1]}"

		echo "Updating ${file} - ${test_class_name} for header test"
		sed -i "s/\\(class $test_class_name\\)/${header_handling}\\n\\1/" "$file"
	done
}

export -f install_db
export -f exclude_core_tests
export -f update_core_tests
