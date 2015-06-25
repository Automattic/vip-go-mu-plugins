<?php if ( current_user_can( 'manage_options' ) ) { ?>
    <script type='text/javascript'>
        var tm_cos_token = "<?php echo esc_js( get_option('tm_coschedule_token') ); ?>";
    </script>
    <!--[if IE 9]>
      <style>
        .coschedule .ie-only {
            display:block !important;
        }
        .coschedule .not-ie {
            display: none !important;
        }
      </style>
    <![endif]-->

    <div class="coschedule aj-window-height">
        <div class="cos-welcome-wrapper calendar-bg container-fluid">
            <div class="row-fluid">
                <div class="span8 offset2">
                    <div class="cos-plugin-wrapper">
                        <div class="cos-plugin-inner">
                                <div class="cos-plugin-header text-center">
                                    <h3 class="aj-header-text white marg-none marg-b">Your CoSchedule Editorial Calendar Is Waiting</h3>
                                    <div class="cos-plugin-sales-pitch">
                                        <ul class="text-left">
                                            <li>&bull; Drag-And-Drop Editorial Calendar</li>
                                            <li>&bull; Schedule Social Media While You Blog</li>
                                            <li>&bull; All-In-One Blog &amp; Social Media Publishing</li>
                                            <li>&bull; Communicate With Your Team</li>
                                            <li>&bull; 14 Day Free Trial, Cancel Anytime</li>
                                        </ul>
                                    </div>
                                </div>

                                <div class="cos-plugin-body">
                                    <!-- Form chooser -->
                                    <div id="tm_form_mode">
                                        <a href="#" class="tm_form_mode_register btn btn-blue btn-jumbo btn-block brandon-regular">
                                            <span style="font-size: 20px; line-height: 24px;">Create A New CoSchedule Account &amp; Get Started</span></br>
                                            <span class="small blue1">No Credit Card Required, Cancel Anytime</span>
                                        </a>

                                        <a href="#" class="btn btn-block btn-large brandon-regular tm_form_mode_login">
                                            Sign In With An Existing CoSchedule Account
                                        </a>

                                        <div id="tm_form_mode_login" class="text-center marg-t pad-t" style="margin-bottom: -20px;">
                                            <a href="http://coschedule.com/training-articles/working-with-multiple-blogs" class="grey">Tip: How to use CoSchedule with multiple blogs.</a>
                                        </div>
                                    </div>

                                    <!-- Login Progress -->
                                    <div id="tm_coschedule_alert" class="alert" style="display:none; margin: 0px 0px 10px 0px"></div>

                                    <!-- Calendar Setup Area -->
                                    <div id="tm_form_calendar_setup" style="display: none;">
                                        <div class="cos-installer-loading">
                                            Loading your calendar....
                                        </div>
                                    </div>

                                    <!-- Connection Progress -->
                                    <div id="tm_connection_progress" style="display: none;">
                                        <div class="cos-installer-loading">
                                            <span id="tm_connection_msg"></span>
                                        </div>
                                    </div>

                                    <div id="tm_connection_body" style="display: none;">
                                        <div id="tm_connection_msg" class="alert" style="display: none; margin: 0px 0px 10px 0px"></div>
                                    </div>

                                    <!-- Login form -->
                                    <div id="tm_form_login" style="display: none;">
                                        <label class="form-label ie-only text-left">Email Address</label>
                                        <input class="input-jumbo input-block-level" type="text" name="tm_coschedule_email" id="tm_coschedule_email" placeholder="Email Address"><br>
                                        <label class="form-label ie-only text-left">Password</label>
                                        <input class="input-jumbo input-block-level" type="password" name="tm_coschedule_password" id="tm_coschedule_password" placeholder="Password"><br>
                                        <button type="submit" class="btn btn-blue btn-jumbo btn-block default" id="tm_activate_button">Sign In</button>
                                        <div class="text-center grey brandon-regular marg-t pad-t" style="margin-bottom: -20px;">
                                            Don't have a CoSchedule account yet? <a href="#" class="tm_form_mode_register">Register now</a>.
                                        </div>
                                    </div>
                                    <!-- Registration form -->
                                    <div id="tm_form_register" style="display: none;">
                                        <label class="form-label ie-only text-left">Full Name</label>
                                        <div class="form-group">
                                            <input class="input-jumbo input-block-level" type="text" name="tm_coschedule_name" id="tm_coschedule_name_register" placeholder="Full Name">
                                            <div class="flag">
                                                You'll be up and running <br/>in <strong>30 seconds</strong>!
                                            </div>
                                        </div>
                                        <label class="form-label ie-only text-left">Email Address</label>
                                        <div class="form-group">
                                            <input class="input-jumbo input-block-level" type="text" name="tm_coschedule_email" id="tm_coschedule_email_register" placeholder="Email Address">
                                            <div class="flag">
                                                Now you got it <br/> keep going!
                                            </div>
                                        </div>
                                        <label class="form-label ie-only text-left">Password</label>
                                        <div class="form-group">
                                            <input class="input-jumbo input-block-level" type="password" name="tm_coschedule_password" id="tm_coschedule_password_register" placeholder="Password"><br>
                                            <div class="flag">
                                                Almost ready <br/> don't stop now!
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-blue btn-jumbo btn-block default" id="tm_activate_button_register">Start your 14 day free trial <span aria-hidden="true" class="icon-arrow"></span></button>
                                        <div class="text-center grey brandon-regular marg-t pad-t" style="margin-bottom: -20px;">
                                            Already have a CoSchedule account? <a href="#" class="tm_form_mode_login">Sign in now</a>.
                                        </div>
                                    </div>
                                    <input type="hidden" id="" value="">
                                </div>
                                <div class="tm_footer_logos cos-plugin-footer text-center">
                                    <div class="customer-logos">
                                        <img src="<?php echo esc_url( 'http://direct.coschedule.com/img/app-tmp/customer-logos-color.png' ); ?>">
                                        CoSchedule is trusted by WordPress bloggers and content marketers around the world.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <script>
        jQuery(document).ready(function($) {
            $('.update-nag').remove();
            $('#wpfooter').remove();
            $('#wpwrap #footer').remove();
            $('#wpbody-content').css('paddingBottom', 0);
            $('#CoSiFrame').css('min-height',$('#wpbody').height());
            var resize = function() {
                var p =  $(window).height() - $('#wpadminbar').height() - 4;
                $('#CoSiFrame').height(p);
            }

            resize();
            $(window).resize(function() {
                resize();
            });

            // add enter key trigger submit action //
            $('input[type=text],input[type=password]').keypress(function (e) {
                var result = true;
                if ((e.which && e.which == 13) || (e.keyCode && e.keyCode == 13)) {
                    $(e.target).parents('#tm_form_login, #tm_form_register').find('button[type=submit].default').click();
                    result = false;
                }
                return result;
            });
        });
    </script>
<?php
} else {
    include( plugin_dir_path( __FILE__ ) . '_access-denied.php' );
}