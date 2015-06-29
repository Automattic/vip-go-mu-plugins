<?php
/**
 * Media Library List Table class.
 *
 * TODO: Unhack
 */
require_once ABSPATH . '/wp-admin/includes/class-wp-list-table.php';
require_once ABSPATH . '/wp-admin/includes/class-wp-media-list-table.php';
class FU_WP_Media_List_Table extends WP_Media_List_Table {

	function __construct() {
		parent::__construct();
	}

	function prepare_items() {
		global $lost, $wpdb, $wp_query, $post_mime_types, $avail_post_mime_types;

		$q = $_REQUEST;

		if ( !empty( $lost ) )
			$q['post__in'] = implode( ',', $lost );
		add_filter( 'posts_where', array( &$this, 'modify_post_status_to_private' ) );

		list( $post_mime_types, $avail_post_mime_types ) = wp_edit_attachments_query( $q );
		$this->is_trash = isset( $_REQUEST['status'] ) && 'trash' == $_REQUEST['status'];
		$this->set_pagination_args( array(
				'total_items' => $wp_query->found_posts,
				'total_pages' => $wp_query->max_num_pages,
				'per_page' => $wp_query->query_vars['posts_per_page'],
			) );
		$this->items = $wp_query->posts;
		/* -- Register the Columns -- */
		$columns = $this->get_columns();
		$hidden = array(
			'id',
		);
		$this->_column_headers = array( $columns, $hidden, $this->get_sortable_columns() ) ;

		remove_filter( 'posts_where', array( &$this, 'modify_post_status_to_private' ) );
	}

	function modify_post_status_to_private( $where ) {
		return str_replace( "post_status = 'inherit' ", "post_status = 'private' ", $where );
	}

	function get_bulk_actions() {
		$actions = array();
		$actions['delete'] = __( 'Delete Permanently', 'frontend-uploader' );
		if ( $this->detached )
			$actions['attach'] = __( 'Attach to a post', 'frontend-uploader' );

		return $actions;
	}

	function current_action() {
		if ( isset( $_REQUEST['find_detached'] ) )
			return 'find_detached';

		if ( isset( $_REQUEST['found_post_id'] ) && isset( $_REQUEST['media'] ) )
			return 'attach';

		if ( isset( $_REQUEST['delete_all'] ) || isset( $_REQUEST['delete_all2'] ) )
			return 'delete_all';

		return parent::current_action();
	}

	function has_items() {
		return have_posts();
	}

	function no_items() {
		__( 'No media attachments found.', 'frontend-uploader' );
	}

	function get_columns() {
		$posts_columns = array();
		$posts_columns['cb'] = '<input type="checkbox" />';
		$posts_columns['icon'] = '';
		/* translators: column name */
		$posts_columns['title'] = _x( 'File', 'column name' );
		$posts_columns['author'] = __( 'Author', 'frontend-uploader'  );
		/* translators: column name */
		if ( !$this->detached ) {
			$posts_columns['parent'] = _x( 'Attached to', 'column name' );
		}
		/* translators: column name */
		$posts_columns['date'] = _x( 'Date', 'column name' );
		$posts_columns = apply_filters( 'manage_fu_media_columns', $posts_columns, $this->detached );
		return $posts_columns;
	}

	function display_rows() {
		global $post, $id;

		add_filter( 'the_title', 'esc_html' );
		$alt = '';

		while ( have_posts() ) : the_post();
		if ( $this->is_trash && $post->post_status != 'trash' || !$this->is_trash && $post->post_status == 'trash' )
			continue;

		$alt = ( 'alternate' == $alt ) ? '' : 'alternate';
		$post_owner = ( get_current_user_id() == $post->post_author ) ? 'self' : 'other' ;
		$att_title = _draft_or_post_title();
?>
	<tr id='post-<?php echo $id; ?>' class='<?php echo trim( $alt . ' author-' . $post_owner . ' status-' . $post->post_status ); ?>' valign="top">
<?php

		list( $columns, $hidden ) = $this->get_column_info();
		foreach ( $columns as $column_name => $column_display_name ) {
			$class = "class='$column_name column-$column_name'";

			$style = '';
			if ( in_array( $column_name, $hidden ) )
				$style = ' style="display:none;"';

			$attributes = $class . $style;

			switch ( $column_name ) {

			case 'cb':
?>
		<th scope="row" class="check-column"><?php if ( current_user_can( 'edit_post', $post->ID ) ) { ?><input type="checkbox" name="media[]" value="<?php the_ID(); ?>" /><?php } ?></th>
<?php
				break;

			case 'icon':
				$attributes = 'class="column-icon media-icon"' . $style;
?>
		<td <?php echo $attributes ?>><?php
				if ( $thumb = wp_get_attachment_image( $post->ID, array( 80, 60 ), true ) ) {
					if ( $this->is_trash ) {
						echo $thumb;
					} else {
?>
				<a href="<?php echo get_edit_post_link( $post->ID, true ); ?>" title="<?php echo esc_attr( sprintf( __( 'Edit "%s"', 'frontend-uploader' ), $att_title ) ); ?>">
					<?php echo $thumb; ?>
				</a>

<?php   }
				}
?>
		</td>
<?php
				break;

			case 'title':
?>
		<td <?php echo $attributes ?>><strong><?php if ( $this->is_trash ) echo $att_title; else { ?><a href="<?php echo get_edit_post_link( $post->ID, true ); ?>" title="<?php echo esc_attr( sprintf( __( 'Edit "%s"', 'frontend-uploader' ), $att_title ) ); ?>"><?php echo $att_title; ?></a><?php };  _media_states( $post ); ?></strong>
			<p>
<?php
				if ( preg_match( '/^.*?\.(\w+)$/', get_attached_file( $post->ID ), $matches ) )
					echo esc_html( strtoupper( $matches[1] ) );
				else
					echo strtoupper( str_replace( 'image/', '', get_post_mime_type() ) );
?>
			</p>
<?php
				echo $this->row_actions( $this->_get_row_actions( $post, $att_title ) );
?>
		</td>
<?php
				break;

			case 'author':
?>
		<td <?php echo $attributes ?>><?php the_author() ?></td>
<?php
				break;

			case 'tags':
?>
		<td <?php echo $attributes ?>><?php
				$tags = get_the_tags();
				if ( !empty( $tags ) ) {
					$out = array();
					foreach ( $tags as $c )
						$out[] = "<a href='edit.php?tag=$c->slug'> " . esc_html( sanitize_term_field( 'name', $c->name, $c->term_id, 'post_tag', 'display' ) ) . "</a>";
					echo join( ', ', $out );
				} else {
					__( 'No Tags', 'frontend-uploader' );
				}
?>
		</td>
<?php
				break;

			case 'desc':
?>
		<td <?php echo $attributes ?>><?php echo has_excerpt() ? $post->post_excerpt : ''; ?></td>
<?php
				break;

			case 'date':
				if ( '0000-00-00 00:00:00' == $post->post_date && 'date' == $column_name ) {
					$t_time = $h_time = __( 'Unpublished', 'frontend-uploader' );
				} else {
					$t_time = get_the_time( __( 'Y/m/d g:i:s A', 'frontend-uploader' ) );
					$m_time = $post->post_date;
					$time = get_post_time( 'G', true, $post, false );
					if ( ( abs( $t_diff = time() - $time ) ) < 86400 ) {
						if ( $t_diff < 0 )
							$h_time = sprintf( __( '%s from now', 'frontend-uploader' ), human_time_diff( $time ) );
						else
							$h_time = sprintf( __( '%s ago', 'frontend-uploader' ), human_time_diff( $time ) );
					} else {
						$h_time = mysql2date( __( 'Y/m/d', 'frontend-uploader' ), $m_time );
					}
				}
?>
		<td <?php echo $attributes ?>><?php echo $h_time ?></td>
<?php
				break;

			case 'parent':
				if ( $post->post_parent > 0 ) {
					if ( get_post( $post->post_parent ) ) {
						$title =_draft_or_post_title( $post->post_parent );
					}
?>
			<td <?php echo $attributes ?>>
				<strong><a href="<?php echo get_edit_post_link( $post->post_parent ); ?>"><?php echo $title ?></a></strong>,
				<?php echo get_the_time( __( 'Y/m/d', 'frontend-uploader' ) ); ?>
			</td>
<?php
				} else {
?>
			<td <?php echo $attributes ?>><?php __( '(Unattached)', 'frontend-uploader' ); ?><br />
			<a class="hide-if-no-js" onclick="findPosts.open( 'media[]','<?php echo $post->ID ?>' );return false;" href="#the-list"><?php _e_( 'Attach', 'frontend-uploader' ); ?></a></td>
<?php
				}
				break;



			default:
?>
		<td <?php echo $attributes ?>>
			<?php do_action( 'manage_fu_media_custom_column', $column_name, $id ); ?>
		</td>
<?php
				break;
			}
		}
?>
	</tr>
<?php endwhile;
	}

	/**
	 * [_get_row_actions description]
	 * @param  [type] $post      [description]
	 * @param  [type] $att_title [description]
	 * @return [type]            [description]
	 */
	function _get_row_actions( $post, $att_title ) {
		$actions = array();

		if ( $this->detached ) {
			if ( current_user_can( 'edit_post', $post->ID ) )
				$actions['edit'] = '<a href="' . get_edit_post_link( $post->ID, true ) . '">' . __( 'Edit', 'frontend-uploader' ) . '</a>';
			if ( current_user_can( 'delete_post', $post->ID ) )
				if ( EMPTY_TRASH_DAYS && MEDIA_TRASH ) {
					$actions['trash'] = "<a class='submitdelete' href='" . wp_nonce_url( "post.php?action=trash&amp;post=$post->ID", 'trash-attachment_' . $post->ID ) . "'>" . __( 'Trash', 'frontend-uploader' ) . "</a>";
				} else {
				$delete_ays = !MEDIA_TRASH ? " onclick='return showNotice.warn();'" : '';
				// $actions['delete'] = "<a class='submitdelete'$delete_ays href='" . wp_nonce_url( "post.php?action=delete&amp;post=$post->ID", 'delete-attachment_' . $post->ID ) . "'>" . __( 'Delete Permanently', 'frontend-uploader' ) . "</a>";
				$actions['delete'] = '<a href="'.admin_url( 'admin-ajax.php' ).'?action=delete_ugc&id=' . $post->ID . '&fu_nonce=' . wp_create_nonce( FU_NONCE ). '">'. __( 'Delete Permanently', 'frontend-uploader' ) .'</a>';
			}
			$actions['view'] = '<a href="' . get_permalink( $post->ID ) . '" title="' . esc_attr( sprintf( __( 'View "%s"', 'frontend-uploader' ), $att_title ) ) . '" rel="permalink">' . __( 'View', 'frontend-uploader' ) . '</a>';
			if ( current_user_can( 'edit_post', $post->ID ) )
				$actions['attach'] = '<a href="#the-list" onclick="findPosts.open( \'media[]\',\''.$post->ID.'\' );return false;" class="hide-if-no-js">'.__( 'Attach', 'frontend-uploader' ).'</a>';
		}
		else {
			if ( current_user_can( 'edit_post', $post->ID ) && !$this->is_trash )
				$actions['edit'] = '<a href="' . get_edit_post_link( $post->ID, true ) . '">' . __( 'Edit', 'frontend-uploader' ) . '</a>';
			if ( current_user_can( 'delete_post', $post->ID ) ) {
				if ( $this->is_trash )
					$actions['untrash'] = "<a class='submitdelete' href='" . wp_nonce_url( "post.php?action=untrash&amp;post=$post->ID", 'untrash-attachment_' . $post->ID ) . "'>" . __( 'Restore', 'frontend-uploader' ) . "</a>";
				elseif ( EMPTY_TRASH_DAYS && MEDIA_TRASH )
					$actions['trash'] = "<a class='submitdelete' href='" . wp_nonce_url( "post.php?action=trash&amp;post=$post->ID", 'trash-attachment_' . $post->ID ) . "'>" . __( 'Trash', 'frontend-uploader' ) . "</a>";
				if ( $this->is_trash || !EMPTY_TRASH_DAYS || !MEDIA_TRASH ) {
					$delete_ays = ( !$this->is_trash && !MEDIA_TRASH ) ? " onclick='return showNotice.warn();'" : '';
					$actions['delete'] = "<a class='submitdelete'$delete_ays href='" . wp_nonce_url( "post.php?action=delete&amp;post=$post->ID", 'delete-attachment_' . $post->ID ) . "'>" . __( 'Delete Permanently', 'frontend-uploader' ) . "</a>";
				}

				if ( $post->post_status == 'private' ) {
					$delete_ays = !MEDIA_TRASH ? " onclick='return showNotice.warn();'" : '';
					$actions['pass'] = '<a href="'.admin_url( 'admin-ajax.php' ).'?action=approve_ugc&id=' . $post->ID . '&fu_nonce=' . wp_create_nonce( FU_NONCE ). '">'. __( 'Approve', 'frontend-uploader' ) .'</a>';
					$actions['delete'] = '<a ' .  $delete_ays . ' href="'.admin_url( 'admin-ajax.php' ).'?action=delete_ugc&id=' . $post->ID . '&fu_nonce=' . wp_create_nonce( FU_NONCE ). '">'. __( 'Delete Permanently', 'frontend-uploader' ) .'</a>';
				}
			}
			if ( !$this->is_trash ) {
				$title =_draft_or_post_title( $post->post_parent );
				$actions['view'] = '<a href="' . get_permalink( $post->ID ) . '" title="' . esc_attr( sprintf( __( 'View "%s"', 'frontend-uploader' ), $title ) ) . '" rel="permalink">' . __( 'View', 'frontend-uploader' ) . '</a>';
			}
		}

		$actions = apply_filters( 'media_row_actions', $actions, $post, $this->detached );

		return $actions;
	}
}

// Add a nice little feature:
// Re-attach Media
// http://wordpress.org/support/topic/detach-amp-re-attach-media-attachment-images-from-posts

add_filter( "manage_fu_media_columns", 'fu_upload_columns' );
add_action( "manage_fu_media_custom_column", 'fu_media_custom_columns', 0, 2 );

function fu_upload_columns( $columns ) {
	unset( $columns['parent'] );
	$columns['better_parent'] = "Parent";
	return $columns;
}

function fu_media_custom_columns( $column_name, $id ) {
	$post = get_post( $id );
	if ( $column_name != 'better_parent' )
		return;

	if ( $post->post_parent > 0 ) {
		if ( get_post( $post->post_parent ) ) {
			$title =_draft_or_post_title( $post->post_parent );
		} else {
			$title = '<em>Untitled</em>';
		}
?>
			<strong><a href="<?php echo get_edit_post_link( $post->post_parent ); ?>"><?php echo $title ?></a></strong>, <?php echo get_the_time( __( 'Y/m/d', 'frontend-uploader' ) ); ?>
			<br />
			<a class="hide-if-no-js" onclick="findPosts.open('media[]','<?php echo $post->ID ?>' );return false;" href="#the-list"><?php _e( 'Re-Attach', 'frontend-uploader' ); ?></a>

			<?php
	} else {
?>
			<?php __( '(Unattached)', 'frontend-uploader' ); ?><br />
			<a class="hide-if-no-js" onclick="findPosts.open('media[]','<?php echo $post->ID ?>' );return false;" href="#the-list"><?php _e( 'Attach', 'frontend-uploader' ); ?></a>
			<?php
	}
}
