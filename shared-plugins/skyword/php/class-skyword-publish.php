<?php


/**
 * Code for post creation
 */
class Skyword_Publish
{

	/**
	 * Class constructor
	 */
	public function __construct() {
		add_filter( 'xmlrpc_methods', array( $this, 'skyword_xmlrpc_methods' ) );
		$this->skyword_defaults();
	}
	/**
	* Extend XMLRPC calls
	*/
	public function skyword_xmlrpc_methods( $methods ) {
		$methods['skyword_post'] = array( $this, 'skyword_post');
		$methods['skyword_newMediaObject'] = array( $this, 'skyword_newMediaObject' );
		$methods['skyword_author'] = array( $this, 'skyword_author' );
		$methods['skyword_version'] =  array( $this,  'skyword_version' );
		$methods['skyword_version_number'] = array( $this, 'skyword_version_number' );
		$methods['skyword_getAuthors'] = array( $this, 'skyword_get_authors' );
		$methods['skyword_getCategories'] = array( $this, 'skyword_get_categories' );
		$methods['skyword_getTags'] = array( $this, 'skyword_get_tags' );
		$methods['skyword_getPost'] = array( $this, 'skyword_get_post' );
		$methods['skyword_deletePost'] = array( $this, 'skyword_delete_post' );
		$methods['skyword_getTaxonomies'] = array( $this, 'skyword_get_taxonomies' );
		return $methods;
	}

	// Set defaults on initial plugin activation
	private function skyword_defaults() {
		$tmp = get_option('skyword_plugin_options');
	    if(!is_array($tmp)) {
			$arr = array(
			"skyword_api_key"=>null, 
			"skyword_enable_ogtags" => true, 
			"skyword_enable_metatags" => true, 
			"skyword_enable_googlenewstag" => true,
			"skyword_enable_pagetitle" => true,
			"skyword_enable_sitemaps" => true,
			"skyword_generate_all_sitemaps" => true,
			"skyword_generate_news_sitemaps" => true,
			"skyword_generate_pages_sitemaps" => true,
			"skyword_generate_categories_sitemaps" => true,
			"skyword_generate_tags_sitemaps" => true
			);
			update_option('skyword_plugin_options', $arr);
		}
	}

	/**
	* Returns current version of plugin to write.skyword.com.  
	*/
	public function skyword_version( $args ) {
		$login = $this->login( $args );
		if ( 'success' == $login['status'] ) {
			return esc_html (strval( __('Wordpress Version: '.get_bloginfo('version').' Plugin Version: '.SKYWORD_VERSION) ) );
		} else {
			return esc_html ($login['message'] );
		}
	}
	/**
	* Returns version number of plugin
	*/
	public function skyword_version_number( $args ) {
		global $wp_xmlrpc_server;
		$wp_xmlrpc_server->escape( $args );
 		$login = $this->login( $args );
		if ( 'success' == $login['status'] ) {
			return esc_html ( strval( SKYWORD_VN ) );
		} else {
			return esc_html ( $login );
		}
	}
	/**
	* Gets author id if they exist, otherwise creates guest author with co-author-plus plugin
	*/
	public function skyword_author( $args ) {
		global $wp_xmlrpc_server;
		$wp_xmlrpc_server->escape( $args );
		$login = $this->login( $args );
		if ( 'success' == $login['status'] ) {
			$data = $args[3];
			$user_id = $this->check_username_exists( $data );
			return esc_html(strval( $user_id ) );
		} else {
			return esc_html( $login['message'] );
		}
	}
	/**
	* Returns list of authors associated with site for ghost writing
	*/
	public function skyword_get_authors( $args ) {
		global $wp_xmlrpc_server;
		$wp_xmlrpc_server->escape( $args );
		$authors = $wp_xmlrpc_server->wp_getAuthors ($args);
		return $authors;


	}
	/** 
	* Returns list of categories for write.skyword.com publishing
	*/
	public function skyword_get_categories( $args = '' ) {
		global $wp_xmlrpc_server;
		$wp_xmlrpc_server->escape( $args );
		$categories = $wp_xmlrpc_server->mw_getCategories ($args);
		return $categories;
	}
	/**
	* Returns list of tags for write.skyword.com publishing
	*/
	public function skyword_get_tags( $args = '' ) {
		global $wp_xmlrpc_server;
		$wp_xmlrpc_server->escape( $args );
		$tags = $wp_xmlrpc_server->wp_getTags ($args);
		return $tags;
		
	}
	public function skyword_get_taxonomies( $args = '' ) {
		global $wp_xmlrpc_server;
		$wp_xmlrpc_server->escape( $args );
		$login = $this->login($args);
		if ( 'success' == $login['status'] ) {
			$taxonomiesStruct = array();
			$taxonomies = get_taxonomies(null, "objects"); 

			if ( $taxonomies ) {
				foreach ( $taxonomies as $taxonomy ) {
					$struct['name']    = $taxonomy->name;
					$hierarchical = $taxonomy->hierarchical;
					$terms = get_terms( $struct['name'], array(
 						'hide_empty' => 0 ) 
					);
					foreach ( $terms as $term ) {
						$termStruct['name'] = $term->name;
						if ($hierarchical){
							$termStruct['id'] = $term->term_id;
						} else {
							$termStruct['id'] = $term->name;
						}
						$termsArr[] = $termStruct;
 					}
 					if ($terms){
	 					$struct['terms'] = $termsArr;
	 					$struct['termString'] = $termString;	//@todo undefined variable
						$taxonomiesStruct[] = $struct;
					}
					unset($termsArr);
					$termsArr = array();
				}
			}

			return $taxonomiesStruct;
		} else {
			return esc_html( $login['message'] );
		}
	}

	
	/**
	* Returns permalink for post to write.skyword.com
	*/
	public function skyword_get_post( $args = '' )	{
		global $wp_xmlrpc_server;
		$wp_xmlrpc_server->escape( $args );
		$response = $wp_xmlrpc_server->wp_getPost ($args);
		return $response;
	}
	/**
	* Deletes post by id 
	*/
	public function skyword_delete_post( $args = '' ) {
		global $wp_xmlrpc_server;
		$wp_xmlrpc_server->escape( $args );
		$response = $wp_xmlrpc_server->wp_deletePost ($args);
		return $response;
	}
	/**
	* Creates posts from write.skyword.com
	*/
	public function skyword_post( $args ) {
		global $coauthors_plus;
		$login = $this->login( $args );
		if ( 'success' == $login['status'] ) {
			$data = $args[3];
			if ( null != $data['publication-date'] ) {
				$dateCreated = $data['publication-date']->getIso();
				$post_date = get_date_from_gmt( iso8601_to_datetime( $dateCreated ) );
			} else {
				$post_date = current_time('mysql');
			}
			if ( null != $data['publication-state'] ) {
				$state = sanitize_text_field( $data['publication-state'] );
			} else {
				$state = "draft";
			}

			$categories = $data['categories'];
			$post_category = array();
			foreach ( $categories as $category ) {
				$categoryId = (int) $category['id'];
				if ( $categoryId != null && $categoryId != 0 ){
					$post_category[] = $category['id'];
				}
				
				
			}
			$data['post-id'] = $this->check_content_exists( $data['skyword_content_id'] , $data['post-type'] );
			$new_post = array(
				'post_status' => $state,
				'post_date' =>  $post_date,
				'post_excerpt' => wp_kses_post( $data['excerpt'] ),
				'post_type' => sanitize_text_field( $data['post-type'] ),
				'comment_status' => 'open',
				'post_category' => $post_category	//sanitized above
			);

			if (null != $data['title']) {
				$new_post['post_title'] = wp_kses_post( $data['title'] );
			}
			if (null != $data['description']) {
				$new_post['post_content'] = wp_kses_post ( $data['description'] );
			}
			if (null != $data['slug']) {
				$new_post['post_name'] = sanitize_text_field( $data['slug'] );
			}
			if (null != $data['post-id']) {
				$new_post['ID'] = (int) $data['post-id'];
			}
			if (null != $data['user-id'] &&  is_numeric( trim( $data['user-id'] ) ) ) {
				$new_post['post_author'] = $data['user-id'];
			}

			$post_id = wp_insert_post($new_post);
		
			$utf8string =  html_entity_decode( $data['tags-input'] );
			wp_set_post_tags( $post_id, $utf8string, false );

			//attach attachments to new post;
			$this->attach_attachments( $post_id, $data );
			//add content template/attachment information as meta
			$this->create_custom_fields( $post_id, $data );
			$this->update_custom_field( $post_id, 'skyword_tracking_tag', $data['tracking'] );
			$this->update_custom_field( $post_id, 'skyword_seo_title', wp_kses_post( $data['metatitle'] ) );
			$this->update_custom_field( $post_id, 'skyword_metadescription', wp_kses_post( $data['metadescription'] ) );
			$this->update_custom_field( $post_id, 'skyword_keyword', wp_kses_post( $data['metakeyword'] ) );
			$this->update_custom_field( $post_id, 'skyword_content_id', wp_kses_post( $data['skyword_content_id'] ) );
			
			//add custom taxonomy values
			foreach ( $data["taxonomies"] as $taxonomy ) { 
			    wp_set_post_terms( $post_id, $taxonomy['values'], $taxonomy['name'], true );
			}
			
			//Create sitemap information
			//@todo the input below should be sanitized before being inserted into the DB.
			if ( 'news' == $data['publication-type'] ) {
				$this->update_custom_field($post_id, 'skyword_publication_type', 'news');
				if ( null != $data['publication-access'] ) {
					$this->update_custom_field($post_id, 'skyword_publication_access', wp_kses_post( ['publication-access'] ) );
				}
				if ( null != $data['publication-name'] ) {
					$this->update_custom_field($post_id, 'skyword_publication_name', wp_kses_post( $data['publication-name'] ) );
				}
				if ( null != $data['publication-geolocation'] ) {
					$this->update_custom_field($post_id, 'skyword_geolocation', wp_kses_post( $data['publication-geolocation'] ) );
				}
				if ( null != $data['publication-keywords'] ) {
					$this->update_custom_field($post_id, 'skyword_tags', wp_kses_post( $data['publication-keywords'] ) );
				}
				if ( null != $data['publication-stocktickers'] ) {
					$this->update_custom_field($post_id, 'skyword_stocktickers', wp_kses_post( $data['publication-stocktickers'] ) );
				}
			} else {
				$this->update_custom_field($post_id, 'skyword_publication_type', 'evergreen');
			}
			if ( null != $coauthors_plus) {
				if ( !is_numeric( trim ( $data['user-id'] ) ) )  {
					$data['user-id'] = str_replace( 'guest-', '', $data['user-id'] );
					$author = $coauthors_plus->guest_authors->get_guest_author_by( 'ID', $data['user-id'] );
					$author_term = $coauthors_plus->update_author_term( $author );
					wp_set_post_terms( $post_id, $author_term->slug, $coauthors_plus->coauthor_taxonomy, true );
				}
			}
			return esc_html( strval($post_id) );
		} else {
			return esc_html( $login['message'] );
		}
	}
	/**
	* Modified image upload based off of xmlrpc newMediaObject function.
	* Adds ability to include alt title, caption, and description to attachment
	*/
	public function skyword_newMediaObject( $args ) {
		$login = $this->login($args);
		if ( 'success' == $login['status'] ) {
			global $wpdb;

			$data = $args[3];
			$name = sanitize_file_name( $data['name'] );
			$type = esc_html ($data['type'] );
			$bits = $data['bits'];
			$title =  esc_html( $data['title'] );
			$caption =  esc_html( $data['caption'] );
			$alttext = esc_html( $data['alttext'] );
			$description =  esc_html( $data['description'] );
			if (!isset($title)) {
				$title = $name;
			}

			logIO( 'O', '(MW) Received '.strlen($bits).' bytes' );

			do_action( 'xmlrpc_call', 'metaWeblog.newMediaObject' );


			if ( $upload_err = apply_filters( 'pre_upload_error', false ) )
				return new IXR_Error(500, $upload_err);

			
			$upload = wp_upload_bits($name, NULL, $bits);
			if ( ! empty($upload['error']) ) {
				$errorString = esc_html (sprintf(__('Could not write file %1$s (%2$s)'), $name, $upload['error']) );
				logIO('O', '(MW) ' . $errorString);
				return new IXR_Error(500, $errorString);
			}
			// Construct the attachment array
			// attach to post_id 0
			$post_id = 0;
			$attachment = array(
				'post_title' => $title,
				'post_content' => '',
				'post_type' => 'attachment',
				'post_parent' => $post_id,
				'post_mime_type' => $type,
				'post_excerpt' => $caption,
				'post_content' => $description,
				'guid' => $upload[ 'url' ]
			);

			// Save the data
			$id = wp_insert_attachment( $attachment, $upload[ 'file' ], $post_id );
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $upload['file'] ) );
			//adds alt text as meta
			add_post_meta( $id, "_wp_attachment_image_alt", $alttext, false) ;
			return apply_filters( 'wp_handle_upload', array( 'file' => $upload[ 'file' ], 'url' => $upload[ 'url' ], 'type' => $type ), 'upload' );
		} else {
			return esc_html( $login['message'] );
		}

	}
	/**
	* Checks if post exists identified by skyword content id, used to avoid duplicates if publishing error occcurs
	*/
	private function check_content_exists( $skywordId, $postType ) {
		$query = array(
		    'ignore_sticky_posts' => true,
			'meta_key' => 'skyword_content_id',
			'meta_value' => $skywordId,
		    'post_type' => $postType,
			'post_status' => array('publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash')
		);
		query_posts( $query );
		if ( have_posts() ) :
			while ( have_posts() ) : the_post();
				$str = get_the_ID() ;
			return $str;
			endwhile;
		else :
			return null;
		endif;
	}
	/**
	* Uses nonce or un/pw to authenticate whether user is able to interact with plugin
	*/
	private function login( $args ) {
		$username = $args[1];
		$password = $args[2];
		global $wp_xmlrpc_server;
		if ( !$user = $wp_xmlrpc_server->login($username, $password) ) {
			$response['message'] = new IXR_Error(403, esc_html ( __( 'Invalid UN/PW Combination: UN = '.$username.' PW = '.$password ) ) );
			$response['status'] = 'error';
		} else if (!user_can($user->ID, 'edit_posts')) {
			$response['message'] = new IXR_Error(403, esc_html ( __( 'You do not have sufficient privileges to login.' ) ));
			$response['status'] = 'error';
		} else {
			$response['status'] = 'success';
		}

		return $response;

	}

	/**
	* Checks whether username exists. 
	* Creates Author if not
	*/
	private function check_username_exists( $data ) {
		global $coauthors_plus;
		$user_id = username_exists( $data['user-name'] );
		if (!$user_id) {

			if ( null != $coauthors_plus) {

				$guest_author = array();
				$guest_author['ID'] = '';
				$guest_author['display_name'] = $data['display-name'];
				$guest_author['first_name'] = $data['first-name'];
				$guest_author['last_name'] = $data['last-name'];
				$guest_author['user_login'] = $data['user-name'];
				$guest_author['user_email'] = $data['email'];
				$guest_author['description'] = $data['bio'];
				$guest_author['jabber'] = '';
				$guest_author['yahooim'] = '';
				$guest_author['aim'] = '';
				$guest_author['website'] = $data['website'];
				$guest_author['linked_account'] = '';
				$guest_author['website'] = $data['website'];
				$guest_author['company'] = $data['company'];
				$guest_author['title'] = $data['title'];
				$guest_author['google'] = $data['google'];
				$guest_author['twitter'] = $data['twitter'];

				$retval = $coauthors_plus->guest_authors->create( $guest_author );
				if( is_wp_error( $retval ) ) {
					$author = $coauthors_plus->guest_authors->get_guest_author_by( 'user_login', $data['user-name'] );
					if (null != $author){
						$user_id = 'guest-'.$author->ID;
					}
				} else {
					$user_id = 'guest-'.$retval;
				}
				
			}
		
			
		}
		return $user_id;
	}
	/**
	* Attaches attachments provided to a specific post
	*/
	private function attach_attachments( $post_id, $data ) {
		global $wpdb;
		$args = array(
			'post_type' => 'attachment',
			'post_parent' => 0,
			'suppress_filters' => false
		);

		$attachments = get_posts( $args );

		if ( is_array( $attachments ) ) {
			foreach ( $attachments as $file ) {
				if ( is_array( $data['attachments'] ) ) {
					foreach ( $data['attachments'] as $attachmentExt ) {
						if ( $attachmentExt == $file->guid ) {
							$wpdb->update($wpdb->posts, array('post_parent' => $post_id), array('ID' => $file->ID) );
						}
						if ( false !== strpos( $attachmentExt, $file->guid.'featured' ) ) {
							delete_post_meta($post_id, '_thumbnail_id');
							add_post_meta($post_id, '_thumbnail_id', $file->ID, false);
							$wpdb->update($wpdb->posts, array('post_parent' => $post_id), array('ID' => $file->ID) );

						}
					}
				}
			}
		}
	}
	/** 
	* Updates all custom fields provided by write.skyword.com
	*/
	private function create_custom_fields( $post_id, $data ) {
		$custom_fields = explode( ':', $data['custom_fields']);
		foreach ( $custom_fields as $custom_field ) {
			$fields = explode( '-', $custom_field );
            delete_post_meta( $post_id, str_replace( '%2d', '-', $fields[0] ) );
			add_post_meta($post_id, str_replace('%2d', '-', $fields[0]), str_replace('%3A', ':', str_replace('%2d', '-', $fields[1])), false);
		}
	}
	/** 
	* Updates specified custom field
	*/
	private function update_custom_field( $post_id, $key, $data ) {
		delete_post_meta($post_id, $key);
		add_post_meta($post_id, $key, $data, false);
	}
}

global $skyword_publish;
$skyword_publish = new Skyword_Publish();