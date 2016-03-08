<?php
/*
	Plugin Name: Kapost Social Publishing Byline
	Plugin URI: http://www.kapost.com/
	Description: Kapost Social Publishing Byline
	Version: 1.9.6
	Author: Kapost
	Author URI: http://www.kapost.com
*/
define('KAPOST_BYLINE_ANALYTICS_URL', '//analytics.kapost.com');

define('KAPOST_BYLINE_VERSION', '1.9.6-WIP');

function kapost_byline_custom_fields($raw_custom_fields)
{
	if(!is_array($raw_custom_fields))
		return array();

	$custom_fields = array();
	foreach($raw_custom_fields as $i => $cf)
	{
		$k = sanitize_text_field($cf['key']);
		$v = sanitize_text_field($cf['value']);
		$custom_fields[$k] = $v;
	}

	return $custom_fields;
}

function kapost_is_protected_meta($protected_fields, $field)
{
	if(!in_array($field, $protected_fields))
		return false;

	if(function_exists('is_protected_meta'))
		return is_protected_meta($field, 'post');

	return ($field[0] == '_');
}

function kapost_byline_protected_custom_fields($custom_fields)
{		
	if(!isset($custom_fields['_kapost_protected']))
		return array();

	$protected_fields = array();
	foreach(explode('|', $custom_fields['_kapost_protected']) as $p)
	{
		list($prefix, $keywords) = explode(':', $p);

		$prefix = trim($prefix);
		if(empty($keywords))
		{	
			$protected_fields[] = "_${prefix}";
			continue;
		}

		foreach(explode(',', $keywords) as $k)
		{
			$kk = trim($k);
			$protected_fields[] = "_${prefix}_${kk}";
		}
	}	
	$pcf = array();
	foreach($custom_fields as $k => $v)
	{	
		if(kapost_is_protected_meta($protected_fields, $k))
			$pcf[$k] = $v;																								  
	}
	return $pcf;
}

function kapost_byline_update_array_custom_fields($id, $custom_fields)
{
	$prefix = '_kapost_array_';
	foreach($custom_fields as $k => $v)
	{
		if(strpos($k, $prefix) === 0)
		{
			$meta_key = str_replace($prefix, '', $k);
			delete_post_meta($id, $meta_key);

			if(empty($v))
				continue;
			$meta_values = @json_decode(@base64_decode($v), true);
			if(!is_array($meta_values))
				continue;

			foreach($meta_values as $meta_value)
				add_post_meta($id, $meta_key, $meta_value);
		}
	}
}

function kapost_byline_update_post($id, $custom_fields, $uid=false, $blog_id=false)
{
	$post = get_post($id);
	if(!is_object($post)) return false;

	$post_needs_update = false;

	// set any "array" custom fields
	kapost_byline_update_array_custom_fields($id, $custom_fields);

	// if this is a draft then clear the 'publish date' or set our own
	if($post->post_status == 'draft')
	{
		// this is required because otherwise any date we set will be
		// cleared by wp_update_post() down below ...
		$post->edit_date = true;

		if(isset($custom_fields['kapost_publish_date']))
		{
			$post_date = $custom_fields['kapost_publish_date']; // UTC
			$post->post_date = get_date_from_gmt($post_date);
			$post->post_date_gmt = $post_date;
		}
		else
		{
			$post->post_date = '0000-00-00 00:00:00';
			$post->post_date_gmt = '0000-00-00 00:00:00';
		}

		$post_needs_update = true;
	}

	// set our custom type
	if(isset($custom_fields['kapost_custom_type']))
	{
		$custom_type = $custom_fields['kapost_custom_type'];
		if(!empty($custom_type) && post_type_exists($custom_type))
		{
			$post->post_type = $custom_type;
			$post_needs_update = true;
		}
	}

	// set our featured image
	if(isset($custom_fields['kapost_featured_image']))
	{
		// look up the image by URL which is the GUID (too bad there's NO wp_ specific method to do this, oh well!)
		global $wpdb;
		$thumbnail = $wpdb->get_row($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND guid = %s LIMIT 1", $custom_fields['kapost_featured_image']));

		// if the image was found, set it as the featured image for the current post
		if(!empty($thumbnail))
		{
			// We support 2.9 and up so let's do this the old fashioned way
			// >= 3.0.1 and up has "set_post_thumbnail" available which does this little piece of mockery for us ...
			update_post_meta($id, '_thumbnail_id', $thumbnail->ID);
		}
	}

	// store our protected custom field required by our analytics
	if(isset($custom_fields['_kapost_analytics_post_id']))
	{
		// join them into one for performance and speed
		$kapost_analytics = array();
		foreach($custom_fields as $k => $v)
		{
			// starts with?
			if(strpos($k, '_kapost_analytics_') === 0)
			{
				$kk = str_replace('_kapost_analytics_', '', $k);
				$kapost_analytics[$kk] = $v;
			}
		}

		add_post_meta($id, '_kapost_analytics', $kapost_analytics);
	}

	// store other implicitly 'allowed' protected custom fields
	if(isset($custom_fields['_kapost_protected']))
	{
		foreach(kapost_byline_protected_custom_fields($custom_fields) as $k => $v)
		{
			delete_post_meta($id, $k);
			if(!empty($v)) add_post_meta($id, $k, $v);
		}
	}

	// match custom fields to custom taxonomies if appropriate
	$taxonomies = array_keys(get_taxonomies(array('_builtin' => false), 'names'));
	if(!empty($taxonomies))
	{
		foreach($custom_fields as $k => $v)
		{																										
			if(in_array($k, $taxonomies))
			{
				wp_set_object_terms($id, explode(',', $v), $k);
				delete_post_meta($id, $k);		
			}
		}
	}

	// set our post author
	if($uid !== false && $post->post_author != $uid)
	{
		$post->post_author = $uid;
		$post_needs_update = true;
	}

	// if any changes has been made above update the post once
	if($post_needs_update)
		wp_update_post((array) $post);

	return true;
}

function kapost_byline_inject_analytics()
{
	global $post;

	if(!is_single())
		return;

	if(!isset($post) || ($post->post_status != 'publish') || (strpos($post->post_content, '<!-- END KAPOST ANALYTICS CODE -->') !== FALSE))
		return;

	$kapost_analytics = get_post_meta($post->ID, '_kapost_analytics', true);
	if(empty($kapost_analytics))
		return;

	$url = KAPOST_BYLINE_ANALYTICS_URL;

	$post_id = esc_js($kapost_analytics['post_id']);

	if(isset($kapost_analytics['site_id']))
		$site_id = esc_js($kapost_analytics['site_id']);
	else
		$site_id = '';

echo "<!-- BEGIN KAPOST ANALYTICS CODE -->
<script type=\"text/javascript\">
<!--
var _kaq = _kaq || [];
_kaq.push([2, '$post_id', '$site_id']);
(function(){
var scheme = location.protocol == 'https:' ? location.protocol : 'http:';
var ka = document.createElement('script'); ka.async=true; ka.id='ka_tracker'; ka.src= scheme + '$url/ka.js';
var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ka, s);
})();
//-->
</script>
<!-- END KAPOST ANALYTICS CODE -->";
}

add_action('wp_footer', 'kapost_byline_inject_analytics');

function kapost_byline_xmlrpc_version()
{
	return KAPOST_BYLINE_VERSION;
}

function kapost_byline_xmlrpc_new_post($args)
{
	global $wp_xmlrpc_server;

	// create a copy of the arguments and escape that
	// in order to avoid any potential double escape issues
	$_args = $args;
	$wp_xmlrpc_server->escape($_args);

	$blog_id	= intval($_args[0]);
	$username	= $_args[1];
	$password	= $_args[2];
	$data		= $_args[3];

	if(!$wp_xmlrpc_server->login($username, $password))
		return $wp_xmlrpc_server->error;

	if(!current_user_can('publish_posts'))
		return new IXR_Error(401, __('Sorry, you are not allowed to publish posts on this site.'));
	$uid = false;
	$custom_fields = kapost_byline_custom_fields($data['custom_fields']);
	if(isset($custom_fields['kapost_author_email']))
	{
		$uid = email_exists($custom_fields['kapost_author_email']);
		if(!$uid || (function_exists('is_user_member_of_blog') && !is_user_member_of_blog($uid, $blog_id)))
			return new IXR_Error(401, 'The author of the post does not exist in WordPress.');
	}
	$id = $wp_xmlrpc_server->mw_newPost($args);
	if(is_string($id))
		kapost_byline_update_post($id, $custom_fields, $uid, $blog_id);
	return $id;
}

function kapost_byline_xmlrpc_edit_post($args)
{
	global $wp_xmlrpc_server, $current_site;

	// create a copy of the arguments and escape that
	// in order to avoid any potential double escape issues
	$_args = $args;
	$wp_xmlrpc_server->escape($_args);

	$blog_id	= $current_site->id;
	$post_id	= intval($_args[0]);
	$username	= $_args[1];
	$password	= $_args[2];
	$data		= $_args[3];

	if(!$wp_xmlrpc_server->login($username, $password))
		return $wp_xmlrpc_server->error;
	if(!current_user_can('edit_post', $post_id))
		return new IXR_Error(401, __('Sorry, you cannot edit this post.'));

	$post = get_post($post_id);
	if(!is_object($post) || !isset($post->ID))
		return new IXR_Error(404, __('Invalid post ID.'));

	$uid = false;
	$custom_fields = kapost_byline_custom_fields($data['custom_fields']);
	if(isset($custom_fields['kapost_author_email']))
	{
		$uid = email_exists($custom_fields['kapost_author_email']);
		if(!$uid || (function_exists('is_user_member_of_blog') && !is_user_member_of_blog($uid, $blog_id)))
			return new IXR_Error(401, 'The author of the post does not exist in WordPress.');
	}

	if(in_array($post->post_type, array('post', 'page')))
	{
		$result = $wp_xmlrpc_server->mw_editPost($args);

		if($result === true)
			kapost_byline_update_post($post_id, $custom_fields, $uid, $blog_id);

		return $result;
	}

	// to avoid double escaping the content structure in wp_editPost
	// point data to the original structure
	$data = $args[3];

	$content_struct = array();
	$content_struct['post_type'] = $post->post_type; 
	$content_struct['post_status'] = $publish ? 'publish' : 'draft';

	if(isset($data['title']))
		$content_struct['post_title'] = $data['title'];

	if(isset($data['description']))
		$content_struct['post_content'] = $data['description'];

	if(isset($data['custom_fields']))
		$content_struct['custom_fields'] = $data['custom_fields'];

	if(isset($data['mt_excerpt']))
		$content_struct['post_excerpt'] = $data['mt_excerpt'];

	if(isset($data['mt_keywords']) && !empty($data['mt_keywords']))
		$content_struct['terms_names']['post_tag'] = explode(',', $data['mt_keywords']);

	if(isset($data['categories']) && !empty($data['categories']) && is_array($data['categories']))
		$content_struct['terms_names']['category'] = $data['categories'];

	$result = $wp_xmlrpc_server->wp_editPost(array($blog_id, $args[1], $args[2], $args[0], $content_struct));

	if($result === true)
		kapost_byline_update_post($post_id, $custom_fields, $uid, $blog_id);

	return $result;
}

function kapost_byline_xmlrpc_get_post($args)
{
	global $wp_xmlrpc_server;

	return $wp_xmlrpc_server->mw_getPost($args);
}

function kapost_byline_xmlrpc_new_media_Object($args)
{
	global $wpdb, $wp_xmlrpc_server;

	// create a copy of the arguments and escape that
	// in order to avoid any potential double escape issues
	$_args = $args;
	$wp_xmlrpc_server->escape($_args);

	$blog_id	= intval($_args[0]);
	$username	= $_args[1];
	$password	= $_args[2];
	$data		= $_args[3];

	if(!$wp_xmlrpc_server->login($username, $password))
		return $wp_xmlrpc_server->error;

	if(!current_user_can('upload_files'))
		return new IXR_Error(401, __('You are not allowed to upload files to this site.'));
	$image = $wp_xmlrpc_server->mw_newMediaObject($args);
	if(!is_array($image) || empty($image['url']))
		return $image;

	$attachment = $wpdb->get_row($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND guid = %s LIMIT 1", $image['url']));

	if(empty($attachment))
		return $image;

	$update_attachment = false;

	if(isset($data['description']))
	{
		$attachment->post_content = sanitize_text_field($data['description']);
		$update_attachment = true;
	}

	if(isset($data['title']))
	{
		$attachment->post_title	= sanitize_text_field($data['title']);
		$update_attachment = true;
	}

	if(isset($data['caption']))
	{
		$attachment->post_excerpt = sanitize_text_field($data['caption']);
		$update_attachment = true;
	}

	if($update_attachment) 
		wp_update_post($attachment);

	if(isset($data['alt'])) 
		add_post_meta($attachment->ID, '_wp_attachment_image_alt', sanitize_text_field($data['alt']));

	if(!isset($image['id']))
		$image['id'] = $attachment->ID;

	return $image;
}

function kapost_byline_xmlrpc_get_permalink($args)
{
	global $wp_xmlrpc_server;
	$wp_xmlrpc_server->escape($args);

	$post_id	= intval($args[0]);
	$username	= $args[1];
	$password	= $args[2];

	if(!$wp_xmlrpc_server->login($username, $password))
		return $wp_xmlrpc_server->error;
	if(!current_user_can('edit_post', $post_id))
		return new IXR_Error(401, __('Sorry, you cannot edit this post.'));

	$post = get_post($post_id);
	if(!is_object($post) || !isset($post->ID))
		return new IXR_Error(401, __('Sorry, you cannot edit this post.'));

	list($permalink, $post_name) = get_sample_permalink($post->ID);
	$permalink = str_replace(array('%postname%', '%pagename%'), $post_name, $permalink);

	if(strpos($permalink, "%") !== false) # make sure it doesn't contain %day%, etc.
		$permalink = get_permalink($post);

	return $permalink;
}

function kapost_byline_xmlrpc($methods)
{
	$methods['kapost.version']			= 'kapost_byline_xmlrpc_version';
	$methods['kapost.newPost']			= 'kapost_byline_xmlrpc_new_post';
	$methods['kapost.editPost']			= 'kapost_byline_xmlrpc_edit_post';
	$methods['kapost.getPost']			= 'kapost_byline_xmlrpc_get_post';
	$methods['kapost.newMediaObject']	= 'kapost_byline_xmlrpc_new_media_object';
	$methods['kapost.getPermalink']		= 'kapost_byline_xmlrpc_get_permalink';
	return $methods;
}
add_filter('xmlrpc_methods', 'kapost_byline_xmlrpc');

?>