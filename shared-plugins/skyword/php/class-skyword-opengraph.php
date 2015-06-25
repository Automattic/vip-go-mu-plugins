<?php
class Skyword_Opengraph {
	/**
	 * Class constructor
	 */
	function __construct() {
		add_action( 'wp_head', array( $this, 'head' ), 1, 1 );
		add_filter( 'wp_title', array($this, 'title' ));
	}
	/**
	* Add meta/og tags
	*/
	public function head() {
		global $wp_query;
		$current_post = $wp_query->post;
		//Only display meta tags if on single post page
		$options = get_option('skyword_plugin_options');
		//Only provide tags on our content
		if ( null != get_post_meta( $current_post->ID, 'skyword_metadescription', true ) ) {
			if ( is_singular() ) {
				$description = get_post_meta( $current_post->ID, 'skyword_metadescription', true );

				if (null != get_post_meta( $current_post->ID, 'skyword_metatitle', true)) {
					$title = get_post_meta( $current_post->ID, 'skyword_metatitle', true);
				} else if (null != get_post_meta( $current_post->ID, 'skyword_seo_title', true)) {
					$title = get_post_meta( $current_post->ID, 'skyword_seo_title', true);
				} else {
					$current_post->post_title;
				}

				if ( $options['skyword_enable_ogtags'] ) {
					$image = $this->get_image( $current_post );
					echo '<meta property="og:title" content="'.esc_attr( $title ).'"/>';
					echo '<meta property="og:description" content="' .esc_attr( $description ).'"/>\n';
					echo '<meta property="og:url" content="' .esc_html( get_permalink( $current_post->ID ) ).'"/>\n';
					echo '<meta property="og:site_name" content="' .esc_html (get_option( 'blogname' ) ). '"/>\n';
					echo '<meta property="og:type" content="article"/>\n';
					if ( isset( $image ) ) {
						echo '<meta property="og:image" content="'.esc_attr($image).'"/>\n';
					}
				}
				if ( $options['skyword_enable_metatags'] ) {
					echo '<meta name="description" content="' .esc_attr($description).'"/>\n';
				}
				if ( $options['skyword_enable_googlenewstag'] ) {
					if ( null != get_post_meta( $current_post->ID, 'skyword_publication_keywords', true ) ) {
						echo '<meta name="news_keywords" content="' . esc_html( get_post_meta( $current_post->ID, 'skyword_publication_keywords', true ) ).'"/>\n';
					} else if ( null != get_post_meta(  $current_post->ID, 'skyword_tags', true ) ) {
						echo '<meta name="news_keywords" content="' . esc_html (get_post_meta( $current_post->ID, 'skyword_tags', true ) ).'"/>\n';
					}
				}

			}
		}
		return;

	}
	/**
	* Update title tag with seo title
	*/
	public function title() {
		global $wp_query;
		$current_post = $wp_query->post;
		//Only display meta tags if on single post page
		$options = get_option( 'skyword_plugin_options' );
		if ( is_singular() ) {
			if ( $options['skyword_enable_pagetitle'] ) {

				if ( null != get_post_meta( $current_post->ID, 'skyword_metatitle', true ) ) {
					return esc_html( get_post_meta(  $current_post->ID, 'skyword_metatitle', true ) );
				} else if ( null != get_post_meta(  $current_post->ID, 'skyword_seo_title', true ) ) {
					return esc_html ( get_post_meta( $current_post->ID, 'skyword_seo_title', true ) );
				} else {
					return esc_html ( $current_post->post_title );
				}
			}
		}
	}
	/**
	* Finds best iamge for og:image tag
	*/
	private function get_image( $current_post ) {
		//First try for featured image
		if ( function_exists( 'has_post_thumbnail' ) && has_post_thumbnail( $current_post->ID ) ) {
			$thumb = wp_get_attachment_image_src( get_post_thumbnail_id( $current_post->ID ), 'thumbnail' );
			$image = $thumb[0];
		}
		//if not found, use an attached image
		if (!isset($image)) {
			$args = array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'post_parent' => $current_post->ID, 'suppress_filters' => false );
			$images = get_posts( $args );
			foreach ( $images as $image ) {
				$thumb = wp_get_attachment_image_src( $image->ID, 'thumbnail' );
				return $thumb[0];
			}
		}

		return $image;
	}

}
global $skyword_opengraph;
$skyword_opengraph = new Skyword_Opengraph;