<?php
/**
 * Various helper functions
 */

/**
 * Get the common MIME-types for extensions
 * @return array
 */
function fu_get_mime_types() {
	// Generated with dyn_php class: http://www.phpclasses.org/package/2923-PHP-Generate-PHP-code-programmatically.html
	$mimes_exts = array(
		'doc'=>
		array(
			'label'=> 'Microsoft Word Document',
			'mimes'=>
			array(
				'application/msword',
			),
		),
		'docx'=>
		array(
			'label'=> 'Microsoft Word Open XML Document',
			'mimes'=>
			array(
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			),
		),
		'xls'=>
		array(
			'label'=> 'Excel Spreadsheet',
			'mimes'=>
			array(
				'application/vnd.ms-excel',
				'application/msexcel',
				'application/x-msexcel',
				'application/x-ms-excel',
				'application/vnd.ms-excel',
				'application/x-excel',
				'application/x-dos_ms_excel',
				'application/xls',
			),
		),
		'xlsx'=>
		array(
			'label'=> 'Microsoft Excel Open XML Spreadsheet',
			'mimes'=>
			array(
				'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			),
		),
		'pdf'=>
		array(
			'label'=> 'Portable Document Format File',
			'mimes'=>
			array(
				'application/pdf',
				'application/x-pdf',
				'application/acrobat',
				'applications/vnd.pdf',
				'text/pdf',
				'text/x-pdf',
			),
		),
		'psd'=>
		array(
			'label'=> 'Adobe Photoshop Document',
			'mimes'=>
			array(
				'image/photoshop',
				'image/x-photoshop',
				'image/psd',
				'application/photoshop',
				 'application/psd',
				'zz-application/zz-winassoc-psd',
				'image/vnd.adobe.photoshop',
			),
		),
		'csv'=>
		array(
			'label'=> 'Comma Separated Values File',
			'mimes'=>
			array(
				'text/comma-separated-values',
				'text/csv',
				'application/csv',
				'application/excel',
				'application/vnd.ms-excel',
				'application/vnd.msexcel',
				'text/anytext',
			),
		),
		'ppt'=>
		array(
			'label'=> 'PowerPoint Presentation',
			'mimes'=>
			array(
				'application/vnd.ms-powerpoint',
				'application/mspowerpoint',
				'application/ms-powerpoint',
				'application/mspowerpnt',
				'application/vnd-mspowerpoint',
			),
		),
		'pptx'=>
		array(
			'label'=> 'PowerPoint Open XML Presentation',
			'mimes'=>
			array(
				'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			),
		),
		'mp3'=>
		array(
			'label'=> 'MP3 Audio File',
			'mimes'=>
			array(
				'audio/mpeg',
				'audio/x-mpeg',
				'audio/mp3',
				'audio/x-mp3',
				'audio/mpeg3',
				'audio/x-mpeg3',
				'audio/mpg',
				'audio/x-mpg',
				'audio/x-mpegaudio',
			),
		),
		'avi'=>
		array(
			'label'=> 'Audio Video Interleave File',
			'mimes'=>
			array(
				'video/avi',
				'video/msvideo',
				'video/x-msvideo',
				'image/avi',
				'video/xmpg2',
				'application/x-troff-msvideo',
				'audio/aiff',
				'audio/avi',
			),
		),
		'mp4'=>
		array(
			'label'=> 'MPEG-4 Video File',
			'mimes'=>
			array(
				'video/mp4v-es',
				'audio/mp4',
				'application/mp4',
			),
		),
		'm4a'=> array(
			'label'=> 'MPEG-4 Audio File',
			'mimes'=> array(
				'audio/aac', 'audio/aacp', 'audio/3gpp', 'audio/3gpp2', 'audio/mp4', 'audio/MP4A-LATM','audio/mpeg4-generic', 'audio/x-m4a', 'audio/m4a'
			) ),
		'mov'=>
		array(
			'label'=> 'Apple QuickTime Movie',
			'mimes'=>
			array(
				'video/quicktime',
				'video/x-quicktime',
				'image/mov',
				'audio/aiff',
				'audio/x-midi',
				'audio/x-wav',
				'video/avi',
			),
		),
		'mpg'=>
		array(
			'label'=> 'MPEG Video File',
			'mimes'=>
			array(
				'video/mpeg',
				'video/mpg',
				'video/x-mpg',
				'video/mpeg2',
				'application/x-pn-mpg',
				'video/x-mpeg',
				'video/x-mpeg2a',
				'audio/mpeg',
				'audio/x-mpeg',
				'image/mpg',
			),
		),
		'mid'=>
		array(
			'label'=> 'MIDI File',
			'mimes'=>
			array(
				'audio/mid',
				'audio/m',
				'audio/midi',
				'audio/x-midi',
				'application/x-midi',
				'audio/soundtrack',
			),
		),
		'wav'=>
		array(
			'label'=> 'WAVE Audio File',
			'mimes'=>
			array(
				'audio/wav',
				'audio/x-wav',
				'audio/wave',
				'audio/x-pn-wav',
			),
		),
		'wma'=>
		array(
			'label'=> 'Windows Media Audio File',
			'mimes'=>
			array(
				'audio/x-ms-wma',
				'video/x-ms-asf',
			),
		),
		'wmv'=>
		array(
			'label'=> 'Windows Media Video File',
			'mimes'=>
			array(
				'video/x-ms-wmv',
			),
		),
	);

	return $mimes_exts;
}

/**
 * Generate slug => description array for Frontend Uploader settings
 * @return array
 */
function fu_get_exts_descs() {
	$mimes = fu_get_mime_types();
	$a = array();

	foreach( $mimes as $ext => $mime )
		$a[$ext] = sprintf( '%1$s (.%2$s)', $mime['label'], $ext );

	return $a;
}