	<div id="icon-sailthru" class="icon32"></div>
	<h2><?php _e( 'Sailthru Recommends', 'sailthru-for-wordpress' ); ?></h2>

	<?php

		$sailthru = get_option( 'sailthru_setup_options' );

		if( ! is_array( $sailthru ) ) {

			echo '<p>Please return to the <a href="' . esc_url( menu_page_url( 'sailthru_configuration_menu', false ) ) . '">Sailthru Settings screen</a> and set up your API key and secret before setting up this widget.</p>';
			return;

		}

		/*
		 * If Scout is not on, advise the user
		 */
		$scout = get_option( 'sailthru_scout_options' );

		if( ! isset( $scout['sailthru_scout_is_on'] ) ||  ! $scout['sailthru_scout_is_on'] ) {

			echo '<p>Don\'t forget to <a href="' . esc_url( menu_page_url( 'scout_configuration_menu', false ) ) . '">enable Scout</a> before setting up this widget.</p>';
			return;

		}

	?>

        <div id="<?php echo $this->get_field_id( 'title' ); ?>_div" style="display: block;">
        	<p>Use the Scout configuration page to choose your settings for this sidebar widget.</p>
            <p>
            	<label for="<?php echo $this->get_field_id( 'title' ); ?>">
            		<?php _e( 'Title:' ); ?>
            		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
            	</label>
            </p>
        </div>
