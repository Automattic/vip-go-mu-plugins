<?php
// Set content type to XML
header("Content-Type: text/xml; charset=UTF-8");

print '<?xml version="1.0" encoding="UTF-8"?>';
?>
<mysiteapp result="true" wordpress_version="<?php echo get_bloginfo('version') ?>" plugin_version="<?php echo MYSITEAPP_PLUGIN_VERSION ?>">
<?php
if (function_exists('wp_get_current_user')):
	$current_user = wp_get_current_user();
?>
	<user ID="<?php echo $current_user->ID ?>">
		<name><![CDATA[<?php echo $current_user->user_login?>]]></name>
		<logout_url><![CDATA[<?php echo mysiteapp_logout_url_wrapper() ?>]]></logout_url>
		<login_url><![CDATA[<?php echo site_url('wp-login.php') ?>]]></login_url>
	</user>
<?php
endif;