<?php

class Publishthis_Utils {

	/**
	 * Publishthis constructor.
	 */
	function __construct() {
	}

	/**
	 *
	 *
	 * @desc Format time to user-friendly look&feel
	 * @param unknown $utcDateTime Initial time
	 */
	function getElapsedPrettyTime( $utcDateTime ) {
		$str = "";

		$currentTime = time();

		$timestamp = strtotime( $utcDateTime );

		$timeDiff = $currentTime - $timestamp;

		$secondsInMinute = 60;
		$secondsInHour = 60 * 60;
		$secondsInDay = 60 * 60 * 24;
		$secondsInWeek = 60 * 60 * 24 * 7;

		if ( $timeDiff > $secondsInWeek ) {
			$weeks = floor( $timeDiff / $secondsInWeek );
			if ( $weeks == 1 ) {
				$str .= "one week ago";
			} else {
				$str .= "{$weeks} weeks ago";
			}
		} else if ( $timeDiff > $secondsInDay ) {
				$days = floor( $timeDiff / $secondsInDay );
				if ( $days == 1 ) {
					$str .= "one day ago";
				} else {
					$str .= "{$days} days ago";
				}
			} else if ( $timeDiff >= $secondsInHour ) {
				$hrs = floor( $timeDiff / $secondsInHour );
				if ( $hrs == 1 ) {
					$str .= "one hour ago";
				} else {
					$str .= "{$hrs} hours ago";
				}
			} else {
			$mins = floor( $timeDiff / $secondsInMinute );
			if ( $mins <= 5 ) {
				$str .= "a few minutes ago";
			} else {
				$str .= "{$mins} minutes ago";
			}
		}

		return $str;
	}

	/**
	 *
	 *
	 * @param unknown $originalUrl       Original image url
	 * @param unknown $intMaxWidth       Output image max size
	 * @param unknown $okToResizePreview Flag that shows should we resize image or not
	 * @return Url for resized image
	 */
	function getResizedPhotoUrl( $originalUrl, $intMaxWidth, $okToResizePreview ) {
		/*
		 * This will look at our url, and see if we can go beyond our max width
		 * or not. 1 - if we are a preview image, see if the option is set to
		 * allow preview images to be resized 2 - if the max width is less than
		 * our thumbnail size, then no changes need to be done, just pass in the
		 * width argument 3 - if max width is set, then use our small-xlarge
		 * markers to see if we can resize or not
		 */

		// first see if this url is from publishthis
		$isPTImage = strrpos( $originalUrl, "publishthis.com" );
		if ( ! $isPTImage ) {
			return $originalUrl;
		}

		$isPTPreviewImage = strrpos( $originalUrl, "_preview_" );

		// now we need to check if we can resize the preview image
		if ( ! $okToResizePreview && $isPTPreviewImage ) {
			return $originalUrl;
		}

		/**
		 * thumbnail is 120x90
		 *
		 * xsmall - 100 or less
		 * small - 100-199
		 * medium - 200-299
		 * large - 300-399
		 * xlarge - larger
		 */

		if ( $intMaxWidth == 120 ) {
			return $originalUrl;
		}

		// if it is smaller than our thumbnail, doesn't really matter
		// just return the resized image
		if ( $intMaxWidth < 120 ) {
			return $originalUrl . "?W=" . $intMaxWidth;
		}

		$isXSmall = strrpos( $originalUrl, "_xsmall_" );
		$isSmall = strrpos( $originalUrl, "_small_" );
		$isMedium = strrpos( $originalUrl, "_medium_" );
		$isLarge = strrpos( $originalUrl, "_large_" );
		$isXLarge = strrpos( $originalUrl, "_xlarge_" );

		if ( $isXSmall && ( $intMaxWidth >= 100 ) ) {
			return $originalUrl;
		}

		if ( $isSmall && ( $intMaxWidth >= 200 ) ) {
			// return it as big as we can
			return $originalUrl . "?W=200";
		}

		if ( $isMedium && ( $intMaxWidth >= 300 ) ) {
			// return it as big as we can
			return $originalUrl . "?W=300";
		}

		if ( $isLarge && ( $intMaxWidth >= 400 ) ) {
			// return it as big as we can
			return $originalUrl . "?W=400";
		}

		if ( $isXLarge && ( $intMaxWidth >= 800 ) ) {
			// return it as big as we can
			return $originalUrl . "?W=800";
		}

		// ok, we have now checked max sizes beyond the scope of the image url
		return $originalUrl . "?W=" . $intMaxWidth;

	}

	/**
	 *
	 *
	 * @desc Set the image alignment
	 * @param unknown $strAlignmentValue Possible values: 0 - none, 1 - center, 2 - left, 3 - right
	 * @return string Image alignment
	 */
	function getImageAlignmentClass( $strAlignmentValue ) {
		$align = "";

		switch ( $strAlignmentValue ) {
		case "1": //align to center
			$align = " pt-align-center";
			break;

		case "2": //align to the left
			$align = " alignleft";
			break;

		case "3": //align to the right
			$align = " alignright";
			break;

		default: break;
		}
		return $align;
	}
}
