<div class='lfapps-initial-modal'>
    <h3><?php esc_html_e('Welcome to Livefyre Apps', 'lfapps'); ?></h3>
    <p><?php esc_html_e('Select the Livefyre Environment which is applicable to you:', 'lfapps'); ?></p>
    <div class='lfapps-env-select-row clearfix'>
        <div class='lfapps-env-box lfapps-community'>            
            <label for="lfapps-community-env-radio">                
                <h4><input type="radio" id="lfapps-community-env-radio" name="lfapps-env" value="community" <?php echo get_option('livefyre_apps-package_type') === 'community' ? 'checked' : ''; ?>> <?php esc_html_e('Community', 'lfapps'); ?></h4>
                <p><?php esc_html_e('Our free community comment and sidenote products that are ideal for bloggers. Learn more > ', 'lfapps'); ?> <a href='http://web.livefyre.com/'>Comments</a></br></p>
            </label>            
        </div>
        <div class='lfapps-env-box lfapps-enterprise'>
            <label for="lfapps-enterprise-env-radio">   
                <h4><input type="radio" id="lfapps-enterprise-env-radio" name="lfapps-env" value="enterprise" <?php echo get_option('livefyre_apps-package_type') === 'enterprise' ? 'checked' : ''; ?>> <?php esc_html_e('Enterprise', 'lfapps'); ?></h4>
                <p><?php esc_html_e('For high-volume publishers and brands who want access to all Livefyre apps, enterprise support and ownership of user data. Enterprise API key required. Learn more >', 'lfapps');?> <a href='http://web.livefyre.com/streamhub/'>Streamhub</a></p>
            </label>            
        </div>
    </div>
    <div class="lfapps-env-submit">
        <input type="hidden" id="lfapps-env-url" value="<?php echo esc_url(Livefyre_Apps_Admin::get_page_url('livefyre_apps')); ?>"/>
        <a href="#" id="lfapps-env-submit-btn" class="button button-primary"><?php esc_html_e('Save Changes'); ?></a>
        <?php if(get_option('livefyre_apps-initial_modal_shown')): ?>
        <a href="#" id="lfapps-env-cancel-btn" class="button button-secondary"><?php esc_html_e('Cancel'); ?></a> 
        <?php endif; ?>
    </div>
</div>