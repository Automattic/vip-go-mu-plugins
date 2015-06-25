<?php
global $pt_content;
global $publishthis;

$widgetIndex = 1;
foreach ( $pt_content ['result'] as $result ) :
	$alt = isset ( $result->title ) ? $result->title : 'PublishThis image';

$strImageUrl = null;

if ( isset ( $result->imageUrl ) ) {
	$strImageUrl = $publishthis->utils->getResizedPhotoUrl ( $result->imageUrl, $pt_content ['max_width_images'], $pt_content ['ok_resize_previews'] );
}

?>
<div
	class="pt-widget pt-widget-<?php echo esc_attr( $result->contentType ); ?> pt-widget-row-<?php echo $widgetIndex++?>">
			<?php if ( isset( $strImageUrl ) && $pt_content['show_photos'] ) { ?>
				<div class="pt-imgcontent-wrap">
		<a href="<?php echo $result->url ?>" target="_blank" rel="nofollow"
			class="pt-imgcontent-link"><img
			src="<?php echo esc_url( $strImageUrl ); ?>"
			alt="<?php echo esc_attr( $alt ); ?>" class="pt-image" /></a>
	</div>
			<?php } ?>
			<?php if ( $pt_content['show_links'] ) { ?>
				<h5>
		<a href="<?php echo $result->url ?>" target="_blank" rel="nofollow"
			class="pt-content-link"><?php echo $result->title ?></a>
	</h5>
			<?php } else { ?>
				<h5><?php echo $result->title ?></h5>
			<?php } ?>
			<?php if ( isset( $result->publishDate ) ) { ?>
				<span class="pt-content-date"><?php echo $publishthis->utils->getElapsedPrettyTime( $result->publishDate ) ?></span>
			<?php } ?>
			<?php if ( isset( $result->publisher ) && $pt_content['show_source'] ) { ?>
				<span class="pt-content-publisher"> via <strong><?php echo $result->publisher; ?></strong></span>
			<?php } ?>
			<?php if ( isset( $result->summary ) && $pt_content['show_summary'] ) { ?>
				<p class="pt-content-summary"><?php echo $result->summary; ?></p>
			<?php } ?>

		</div>
<?php
endforeach;
?>
