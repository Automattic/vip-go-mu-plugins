<?php
global $publishthis;
global $pt_content;
global $pt_content_features;

// work with the section content
// need to know the type, because each type has slightly different
// display needs

$varHadTweets = false;
$strImageUrl = null;

if ( isset ( $pt_content->imageUrl ) ) {
	$strImageUrl = $publishthis->utils->getResizedPhotoUrl ( $pt_content->imageUrl, $pt_content_features ['max_image_width'], $pt_content_features ['ok_resize_previews'] );
}

$sectionContent = "<div class=\"pt-content pt-post-combined pt-post-" . esc_attr ( $pt_content->contentType ) . "\">";

if ( isset ( $pt_content->url ) && ( ! empty ( $pt_content->url ) ) ) {
	$sectionContent = $sectionContent . "<h3><a href=\"" . $pt_content->url . "\" target=\"_blank\" rel=\"nofollow\">" . $pt_content->title . "</a></h3>";
} else {
	$sectionContent = $sectionContent . "<h3>" . $pt_content->title . "</h3>";
}

if ( $pt_content_features ['annotation_placement'] == '0' ) {
	if ( isset ( $pt_content->annotations ) ) {

		if ( count( $pt_content->annotations ) > 0 ) {
			$sectionContent = $sectionContent . "<div class=\"pt-annotate\">" . $pt_content->annotations [0]->annotation . "</div>";
		}
	}
}

if ( $pt_content->contentType == 'video' ) {

	if ( isset ( $pt_content->embed ) && ! empty ( $pt_content->embed ) ) {
		$sectionContent = $sectionContent . "<div class=\"pt-embed\">[ptraw]" . esc_html ( $pt_content->embed ) . "[/ptraw]</div>";
	} else {
		$sectionContent = $sectionContent . "<div class=\"pt-image" . $publishthis->utils->getImageAlignmentClass ( $pt_content_features ['image_alignment'] ) . "\"><img src=\"" . $strImageUrl . "\"/></div>";
	}
	$sectionContent = $sectionContent . "<p class=\"pt-summary\">" . $pt_content->summary . "</p>";

	if ( ! empty ( $pt_content_features ["read_more"] ) ) {
		$sectionContent = $sectionContent . "<div class\"pt-readmore\"><a href=\"" . $pt_content->url . "\" target=\"_blank\" rel=\"nofollow\">" . $pt_content_features ["read_more"] . "</a></div>";
	}

} else if ( $pt_content->contentType == 'tweet' ) {

		$varHadTweets = true;

		// <blockquote class="twitter-tweet"><p>Search API will now always return
		// "real" Twitter user IDs. The with_twitter_user_id parameter is no longer
		// necessary. An era has ended. ^TS</p>&mdash; Twitter API (@twitterapi) <a
		// href="https://twitter.com/twitterapi/status/133640144317198338"
		// data-datetime="2011-11-07T20:21:07+00:00">November 7,
		// 2011</a></blockquote>
		// <script src="//platform.twitter.com/widgets.js" charset="utf-8"></script>

		$sectionContent = $sectionContent . "<blockquote class=\"twitter-tweet\"><p>" . $pt_content->statusText . "</p>";
		$sectionContent = $sectionContent . "&mdash; Twitter  (@" . $pt_content->userScreenName . ") <a href=\"" . $pt_content->statusUrl . "\" data-datetime=\"" . $pt_content->publishDate . "\">" . $pt_content->publishDate . "</a></blockquote>";

	} else if ( $pt_content->contentType == 'photo' ) {

		$sectionContent = $sectionContent . "<div class=\"pt-image\"><a href=\"" . $strImageUrl . "\" target=\"_blank\" rel=\"nofollow\"><img src=\"" . $pt_content->imageUrl . "\"/></a></div>";

		if ( isset ( $pt_content->photoCredit ) ) {
			$sectionContent = $sectionContent . "<div class=\"pt-credit\">credit:<strong>" . $pt_content->photoCredit . "</strong></div>";
		}

		if ( isset ( $pt_content->summary ) ) {
			$sectionContent = $sectionContent . "<p class=\"pt-summary\">" . $pt_content->summary . "</p>";
		}

	} else if ( $pt_content->contentType == 'text' ) {

		$sectionContent = $sectionContent . "<p class=\"pt-text\"><!--text start-->" . balanceTags ( $pt_content->text, true ) . "<!-- text end--></p>";
	} else {
	// do the default display. assume that it is an article, but could also just
	// be an unknown
	// content type

	$sectionContent = $sectionContent . "<!-- default view -->";

	if ( isset ( $strImageUrl ) ) {
		$sectionContent = $sectionContent . "<div class=\"pt-image" . $publishthis->utils->getImageAlignmentClass ( $pt_content_features ['image_alignment'] ) . "\"><img src=\"" . $strImageUrl . "\"/></div>";
	}

	if ( isset ( $pt_content->summary ) ) {
		$sectionContent = $sectionContent . "<p class=\"pt-summary\">" . $pt_content->summary . "</p>";
	}

	if ( isset ( $pt_content->url ) ) {
		if ( ! empty ( $pt_content_features ["read_more"] ) ) {
			$sectionContent = $sectionContent . "<div class\"pt-readmore\"><a href=\"" . $pt_content->url . "\" target=\"_blank\" rel=\"nofollow\">" . $pt_content_features ["read_more"] . "</a></div>";
		}

	}
}

if ( $pt_content_features ['annotation_placement'] == '1' ) {
	if ( isset ( $pt_content->annotations ) ) {

		if ( count( $pt_content->annotations ) > 0 ) {
			$sectionContent = $sectionContent . "<div class=\"pt-annotate\">" . $pt_content->annotations [0]->annotation . "</div>";
		}
	}
}

$sectionContent = $sectionContent . "<div style=\"clear:both;\"></div>";
$sectionContent = $sectionContent . "</div>"; // end of the pt-content div

if ( $varHadTweets ) {
	$sectionContent = "[ptraw]" . esc_html ( "<script src=\"http://platform.twitter.com/widgets.js\" charset=\"utf-8\"></script>" ) . "[/ptraw]" . $sectionContent;
}

echo $sectionContent;

?>
