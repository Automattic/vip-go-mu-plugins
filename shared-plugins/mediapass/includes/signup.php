<?php 

// TODO: implement correct redirect once it's live

?>
<div class="wrap">
	<h2 class="header"><img src="<?php echo plugins_url('/images/logo-icon.png', dirname(__FILE__)) ?>" class="mp-icon" /><span>Signup or associate existing account</span></h2>
	
	<h3>Create an account in just a few minutes...</h3>
	
	<p>No credit card required. No technical expertise needed. Go live in just a few minutes! <a href="<?php echo esc_url( MediaPass_Plugin::$auth_register_url . urlencode( "http" . ( is_ssl() ? "s" : null ) . "://" . $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] ) ); ?>">Click here to get started!</a></p>
	
	<hr/>
	
	<h3>Already have an account?</h3>
	
	<p><a href="<?php echo esc_url( MediaPass_Plugin::$auth_login_url . urlencode( "http" . ( is_ssl() ? "s" : null) . "://" . $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] ) ); ?>">Authorize your account now!</a></p>
	
</div>