<?php 

global $wp_version;

?>
<script type="text/javascript">
//<![CDATA[
window.ZemantaGetAPIKey = function () { 
	return '<?php echo esc_js( $api_key ); ?>';
};

window.ZemantaPluginVersion = function () { 
	return '<?php echo esc_js( $version ); ?>';
};

window.ZemantaProxyUrl = function () { 
	return '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>';
};

window.ZemantaPluginFeatures = {
<?php 
for($i = 0, $keys = array_keys($features), $len = sizeof($keys); $i < $len; $i++) :
	echo "\t'" . esc_js( $keys[$i] ) . "': " . json_encode($features[$keys[$i]]) . ($i < $len-1 ? ',' : '') . "\n";
endfor; 
?>
};
//]]>
</script>

<script type="text/javascript" id="zemanta-loader" src="https://zemantastatic.s3.amazonaws.com/plugins/wordpress/loader.js"></script>
