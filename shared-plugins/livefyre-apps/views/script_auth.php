<script type="text/javascript">
    var livefyre_auth_loaded = false;
</script>
<?php if (get_option('livefyre_apps-package_type') === 'community'): ?>
    <script type="text/javascript">
        var load_livefyre_auth = function() {
            if(!livefyre_auth_loaded) {
                Livefyre.require(['auth'], function(auth) {
                    auth.delegate(auth.createDelegate('http://livefyre.com'));
                });
            }
            livefyre_auth_loaded = true;
        };
    </script>
<?php elseif (get_option('livefyre_apps-auth_type') === 'wordpress'): ?>
    <script>
        var load_livefyre_auth = function() {
            if(!livefyre_auth_loaded) {        
                Livefyre.require(['auth'], function(auth) {
                    auth.delegate({
                        //Called when "sign in" on the widget is clicked. Should sign in to WP
                        login: function(cb) {
                            href = "<?php echo wp_login_url( get_permalink() ); ?>";
                            window.location = href;
                        },
                        //Called when "sign out" on the widget is clicked. Should sign out of WP
                        logout: function(cb) {
                            cb(null);
                            href = "<?php echo urldecode(html_entity_decode(wp_logout_url(site_url()))); ?>";
                            window.location = href;
                        },
                        viewProfile: function() {
                            href = "<?php echo admin_url('profile.php'); ?>";
                            window.location = href;
                        },
                        editProfile: function() {
                            href = "<?php echo admin_url('profile.php'); ?>";
                            window.location = href;
                        }
                    });

            <?php if (is_user_logged_in()): ?>
                        auth.authenticate({livefyre: "<?php echo esc_js(Livefyre_Apps::generate_wp_user_token()); ?>"});
            <?php endif; ?>
                    window.authDelegate = auth.delegate;
                });
            }
            livefyre_auth_loaded = true;
        };
    </script>
<?php elseif(get_option('livefyre_apps-auth_type') === 'auth_delegate'): ?>
    <script type="text/javascript">
        var load_livefyre_auth = function() {
            if(!livefyre_auth_loaded) {         
                Livefyre.require(['auth'], function(auth) {
                    auth.delegate(<?php echo esc_js(get_option('livefyre_apps-livefyre_auth_delegate_name')); ?>);
                });
            }
            livefyre_auth_loaded = true;
        };
    </script>    
<?php else: ?>
    <script type="text/javascript">
        var load_livefyre_auth = function() {};
    </script>
<?php endif; ?>
