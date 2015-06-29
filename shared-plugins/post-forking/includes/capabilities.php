<?php
/**
 * Register default roles and capabilities
 */

class Fork_Capabilities {

	public $cap_version = 1;

	public $defaults = array(
		'administrator' => array(
			'edit_forks'             => true,
			'edit_fork'              => true,
			'edit_others_forks'      => true,
			'edit_private_forks'     => true,
			'edit_published_forks'   => true,
			'read_forks'             => true,
			'read_private_forks'     => true,
			'delete_forks'           => true,
			'delete_others_forks'    => true,
			'delete_private_forks'   => true,
			'delete_published_forks' => true,
			'publish_forks'          => true,
		),
		'editor' => array(
			'edit_forks'             => true,
			'edit_fork'              => true,
			'edit_others_forks'      => true,
			'edit_private_forks'     => true,
			'edit_published_forks'   => true,
			'read_forks'             => true,
			'read_private_forks'     => true,
			'delete_forks'           => true,
			'delete_others_forks'    => true,
			'delete_private_forks'   => true,
			'delete_published_forks' => true,
			'publish_forks'          => true,
		),
		'author' => array(
			'edit_forks'             => true,
			'edit_others_forks'      => false,
			'edit_private_forks'     => true,
			'edit_published_forks'   => true,
			'read_forks'             => true,
			'read_private_forks'     => true,
			'delete_forks'           => true,
			'delete_others_forks'    => false,
			'delete_private_forks'   => true,
			'delete_published_forks' => true,
			'publish_forks'          => true,
		),
		'subscriber' => array(
			'edit_forks'             => true,
			'edit_others_forks'      => false,
			'edit_private_forks'     => false,
			'edit_published_forks'   => true,
			'read_forks'             => true,
			'read_private_forks'     => false,
			'delete_forks'           => true,
			'delete_others_forks'    => false,
			'delete_private_forks'   => false,
			'delete_published_forks' => false,
			'publish_forks'          => false,
		),
	);

	/**
	 * Register with WordPress API
	 */
	function __construct( &$parent ) {

		$this->parent = &$parent;
		add_action( 'init', array( $this, 'add_caps' ) );
		add_filter( 'map_meta_cap', array( $this, 'map_meta_cap' ), 10, 4 );

	}


	/**
	 * Adds plugin-specific caps to all roles so that 3rd party plugins can manage them
	 */
	function add_caps() {
		$version = get_option('post_forking_cap_version');

		// Bail Early if we have already set the caps and aren't updating them
		if ($version !== false && $this->cap_version <= (int) $version)
			return;
		
		add_option('post_forking_cap_version' , $this->cap_version, '', 'yes');

		global $wp_roles;
		if ( ! isset( $wp_roles ) )
			$wp_roles = new WP_Roles;

		foreach (  $wp_roles->role_names as $role=>$label ) {

			//if the role is a standard role, map the default caps, otherwise, map as a subscriber
			$caps = ( array_key_exists( $role, $this->defaults ) ) ? $this->defaults[$role] : $this->defaults['subscriber'];

			//loop and assign
			foreach ( $caps as $cap=>$grant ) {

				//check to see if the user already has this capability, if so, don't re-add as that would override grant
				if ( !isset( $wp_roles->roles[$role]['capabilities'][$cap] ) ) {
					$wp_roles->add_cap( $role, $cap, $grant );
				} else {
					$wp_roles->remove_cap( $role, $cap );
					$wp_roles->add_cap( $role, $cap, $grant );
				}

			}
		}
	}
	
	function map_meta_cap( $caps, $cap, $userID, $args = null ) {
  	
  	     $parent = $this->parent;
  	     $cpt = get_post_type_object( $parent->get_post_type() );

  	     //pre init, CPT not yet registered
  	     if ( !$cpt )
  	         return $caps;
            
  	     switch ( $cap ) {

  	     	// prevent editing of 'merged' posts.
  	     	case 'edit_post':
  	     		if ( empty( $args ) ) break;
  	     		if ( 'fork' == get_post_type( $args[0] ) && in_array( get_post_status( $args[0] ), array('publish', 'merged') ) )
  	     			$caps[] = 'do_not_allow';
        	break;

			// Deprecate this.  Eliminate the concept of Branches.  Only Forks will survive.
        	case 'branch_post':
			
        	   unset( $caps[ array_search( $cap, $caps ) ] );
        	   $caps[] = $cpt->cap->edit_posts;

                //no postID given
                if ( !is_array( $args ) )
                    break;
                
                //only let the post author fork the post                
                if ( $userID != get_post( $args[0] )->post_author )
                    $caps[] = 'do_not_allow';
                            	       
        	break;


			// This should be based on the parent post.  See https://github.com/post-forking/post-forking/issues/96 
        	case 'fork_post':
        	   unset( $caps[ array_search( $cap, $caps ) ] );
  	       	   $caps[] = $cpt->cap->edit_posts;

        	break;

			// This should be based on the parent post.  See https://github.com/post-forking/post-forking/issues/96 
        	case 'publish_fork':
				unset( $caps[ array_search( $cap, $caps ) ] );
  	       	   	//$caps[] = $cpt->cap->publish_posts;

               	if ( !is_array( $args ) )
                	break;

				if ('publish' ===  get_post( $args[0] )->post_status) 
	               	$edit_parent_cap = get_post_type_object( get_post_type( get_post($args[0])->post_parent ) )->cap->edit_published_posts;
				else
	               	$edit_parent_cap = get_post_type_object( get_post_type( get_post($args[0])->post_parent ) )->cap->edit_post;
				
                
               	//if user cannot edit parent post, don't let them publish
               	if ( user_can( $userID, $edit_parent_cap, get_post($args[0])->post_parent ) ) {
					$caps = array();
               	} else {
                   	$caps[] = 'do_not_allow';
			   	}

        	break;
    	
        }
        
        return $caps;
  	
	}


}
