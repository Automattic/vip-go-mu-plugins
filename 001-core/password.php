<?php

add_action( 'user_profile_update_errors', 'validate_current_password' );

function validate_current_password(&$errors) {
    $new_pass = $_POST['pass1'];
    if (isset($new_pass) && $new_pass) {
        $current_pass = $_POST['current_pass'];
        $user = wp_get_current_user();
        $error = wp_authenticate($user->user_login, $current_pass);
        if (is_wp_error($error)) {
            $errors->add('current_pass', "<strong>ERROR</strong>: Current password is not correct.");
        }
    }
    // we are not changing the password
}

add_action( 'show_user_profile', 'add_current_password_field', 1);

function add_current_password_field() { ?>
    <table class="form-table" role="presentation">
        <tr id="current-password-confirm">
            <th><label for="current_pass"><?php _e('Current Password') ?></label></th>
            <td>
                <input type="password" name="current_pass" id="current_pass" class="regular-text" value="" autocomplete="off" />
                <p class="description">
                    <?php _e('Please, type your current password if you want to change it for a new one') ?>.
                </p>
            </td>
        </tr>
    </table>
<?php }