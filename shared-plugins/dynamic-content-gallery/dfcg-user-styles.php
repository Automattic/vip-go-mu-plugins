<?php
/* This file is part of the DYNAMIC CONTENT GALLERY Plugin Version 2.2
**********************************************************************
Copyright 2008  Ade WALKER  (email : info@studiograsshopper.ch)

/* Load user defined styles into the header */

/* Load options */
$options = get_option('dfcg_plugin_settings');

/* Print styles */
?>
<style type="text/css">
.imageElement {
visibility: hidden;
}
#myGallery, #myGallerySet, #flickrGallery
{
	width: <?php echo $options['gallery-width']; ?>px;
	height: <?php echo $options['gallery-height']; ?>px;
	border: <?php echo $options['gallery-border-thick']; ?>px solid <?php echo $options['gallery-border-colour']; ?>;
	background: #000 url('<?php echo DFCG_URL; ?>/css/img/loading-bar-black.gif') no-repeat center;
}
.jdGallery .slideInfoZone
{
	height: <?php echo $options['slide-height']; ?>px;
}
.jdGallery .slideInfoZone h2
{
	font-size: <?php echo $options['slide-h2-size']; ?>px !important;
	margin: <?php echo $options['slide-h2-margtb']; ?>px <?php echo $options['slide-h2-marglr']; ?>px !important;
	color: <?php echo $options['slide-h2-colour']; ?> !important;
	}

.jdGallery .slideInfoZone p
{
	padding: 0;
	font-size: <?php echo $options['slide-p-size']; ?>px !important;
	margin: <?php echo $options['slide-p-margtb']; ?>px <?php echo $options['slide-p-marglr']; ?>px !important;
	color: <?php echo $options['slide-p-colour']; ?> !important;
}
</style>
