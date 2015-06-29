<?php
$title = __( 'Manage UGC Posts', 'frontend-uploader' );
//set_current_screen( 'post' );
if ( ! current_user_can( 'publish_posts' ) )
	wp_die( __( 'You do not have permission to publish posts.', 'frontend-uploader' ) );

$wp_post_list_table = new FU_WP_Posts_List_Table();
$pagenum = $wp_post_list_table->get_pagenum();
$doaction = $wp_post_list_table->current_action();
$wp_post_list_table->prepare_items();
?>

<div class="wrap">
<?php screen_icon(); ?>
<h2><?php echo esc_html( $title ); ?><?php
if ( isset( $_REQUEST['s'] ) && $_REQUEST['s'] )
	printf( '<span class="subtitle">' . __( 'Search results for &#8220;%s&#8221;', 'frontend-uploader' ) . '</span>', get_search_query() ); ?>
</h2>

<?php
$message = '';
if ( isset( $_GET['posted'] ) && (int) $_GET['posted'] ) {
	$message = __( 'Post updated.', 'frontend-uploader' );
	$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'posted' ), $_SERVER['REQUEST_URI'] );
}

if ( isset( $_GET['attached'] ) && (int) $_GET['attached'] ) {
	$attached = (int) $_GET['attached'];
	$message = sprintf( _n( 'Reattached %d attachment.', 'Reattached %d attachments.', $attached ), $attached );
	$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'attached' ), $_SERVER['REQUEST_URI'] );
}

if ( isset( $_GET['deleted'] ) && (int) $_GET['deleted'] ) {
	$message = sprintf( _n( 'Post permanently deleted.', '%d Posts permanently deleted.', $_GET['deleted'] ), number_format_i18n( $_GET['deleted'] ) );
	$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'deleted' ), $_SERVER['REQUEST_URI'] );
}

if ( isset( $_GET['trashed'] ) && (int) $_GET['trashed'] ) {
	$message = sprintf( _n( 'Post moved to the trash.', '%d Posts moved to the trash.', $_GET['trashed'] ), number_format_i18n( $_GET['trashed'] ) );
	$message .= ' <a href="' . esc_url( wp_nonce_url( 'upload.php?doaction=undo&action=untrash&ids='.( isset( $_GET['ids'] ) ? $_GET['ids'] : '' ), "bulk-media" ) ) . '">' . __( 'Undo', 'frontend-uploader' ) . '</a>';
	$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'trashed' ), $_SERVER['REQUEST_URI'] );
}

if ( isset( $_GET['untrashed'] ) && (int) $_GET['untrashed'] ) {
	$message = sprintf( _n( 'Post restored from the trash.', '%d Posts restored from the trash.', $_GET['untrashed'] ), number_format_i18n( $_GET['untrashed'] ) );
	$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'untrashed' ), $_SERVER['REQUEST_URI'] );
}

if ( isset( $_GET['approved'] ) ) {
	$message = 'The post was approved';
}

$messages[1] = __( 'Post updated.', 'frontend-uploader' );
$messages[2] = __( 'Media permanently deleted.', 'frontend-uploader' );
$messages[3] = __( 'Error saving Post.', 'frontend-uploader' );
$messages[4] = __( 'Media moved to the trash.', 'frontend-uploader' ) . ' <a href="' . esc_url( wp_nonce_url( 'upload.php?doaction=undo&action=untrash&ids='.( isset( $_GET['ids'] ) ? $_GET['ids'] : '' ), "bulk-media" ) ) . '">' . __( 'Undo', 'frontend-uploader' ) . '</a>';
$messages[5] = __( 'Media restored from the trash.', 'frontend-uploader' );

if ( isset( $_GET['message'] ) && (int) $_GET['message'] ) {
	$message = $messages[$_GET['message']];
	$_SERVER['REQUEST_URI'] = remove_query_arg( array( 'message' ), $_SERVER['REQUEST_URI'] );
}

if ( !empty( $message ) ) { ?>
<div id="message" class="updated"><p><?php echo $message; ?></p></div>
<?php } ?>

<?php $wp_post_list_table->views(); ?>

<form id="posts-filter" action="" method="get">

<?php $wp_post_list_table->search_box( __( 'Search Posts', 'frontend-uploader' ), 'posts' ); ?>

<?php $wp_post_list_table->display(); ?>

<div id="ajax-response"></div>
<?php find_posts_div(); ?>
<br class="clear" />

</form>
</div>
