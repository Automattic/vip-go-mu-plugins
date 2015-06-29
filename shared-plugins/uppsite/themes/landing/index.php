<?php
$native_url = uppsite_get_native_app('url');
$base_dir = uppsite_get_template_directory_uri();
$app_name = mysiteapp_get_prefs_value('app_name', get_bloginfo('name'));
$navbar_img = mysiteapp_get_prefs_value('navbar_background_url', null);
$landing_bg = mysiteapp_get_prefs_value('landing_background_url', MYSITEAPP_LANDING_DEFAULT_BG);

$native_icon = "ios";
switch (MySiteAppPlugin::detect_specific_os()) {
    case "android":
        $native_icon = "android";
        break;
    case "wp":
        $native_icon = "windows";
        break;
}
$hideMenus = json_decode(mysiteapp_get_prefs_value('hide_menus', '[]'), true);
$branded = in_array('about', $hideMenus);
?><html>
<head>
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="viewport" content="width=device-width, minimum-scale=1.0, maximum-scale=1.0"/>
    <link type="text/css" rel="stylesheet" href="<?php echo $base_dir ?>/assets/css/layout.css"/>
    <script type="text/javascript">
        var is_permanent = "";
        function remember_func(elem) {
            if (elem.checked) {
                is_permanent = "&msa_theme_save_forever=1";
            } else {
                is_permanent = "";
            }
        }
        function btn_selected(elem) {
            var cacheBuster = "";
<?php if (uppsite_should_bypass_cache()): ?>
            document.cookie = "wordpress_logged_in=1; expires=Fri, 3 Jan 2020 20:20:11 UTC; path=<?php echo COOKIEPATH ?>"; // Bypass page-cache plugins
            cacheBuster = "&cb=" + new String(Math.random()).replace(".", "");
<?php endif ?>
            window.location = elem.href + cacheBuster + is_permanent + window.location.hash;
            return false;
        }

        var resizeFunc = function() {
            var height = (window.innerHeight) ? window.innerHeight : $(window).height;
            if (document.body) {
                document.body.style.minHeight = height + "px";
            }
            window.scrollTo(0, 1);
        };
        document.addEventListener("DOMContentLoaded", resizeFunc, this);
        window.onresize = resizeFunc;
    </script>
    <title><?php echo $app_name ?></title>
</head>
<body<?php if (!empty($landing_bg)) { ?> style="background-image: url(<?php echo $landing_bg; ?>)"<?php } ?>>
<div class="main-wrapper">
    <div id="top_container">
        <div class="header">
            <?php if (!empty($navbar_img)) { ?>
            <img src="<?php echo $navbar_img; ?>" class="site-logo" />
            <?php } else { ?>
            <h1><?php echo $app_name; ?></h1>
            <?php } ?>
        </div>
        <?php if (!is_null($native_url)): ?>
        <a class="button download <?php echo $native_icon ?>" href='<?php echo esc_url( $native_url ); ?>'>
            <span>Download the free app</span>
        </a>
        <?php endif; ?>
        <?php if (mysiteapp_should_show_webapp()): ?>
        <a class="button webapp" href='<?php echo esc_url( add_query_arg( 'msa_theme_select', 'webapp' ) ) ?>' onclick='return btn_selected(this);'>
            <span>Browse mobile website</span>
        </a>
        <?php endif; ?>
        <a class="button desktop" href='<?php echo esc_url( add_query_arg( 'msa_theme_select', 'normal' ) ) ?>' onclick='return btn_selected(this);'>
            <span>Browse regular website</span>
        </a>
        <div class="save-box">
            <input id="save_box" class="input_save" type="checkbox" name="save" value="" checked="checked" onchange="remember_func(this);"/>
            <label for="save_box">Save my selection</label>
        </div>
    </div>
    <div id="bottom_container">
        <?php if (!$branded): ?><a href="https://www.uppsite.com" class="powered-by-link"><span class="powered-by">powered by </span><span class="uppsite-bw-logo">UppSite</span></a><?php endif; ?>
    </div>
</div>
<script type="text/javascript">
    remember_func(document.getElementById('save_box'));
    window.onload = function() {
        window.scrollTo(0, 0);
    };
</script>
</body>
</html>