<?php
/**
 * Parse.ly repeated meta elements output
 *
 * @package      Parsely\wp-parsely
 * @author       Parse.ly
 * @copyright    2012 Parse.ly
 * @license      GPL-2.0-or-later
 */

?>
<meta name="parsely-title" content="<?php echo esc_attr( $parsely_page['headline'] ); ?>" />
<meta name="parsely-link" content="<?php echo esc_attr( $parsely_page['url'] ); ?>" />
<meta name="parsely-type" content="<?php echo esc_attr( $parsely_post_type ); ?>" />
<meta name="parsely-image-url" content="<?php echo esc_attr( $parsely_page['thumbnailUrl'] ); ?>" />
<meta name="parsely-pub-date" content="<?php echo esc_attr( $parsely_page['datePublished'] ); ?>" />
<meta name="parsely-section" content="<?php echo esc_attr( $parsely_page['articleSection'] ); ?>" />
<meta name="parsely-tags" content="<?php echo esc_attr( $parsely_page['keywords'] ); ?>" />
<?php foreach ( (array) $parsely_page['author'] as $parsely_author ) { ?>
<meta name="parsely-author" content="<?php echo esc_attr( $parsely_author['name'] ); ?>" />
	<?php
}
