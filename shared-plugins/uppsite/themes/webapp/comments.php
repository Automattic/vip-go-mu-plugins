<?php
global $this_comments;
$this_comments = array();
$comments = uppsite_comments_get( get_the_ID() );
if ($comments) {
	foreach ($comments as $comment) {
        $GLOBALS['comment'] = $comment;
		$this_comments[] = uppsite_get_comment();
	}
}