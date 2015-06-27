<div class="wrap">
    <h2>Parse.ly - Settings <span id="wp-parsely_version">Version <?php echo esc_html($this::VERSION); ?></span></h2>
    <form name="parsely" method="post" action="options.php">
        <?php settings_fields($this::OPTIONS_KEY); ?>
        <?php do_settings_sections($this::OPTIONS_KEY); ?>
        <p class="submit">
            <input name="submit" type="submit" class="button-primary"
             value="<?php esc_attr_e('Save Changes'); ?>" />
        </p>
    </form>
</div>