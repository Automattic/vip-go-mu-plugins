<?php
global $pt_content;
global $publishthis;

$date_format = sprintf( '%s %s', get_option ( 'date_format' ), get_option ( 'time_format' ) );
?>
<ul>
	<?php
foreach ( $pt_content ['result'] as $result ) :
	$alt = isset ( $result->title ) ? $result->title : 'PublishThis image';
?>
		<li
		class="pt-content pt-post-widget pt-post-<?php echo esc_attr( $result->contentType ); ?>">
			<?php if ( isset( $result->title ) ) : ?>
			<h5 class="pt-title"><?php echo esc_html( $result->title ); ?></h5>
			<?php endif; ?>

			<?php if ( isset( $result->embed ) ) : ?>
			<p class="pt-embed"><?php echo $result->embed; ?></p>
			<?php endif; ?>

			<?php if ( isset( $result->imageUrl ) && $pt_content['show_photos'] ) : ?>
			<p class="pt-image">
			<img src="<?php echo esc_url( $result->imageUrl ); ?>"
				alt="<?php echo esc_attr( $alt ); ?>" />
		</p>
			<?php endif; ?>

			<?php if ( isset( $result->summary ) && $pt_content['show_summary'] ) : ?>
			<p class="pt-summary"><?php echo $result->summary; ?></p>
			<?php endif; ?>

			<?php if ( isset( $result->text ) && $pt_content['show_summary'] ) : ?>
			<p class="pt-summary"><?php echo $result->text; ?></p>
			<?php endif; ?>

			<p class="pt-meta entry-meta">
				<?php
if ( isset ( $result->publisher ) && $pt_content ['show_source'] )
	printf( "<span class=\"pt-publisher\">Publisher - %s</span>\n", esc_html ( $result->publisher ) );
if ( isset ( $result->url ) && $pt_content ['show_links'] )
	printf( "<span class=\"pt-url\">URL - <a href=\"%s\">%s</a></span>\n", esc_url ( $result->url ), esc_html ( $result->url ) );
if ( isset ( $result->photoCredit ) )
	printf( "<span class=\"pt-photo-credit\">Photo Credit - %s</span>\n", esc_html ( $result->photoCredit ) );
if ( isset ( $result->socialcount ) )
	printf( "<span class=\"pt-social-count\">Social Count - %s</span>\n", esc_html ( $result->socialcount ) );
if ( isset ( $result->publishDate ) )
	printf( "<span class=\"pt-published-date\">Published Date - %s</span>\n", date( $date_format, strtotime( $result->publishDate ) ) );
if ( isset ( $result->curatedDate ) )
	printf( "<span class=\"pt-curated-date\">Curated Date - %s</span>\n", date( $date_format, strtotime( $result->curatedDate ) ) );
?>
			</p>
	</li>
	<?php endforeach; ?>
</ul>
