<div class="wrap" id="nc-settings-wrapper">
    <a class="nc-logo" href="http://newscred.com" target="_blank">
        <img class="" src="<?php echo NC_IMAGES_URL . "/newscred-logo.png" ?>"/>
    </a>

    <div id="icon-options-general" class="icon32"><br></div>
    <h2>Settings</h2>

    <div class="clearfix"></div>
    <div id="message" class="below-h2 nc-message">
    </div>
    <div id="nc-settings-tabs">

        <form method="post" action="options.php" class="nc-admin-form"  >
            <div id="tabs-1" class="accesskey-settings TabsContent">
                <?php
                // Output the settings sections.
                do_settings_sections( 'nc_plugin_settings' );

                // Output the hidden fields, nonce, etc.
                settings_fields( 'nc_plugin_settings_group' );
                ?>
                <div class="clearfix"></div>
                <?php
                // Submit button.
                submit_button();
                ?>
            </div>
        </form>

    </div>
</div>
