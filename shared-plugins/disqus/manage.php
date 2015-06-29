<?php
global $dsq_version, $dsq_api;

require(ABSPATH . 'wp-includes/version.php');

if ( !current_user_can('manage_options') ) {
	die();
}

if(isset($_POST['dsq_username'])) {
	$_POST['dsq_username'] = stripslashes($_POST['dsq_username']);
}

if(isset($_POST['dsq_password'])) {
	$_POST['dsq_password'] = stripslashes($_POST['dsq_password']);
}

// HACK: For old versions of WordPress
if ( !function_exists('wp_nonce_field') ) {
	function wp_nonce_field() {}
}

// Handle export function.
if( isset($_POST['export']) and DISQUS_CAN_EXPORT ) {
	require_once(dirname(__FILE__) . '/export.php');
	dsq_export_wp();
}

// Handle uninstallation.
if ( isset($_POST['uninstall']) ) {
	update_option('disqus_forum_url', '');
	update_option('disqus_api_key', '');
	update_option('disqus_user_api_key', '');
	update_option('disqus_partner_key', '');
}

// Clean-up POST parameters.
foreach ( array('dsq_forum', 'dsq_username', 'dsq_user_api_key') as $key ) {
	if ( isset($_POST[$key]) ) { $_POST[$key] = strip_tags($_POST[$key]); }
}

// Handle installation process.
if ( isset($_POST['dsq_forum']) && isset($_POST['dsq_user_api_key']) ) {
	list($dsq_forum_id, $dsq_forum_url) = explode(':', $_POST['dsq_forum']);
	update_option('disqus_forum_url', $dsq_forum_url);
	$api_key = $dsq_api->get_forum_api_key($_POST['dsq_user_api_key'], $dsq_forum_id);
	if ( !$api_key || $api_key < 0 ) {
		update_option('disqus_replace', 'replace');
		dsq_manage_dialog(dsq_i('There was an error completing the installation of Disqus. If you are still having issues, refer to the <a href="http://disqus.com/help/wordpress">WordPress help page</a>.'), true);
	} else {
		update_option('disqus_api_key', $api_key);
		update_option('disqus_replace', 'all');
	}
	
	if (!empty($_POST['disqus_partner_key'])) {
		$partner_key = trim(stripslashes($_POST['disqus_partner_key']));
		if (!empty($partner_key)) {
			update_option('disqus_partner_key', $partner_key);
		}
	}
}

// Handle advanced options.
if ( isset($_POST['disqus_forum_url']) && isset($_POST['disqus_replace']) ) {
	$disqus_forum_url = $_POST['disqus_forum_url'];
	if ( $dot_pos = strpos($disqus_forum_url, '.') ) {
		$disqus_forum_url = substr($disqus_forum_url, 0, $dot_pos);
	}
	update_option('disqus_forum_url', $disqus_forum_url);
	update_option('disqus_partner_key', trim(stripslashes($_POST['disqus_partner_key'])));
	update_option('disqus_api_key', trim(stripslashes($_POST['disqus_api_key'])));
	update_option('disqus_user_api_key', trim(stripslashes($_POST['disqus_user_api_key'])));
	update_option('disqus_replace', $_POST['disqus_replace']);
	update_option('disqus_cc_fix', isset($_POST['disqus_cc_fix']));
	dsq_manage_dialog('Your settings have been changed.');
}

$dsq_user_api_key = isset($_POST['dsq_user_api_key']) ? $_POST['dsq_user_api_key'] : null;

// Get installation step process (or 0 if we're already installed).
$step = @intval($_GET['step']);
if ($step > 1 && $dsq_user_api_key) $step = 1;
elseif ($step > 1 && !isset($_POST['dsq_username'])) $step = 1;
$step = (dsq_is_installed()) ? 0 : ($step ? $step : 1);

if ( 2 == $step && isset($_POST['dsq_username']) && isset($_POST['dsq_password']) ) {
	$dsq_user_api_key = $dsq_api->get_user_api_key($_POST['dsq_username'], $_POST['dsq_password']);
	if ( !$dsq_user_api_key ) {
		$step = 1;
		dsq_manage_dialog($dsq_api->get_last_error(), true);
	}
	
	$dsq_sites = $dsq_api->get_forum_list($dsq_user_api_key);
	if ( $dsq_sites < 0 ) {
		$step = 1;
		dsq_manage_dialog($dsq_api->get_last_error(), true);
	}
}

?>
<div class="wrap" id="dsq-wrap">
	<ul id="dsq-tabs">
		<li class="selected" id="dsq-tab-main" rel="dsq-main"><?php echo (dsq_is_installed() ? 'Manage' : 'Install'); ?></li>
		<li id="dsq-tab-advanced" rel="dsq-advanced"><?php echo dsq_i('Advanced Options'); ?></li>
	</ul>

	<div id="dsq-main" class="dsq-content">
<?php
switch ( $step ) {
case 2:
?>
		<div id="dsq-step-2" class="dsq-main">
			<h2><?php echo dsq_i('Install Disqus Comments'); ?></h2>

			<form method="POST" action="?page=disqus">
			<?php wp_nonce_field('dsq-install-2'); ?>
			<table class="form-table">
				<tr>
					<th scope="row" valign="top"><?php echo dsq_i('Select a website'); ?></th>
					<td>
<?php
foreach ( $dsq_sites as $counter => $dsq_site ):
?>
						<input name="dsq_forum" type="radio" id="dsq-site-<?php echo esc_attr( $counter ); ?>" value="<?php echo esc_attr( $dsq_site->id ); ?>:<?php echo esc_attr( $dsq_site->shortname ); ?>" />
						<label for="dsq-site-<?php echo esc_attr( $counter ); ?>"><strong><?php echo esc_html( $dsq_site->name ); ?></strong> (<u><?php echo esc_html( $dsq_site->shortname ); ?>.disqus.com</u>)</label>
						<br />
<?php
endforeach;
?>
						<hr />
						<a href="http://disqus.com/comments/register/"><?php echo dsq_i('Or register a new one on the Disqus website.'); ?></a>
					</td>
				</tr>
			</table>

			<p class="submit" style="text-align: left">
				<input type="hidden" name="dsq_user_api_key" value="<?php echo esc_attr( $dsq_user_api_key ); ?>"/>
				<input name="submit" type="submit" value="Next &raquo;" />
			</p>
			</form>
		</div>
<?php
	break;
case 1:
?>
		<div id="dsq-step-1" class="dsq-main">
			<h2><?php echo dsq_i('Install Disqus Comments'); ?></h2>

			<form method="POST" action="?page=disqus&amp;step=2">
			<?php wp_nonce_field('dsq-install-1'); ?>
			<table class="form-table">
				<tr>
					<th scope="row" valign="top"><?php echo dsq_i('Username'); ?></th>
					<td>
						<input id="dsq-username" name="dsq_username" tabindex="1" type="text" />
						<a href="http://disqus.com/profile/"><?php echo dsq_i('(don\'t have a Disqus Profile yet?)'); ?></a>
					</td>
				</tr>
				<tr>
					<th scope="row" valign="top"><?php echo dsq_i('Password'); ?></th>
					<td>
						<input type="password" id="dsq-password" name="dsq_password" tabindex="2">
						<a href="http://disqus.com/forgot/"><?php echo dsq_i('(forgot your password?)'); ?></a>
					</td>
				</tr>
			</table>

			<p class="submit" style="text-align: left">
				<input name="submit" type="submit" value="Next &raquo;" tabindex="3">
			</p>

			<script type="text/javascript"> document.getElementById('dsq-username').focus(); </script>
			</form>
		</div>
<?php
	break;
case 0:
	$url = get_option('disqus_forum_url');
?>
		<div class="dsq-main">
			<h2><?php echo dsq_i('Comments'); ?></h2>
			<iframe src="<?php echo esc_url( DISQUS_URL . 'comments/moderate/' . $url . '/' ); ?>?template=wordpress" style="width: 100%; height: 800px"></iframe>
		</div>
<?php } ?>
	</div>

<?php
	$dsq_replace = get_option('disqus_replace');
	$dsq_forum_url = strtolower(get_option('disqus_forum_url'));
	$dsq_api_key = get_option('disqus_api_key');
	$dsq_user_api_key = get_option('dsq_user_api_key');
	$dsq_partner_key = get_option('disqus_partner_key');
	$dsq_cc_fix = get_option('disqus_cc_fix');
?>
	<!-- Advanced options -->
	<div id="dsq-advanced" class="dsq-content dsq-advanced" style="display:none;">
		<h2><?php echo dsq_i('Advanced Options'); ?></h2>
		<?php echo dsq_i('Version: %s', esc_html($dsq_version)); ?>
		<form method="POST">
		<?php wp_nonce_field('dsq-advanced'); ?>
		<table class="form-table">
			<tr>
				<th scope="row" valign="top"><?php echo dsq_i('Disqus short name'); ?></th>
				<td>
					<input name="disqus_forum_url" value="<?php echo esc_attr( $dsq_forum_url ); ?>" tabindex="1" type="text" />
					<br />
					<?php echo dsq_i('This is the unique identifier for your website on Disqus Comments.'); ?>
				</td>
			</tr>

			<tr>
				<th scope="row" valign="top"><?php echo dsq_i('Disqus API Key'); ?></th>
				<td>
					<input type="text" name="disqus_api_key" value="<?php echo esc_attr($dsq_api_key); ?>" tabindex="2">
					<br />
					<?php echo dsq_i('This is set for you when going through the installation steps.'); ?>
				</td>
			</tr>

			<tr>
				<th scope="row" valign="top"><?php echo dsq_i('Disqus User API Key'); ?></th>
				<td>
					<input type="text" name="disqus_user_api_key" value="<?php echo esc_attr($dsq_user_api_key); ?>" tabindex="2">
					<br />
					<?php echo dsq_i('This is set for you when going through the installation steps.'); ?>
				</td>
			</tr>
			
			<tr>
				<th scope="row" valign="top"><?php echo dsq_i('Disqus Partner Key'); ?></th>
				<td>
					<input type="text" name="disqus_partner_key" value="<?php echo esc_attr($dsq_partner_key); ?>" tabindex="2">
					<br />
					<?php echo dsq_i('Advanced: Used for single sign-on (SSO) integration. (<a href="%s" onclick="window.open(this.href); return false">more info on SSO</a>)', 'http://disqus.com/help/sso'); ?>
				</td>
			</tr>

			<tr>
				<th scope="row" valign="top"><?php echo dsq_i('Use Disqus Comments on'); ?></th>
				<td>
					<select name="disqus_replace" tabindex="3" class="disqus-replace">
						<option value="all" <?php if('all'==$dsq_replace){echo 'selected';}?>><?php echo dsq_i('On all existing and future blog posts.'); ?></option>
						<option value="closed" <?php if('closed'==$dsq_replace){echo 'selected';}?>><?php echo dsq_i('Only on blog posts with closed comments.'); ?></option>
					</select>
					<br />
					<?php echo dsq_i('NOTE: Your WordPress comments will never be lost.'); ?>
				</td>
			</tr>
			
			<tr>
				<th scope="row" valign="top"><?php echo dsq_i('Comment Counts'); ?></th>
				<td>
					<input type="checkbox" id="disqus_comment_count" name="disqus_cc_fix" <?php if($dsq_cc_fix){echo 'checked="checked"';}?> >
					<label for="disqus_comment_count"<?php echo dsq_i('>Output JavaScript in footer'); ?></label>
					<br /><?php echo dsq_i('NOTE: Check this if you have problems with the comment count displays including: not showing on permalinks, broken featured image carousels, or longer-than-usual homepage load times (<a href="%s" onclick="window.open(this.href); return false">more info</a>).', 'http://disqus.com/help/wordpress'); ?>
				</td>
			</tr>
			
		</table>

		<p class="submit" style="text-align: left">
			<input name="submit" type="submit" value="Save" class="button-primary button" tabindex="4">
		</p>
		</form>

		<table class="form-table">
			<?php if (DISQUS_CAN_EXPORT): ?>
			<tr>
				<th scope="row" valign="top"><?php echo dsq_i('Export comments to Disqus'); ?></th>
				<td>
					<div id="dsq_export">
						<p class="status"><a href="#" class="button"><?php echo dsq_i('Export Comments'); ?></a>  <?php echo dsq_i('This will sync your WordPress comments with Disqus'); ?></p>
					</div>
				</td>
			</tr>
			<?php endif; ?>
			<tr>
				<th scope="row" valign="top"><?php echo dsq_i('Uninstall Disqus Comments'); ?></th>
				<td>
					<form action="?page=disqus" method="POST">
						<?php wp_nonce_field('dsq-uninstall'); ?>
						<input type="submit" value="Uninstall" name="uninstall" onclick="return confirm('<?php echo dsq_i('Are you sure you want to uninstall Disqus?'); ?>')" class="button" />
					</form>
				</td>
			</tr>
			
		</table>
		<br/>
		<h3><?php echo dsq_i('Debug Information'); ?></h3>
		<p><?php echo dsq_i('Having problems with the plugin? <a href="%s">Drop us a line</a> and include the following details and we\'ll do what we can.', 'mailto:help+wp@disqus.com'); ?></p>
		<textarea style="width:90%; height:200px;">URL: <?php echo get_option('siteurl'); ?> 
Version: <?php echo esc_html( $wp_version ); ?>

Plugin Version: <?php echo esc_html( $dsq_version ); ?>
Forum Shortname: <?php echo esc_html( get_option('disqus_forum_url') ); ?>

Active Theme: <?php $theme = get_theme(get_current_theme()); echo esc_html( $theme['Name'].' '.$theme['Version'] ); ?>

</textarea><br/>
	</div>
</div>
