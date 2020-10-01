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
		"tests/term/wpDeleteTerm.php"
		"tests/term/query.php"
		"tests/term/getTerms.php"
		"tests/xmlrpc/basic.php"
		"tests/user/wpSendUserRequest.php"
		"tests/user/capabilities.php"
		"tests/term/wpGetObjectTerms.php"
		"tests/term/getTheTerms.php"
		"tests/term/getTerm.php"
		"tests/term/cache.php"
		"tests/term.php"
		"tests/taxonomy.php"
		"tests/sitemaps/sitemaps.php"
		"tests/sitemaps/functions.php"
		"tests/rest-api/rest-schema-setup.php"
		"tests/rest-api/rest-posts-controller.php"
		"tests/rest-api/rest-attachments-controller.php"
		"tests/query/search.php"
		"tests/query/isTerm.php"
		"tests/privacy/wpPrivacySendRequestConfirmationNotification.php"
		"tests/privacy/wpPrivacySendPersonalDataExportEmail.php"
		"tests/privacy/wpPrivacySendErasureFulfillmentNotification.php"
		"tests/post/getLastPostModified.php"
		"tests/option/updateOption.php"
		"tests/mail.php"
		"tests/import/postmeta.php"
		"tests/import/parser.php"
		"tests/import/import.php"
		"tests/filesystem/base.php"
		"tests/feed/rss2.php"
		"tests/dependencies/scripts.php"
		"tests/date/getFeedBuildDate.php"
		"tests/customize/custom-css-setting.php"
		"tests/comment/wpCountComments.php"
		"tests/canonical/sitemaps.php"
		"tests/admin/includesScreen.php"
		"tests/filesystem/base.php"
		"tests/feed/atom.php"
		"tests/adminbar.php"
		"tests/xmlrpc/wp/restoreRevision.php"
		"tests/xmlrpc/wp/newTerm.php"
		"tests/xmlrpc/wp/newPost.php"
		"tests/xmlrpc/wp/getUsers.php"
		"tests/xmlrpc/wp/getUser.php"
		"tests/xmlrpc/wp/getTerms.php"
		"tests/xmlrpc/wp/getTerm.php"
		"tests/xmlrpc/wp/getTaxonomy.php"
		"tests/xmlrpc/wp/getTaxonomies.php"
		"tests/xmlrpc/wp/getRevisions.php"
		"tests/xmlrpc/wp/getProfile.php"
		"tests/xmlrpc/wp/getPosts.php"
		"tests/xmlrpc/wp/getPostTypes.php"
		"tests/xmlrpc/wp/getPostType.php"
		"tests/xmlrpc/wp/getPost.php"
		"tests/xmlrpc/wp/getPages.php"
		"tests/xmlrpc/wp/getPageList.php"
		"tests/xmlrpc/wp/getPage.php"
		"tests/xmlrpc/wp/getOptions.php"
		"tests/xmlrpc/wp/getMediaItem.php"
		"tests/xmlrpc/wp/getComments.php"
		"tests/xmlrpc/wp/getComment.php"
		"tests/xmlrpc/wp/editTerm.php"
		"tests/xmlrpc/wp/editProfile.php"
		"tests/xmlrpc/wp/editPost.php"
		"tests/xmlrpc/wp/deleteTerm.php"
		"tests/xmlrpc/wp/deletePost.php"
		"tests/xmlrpc/mw/newPost.php"
		"tests/xmlrpc/mw/getRecentPosts.php"
		"tests/xmlrpc/mw/getPost.php"
		"tests/xmlrpc/mw/editPost.php"
		"tests/xmlrpc/mt/getRecentPostTitles.php"
		"tests/rest-api/rest-users-controller.php"
		"tests/filesystem/findFolder.php"
		"tests/admin/includesPlugin.php"
	);

	for testFile in "${TO_EXCLUDE[@]}"; do
		sed -i "/<testsuite name=\"default\">/a <exclude>${PREFIX}${testFile}</exclude>" "$FILE"
	done
}

export -f install_db
export -f exclude_core_tests