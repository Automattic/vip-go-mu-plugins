<?php
global $publishthis;

$meta = get_post_meta( $post->ID );

// Poll interval
$poll_interval = '300';
if (isset ( $meta ['_publishthis_poll_interval'] [0] )) {
	$poll_interval = $meta ['_publishthis_poll_interval'] [0];
}

$publish_author = '';
if (isset ( $meta ['_publishthis_publish_author'] [0] )) {
	$publish_author = $meta ['_publishthis_publish_author'] [0];
}

$read_more = 'Read More ...';
if (isset ( $meta ['_publishthis_read_more'] [0] )) {
	$read_more = $meta ['_publishthis_read_more'] [0];
}

// Feed templates
$feed_templates = $publishthis->api->get_feed_templates();

$feed_template = 0;
if (isset ( $meta ['_publishthis_feed_template'] [0] )) {
	$feed_template = $meta ['_publishthis_feed_template'] [0];
}

// Template section
$template_section = 0;
if (isset ( $meta ['_publishthis_template_section'] [0] )) {
	$template_section = $meta ['_publishthis_template_section'] [0];
}

// Content type
$content_type = 'post';
if (isset ( $meta ['_publishthis_content_type'] [0] )) {
	$content_type = $meta ['_publishthis_content_type'] [0];
}

// Content type format
$content_type_format = 'individual';
if (isset ( $meta ['_publishthis_content_type_format'] [0] )) {
	$content_type_format = $meta ['_publishthis_content_type_format'] [0];
}

// Content status
$content_status = 'draft';
if (isset ( $meta ['_publishthis_content_status'] [0] )) {
	$content_status = $meta ['_publishthis_content_status'] [0];
}

// Featured image
$featured_image = '0';
if (isset ( $meta ['_publishthis_featured_image'] [0] )) {
	$featured_image = $meta ['_publishthis_featured_image'] [0];
}

// setting the maximum image width
$max_image_width = '300';
if (isset ( $meta ['_publishthis_max_image_width'] [0] )) {
	$max_image_width = $meta ['_publishthis_max_image_width'] [0];
}

// ok to resize preview images
$ok_resize_preview = '0';
if (isset ( $meta ['_publishthis_ok_resize_preview'] [0] )) {
	$ok_resize_preview = $meta ['_publishthis_ok_resize_preview'] [0];
}

// set the image alignment
// 0 - none, 1 - center, 2 - left, 3 - right
$image_alignment = '0';
if (isset ( $meta ['_publishthis_image_alignment'] [0] )) {
	$image_alignment = $meta ['_publishthis_image_alignment'] [0];
}

// set the annotation placement
// 0 - Above, 1 - Below
$annotation_placement = '0';
if (isset ( $meta ['_publishthis_annotation_placement'] [0] )) {
	$annotation_placement = $meta ['_publishthis_annotation_placement'] [0];
}

// Category
$category = '0';
if (isset ( $meta ['_publishthis_category'] [0] )) {
	$category = $meta ['_publishthis_category'] [0];
}

// Synchronize
$synchronize = '0';
if (isset ( $meta ['_publishthis_synchronize'] [0] )) {
	$synchronize = $meta ['_publishthis_synchronize'] [0];
}

?>

<table class="publishthis-input widefat" id="publishthis-options">
	<tr>
		<td class="label"><label for="publishthis-poll-interval-field"><?php _e('Poll Interval', 'publishthis'); ?></label>
		</td>
		<td>
			<select id="publishthis-poll-interval-field" name="publishthis_publish_action[poll_interval]">
				<option value="60" <?php selected($poll_interval, '60'); ?>>1 min</option>
				<option value="300" <?php selected($poll_interval, '300'); ?>>5 min</option>
				<option value="600" <?php selected($poll_interval, '600'); ?>>10 min</option>
				<option value="900" <?php selected($poll_interval, '900'); ?>>15 min</option>
				<option value="1800" <?php selected($poll_interval, '1800'); ?>>30 min</option>
				<option value="2700" <?php selected($poll_interval, '2700'); ?>>45 min</option>
				<option value="3600" <?php selected($poll_interval, '3600'); ?>>60 min</option>
				<option value="7200" <?php selected($poll_interval, '7200'); ?>>2 hrs</option>
				<option value="21600" <?php selected($poll_interval, '21600'); ?>>6 hrs</option>
				<option value="43200" <?php selected($poll_interval, '43200'); ?>>12 hrs</option>
				<option value="86400" <?php selected($poll_interval, '86400'); ?>>24 hrs</option>
			</select>
		</td>
	</tr>

	<tr>
		<td class="label"><label for="publishthis-publish-author-field"><?php _e('Publish Author', 'publishthis'); ?></label>
		</td>
		<td><?php
		$authorsArgs = array ('exclude' => '1', 'who' => 'authors' );
		
		wp_dropdown_users ( array ('who' => 'authors', 'name' => 'publishthis_publish_action[publish_author]', 'id' => 'publishthis-publish-author-field', 'include_selected' => true, 'selected' => $publish_author ) );
		?>
		</td>
	</tr>


	<tr>
		<td class="label"><label for="publishthis-feed-template-field"><?php _e('Feed Template', 'publishthis'); ?></label>
		</td>
		<td>
			<select id="publishthis-feed-template-field" name="publishthis_publish_action[feed_template]">
			<?php foreach ($feed_templates as $template) : ?>
				<option value="<?php echo esc_attr($template->templateId); ?>" <?php selected($template->templateId, (int)$feed_template); ?>><?php echo esc_attr($template->displayName); ?></option>
			<?php endforeach; ?>
			</select>
		</td>
	</tr>
	<tr>
		<td class="label"><label for="publishthis-template-section-field"><?php _e('Template Section', 'publishthis'); ?></label>
		</td>
		<td>
			<select id="publishthis-template-section-field" name="publishthis_publish_action[template_section]" 
				data-template-section="<?php echo esc_attr($template_section); ?>">
			</select>
		</td>
	</tr>
	<tr>
		<td class="label"><label for="publishthis-content-type-field"><?php _e('Content Type', 'publishthis'); ?></label>
		</td>
		<td>
			<ul class="radio_list radio vertical">
				<li><label><input type="radio"
						name="publishthis_publish_action[content_type]"
						id="publishthis-content-type-field" value="post"
						<?php checked($content_type, 'post'); ?> /> Posts</label></li>
				<li><label><input type="radio"
						name="publishthis_publish_action[content_type]"
						id="publishthis-content-type-field" value="page"
						<?php checked($content_type, 'page'); ?> /> Pages</label></li>
			</ul>
		</td>
	</tr>
	<tr>
		<td class="label"><label for="publishthis-content-type-format-field"><?php _e('Content Type Format', 'publishthis'); ?></label>
		</td>
		<td>
			<ul class="radio_list radio vertical">
				<li><label><input type="radio"
						name="publishthis_publish_action[content_type_format]"
						id="publishthis-content-type-format-field" value="individual"
						<?php checked($content_type_format, 'individual'); ?> />
						Individual</label></li>
				<li><label><input type="radio"
						name="publishthis_publish_action[content_type_format]"
						id="publishthis-content-type-format-field" value="combined"
						<?php checked($content_type_format, 'combined'); ?> /> Combined</label></li>
			</ul>
		</td>
	</tr>
	<tr>
		<td class="label"><label for="publishthis-content-status-field"><?php _e('Content Status', 'publishthis'); ?></label>
		</td>
		<td>
			<ul class="radio_list radio vertical">
				<li><label><input type="radio"
						name="publishthis_publish_action[content_status]"
						id="publishthis-content-status-field" value="draft"
						<?php checked($content_status, 'draft'); ?> /> Save as 'Draft'</label></li>
				<li><label><input type="radio"
						name="publishthis_publish_action[content_status]"
						id="publishthis-content-status-field" value="publish"
						<?php checked($content_status, 'publish'); ?> /> Publish
						immediatly</label></li>
			</ul>
		</td>
	</tr>
	<tr>
		<td class="label"><label for="publishthis-featured-image-field"><?php _e('Featured Image', 'publishthis'); ?></label>
		</td>
		<td>
			<ul class="checkbox_list checkbox">
				<li><input type="hidden"
					name="publishthis_publish_action[featured_image]" value="0" /> <label><input
						type="checkbox" name="publishthis_publish_action[featured_image]"
						id="publishthis-featured-image-field" value="1"
						<?php checked($featured_image, '1'); ?> /> Download and save
						content image as the "Featured Image"</label></li>
			</ul>
		</td>
	</tr>

	<tr>
		<td class="label"><label for="publishthis-max-image-width-field"><?php _e('Maximum Image Width', 'publishthis'); ?></label>
		</td>
		<td><input type="text"
			name="publishthis_publish_action[max_image_width]"
			id="publishthis-max-image-width-field"
			value="<?php echo $max_image_width; ?>" size="5" maxlength="4" /> Set
			the maximum width for your content's image</label></td>
	</tr>

	<tr>
		<td class="label"><label for="publishthis-ok-resize-preview-field"><?php _e('Ok to Resize Preview Images', 'publishthis'); ?></label>
		</td>
		<td>
			<ul class="checkbox_list checkbox">
				<li><label><input type="checkbox"
						name="publishthis_publish_action[ok_resize_preview]"
						id="publishthis-ok-resize-preview-field" value="0"
						<?php checked($ok_resize_preview, '0'); ?> /> If you have a
						Preview Image for the content, should it be allowed to be resized
						to Max Width?</label></li>
			</ul>
		</td>
	</tr>

	<tr>
		<td class="label"><label for="publishthis-image-alignment-field"><?php _e('Image Alignment', 'publishthis'); ?></label>
		</td>
		<td>
			<select id="publishthis-image-alignment-field" name="publishthis_publish_action[image_alignment]">
				<option value="0" <?php selected($image_alignment, '0'); ?>>None</option>
				<option value="1" <?php selected($image_alignment, '1'); ?>>Center</option>
				<option value="2" <?php selected($image_alignment, '2'); ?>>Left</option>
				<option value="3" <?php selected($image_alignment, '3'); ?>>Right</option>
			</select>
		</td>
	</tr>


	<tr>
		<td class="label"><label for="publishthis-annotation-placement-field"><?php _e('Annotation Placement', 'publishthis'); ?></label>
		</td>
		<td>
			<select id="publishthis-annotation-placement-field" name="publishthis_publish_action[annotation_placement]">
				<option value="0" <?php selected($annotation_placement, '0'); ?>>Above the Content</option>
				<option value="1" <?php selected($annotation_placement, '1'); ?>>Below the Content</option>
			</select>
		</td>
	</tr>

	<tr>
		<td class="label"><label for="publishthis-read-more-field"><?php _e('Read More Label', 'publishthis'); ?></label>
		</td>
		<td><input type="text" name="publishthis_publish_action[read_more]"
			id="publishthis-read-more-field" value="<?php echo $read_more; ?>" />
			Set the text to display for articles that click out to external sites</label>
		</td>
	</tr>

	<tr>
		<td class="label"><label for="publishthis-category-field"><?php _e('Category', 'publishthis'); ?></label>
		</td>
		<td><select id="publishthis-category-field"
			name="publishthis_publish_action[category]"
			data-current="<?php echo esc_attr($category); ?>">
				<option value="0">Do Not Categorize</option>
		</select></td>
	</tr>
	<tr>
		<td class="label"><label for="publishthis-synchronize-field"><?php _e('Synchronize', 'publishthis'); ?></label>
		</td>
		<td>
			<ul class="checkbox_list checkbox">
				<li><input type="hidden"
					name="publishthis_publish_action[synchronize]" value="0" /> <label><input
						type="checkbox" name="publishthis_publish_action[synchronize]"
						id="publishthis-synchronize-field" value="1"
						<?php checked($synchronize, '1'); ?> /> PublishThis should
						override edits i make in Wordpress</label></li>
			</ul>
		</td>
	</tr>
</table>
