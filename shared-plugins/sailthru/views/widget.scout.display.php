<?php

    /*
     * If Scout is not on, advise the user
     */
    $scout = get_option( 'sailthru_scout_options' );

    if( ! isset( $scout['sailthru_scout_is_on'] ) ||  ! $scout['sailthru_scout_is_on'] ) {

        // do nothing, get outta here
        return;

    }

    /*
     * Grab the settings from $instance and fill out default
     * values as needed.
     */
	$title = empty( $instance['title'] ) ? ' ' : apply_filters( 'widget_title', $instance['title'] );

?>
 <div class="sailthru-recommends-widget">

    <?php
        // title
        if ( ! empty( $title ) ) {
            if ( ! isset( $before_title ) ) {
                $before_title = '';
            }
            if ( ! isset( $after_title ) ) {
                $after_title = '';
            }
            echo $before_title . trim( $title ) . $after_title;
        }
    ?>

	<div id="sailthru-scout"><div class="loading">Loading, please wait...</div></div>

</div>
