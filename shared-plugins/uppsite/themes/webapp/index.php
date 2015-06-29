<?php
$isBusiness = uppsite_is_business_panel();
$app_name = mysiteapp_get_prefs_value('app_name', get_bloginfo('name'));
$has_tabbar = mysiteapp_get_prefs_value('menu_type') ? mysiteapp_get_prefs_value('menu_type') == 0 : true;
$has_homepage = mysiteapp_get_prefs_value('has_homepage', 'true') == 'true';
$has_homepage &= MySiteAppPlugin::detect_specific_os() != "android"; // Homepage on Android isn't functional.

$navbar_img = mysiteapp_get_prefs_value('navbar_background_url', '');
$direction = mysiteapp_get_prefs_value('direction', 'ltr');
$hideMenus = mysiteapp_get_prefs_value('hide_menus', '[]');

$add_to_contacts = mysiteapp_get_options_value(MYSITEAPP_OPTIONS_BUSINESS, 'add_to_contacts', 'true') == 'true' ? 'true' : 'false';

$cacheBuster = mysiteapp_get_prefs_value('last_update', 0);

?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo esc_html( $app_name ); ?></title>
    <style type="text/css">
        html, body {
            height: 100%;
        }

        #appLoadingIndicator {
            position: absolute;
            top: 50%;
            left: 50%;
            margin-top: -10px;
            margin-left: -70px;
            width: 155px;
            height: 48px;
            background: url(<?php echo MYSITEAPP_WEBAPP_RESOURCES ?>/uppsite_loading.gif) no-repeat;
        }
    </style>
    <script type="text/javascript">
        var UPPSITE_ROOT_URL = "<?php echo esc_js( uppsite_get_webapp_dir_uri() ); ?>/";
        var UPPSITE_BLOG_URL = "<?php echo esc_js( home_url( '/' ) ); ?>";
        var UPPSITE_NAVBAR_IMG = "<?php echo esc_js( $navbar_img ); ?>";
        var UPPSITE_BLOG_NAME = "<?php echo esc_js( $app_name ); ?>";
        var UPPSITE_ADS = <?php echo mysiteapp_get_ads(); ?>;
        var UPPSITE_PLUGIN_VERSION = "<?php echo esc_js( MYSITEAPP_PLUGIN_VERSION ); ?>";
        var UPPSITE_ANALYTICS_KEY = "<?php echo esc_js( uppsite_get_analytics_key() ); ?>";
        var UPPSITE_APP_ID = "<?php echo esc_js( uppsite_get_appid() ) ?>";
        var WINDOW_HEIGHT = window.inneHeight;
        var WINDOW_WIDTH = window.innerWidth;
        var PHP_USER_AGENT = "<?php echo array_key_exists("HTTP_USER_AGENT", $_SERVER) ? esc_js( $_SERVER['HTTP_USER_AGENT'] ) : "" ?>";
        var UPPSITE_IS_HOMEPAGE = <?php echo uppsite_webapp_bool_to_str($has_homepage) ?>;
        var UPPSITE_IS_TABBAR = <?php echo uppsite_webapp_bool_to_str($has_tabbar) ?>;
        var UPPSITE_COLOURS = <?php echo json_encode(uppsite_get_colours()); ?>;
        var UPPSITE_HOMEPAGE_CAROUSEL_TIMER = <?php echo mysiteapp_homepage_carousel_rotate_interval() ?>;
        var UPPSITE_IS_BUSINESS = <?php echo uppsite_webapp_bool_to_str($isBusiness) ?>;
        var UPPSITE_HIDE_MENUS  = <?php echo $hideMenus; ?>;
        var UPPSITE_POSTS_LIST_VIEW = "<?php echo uppsite_webapp_posts_list_view(); ?>";
        var UPPSITE_CUR_URL = "<?php echo esc_js( home_url( '/' ) ) ?>";
        var UPPSITE_ADD_TO_CONTACTS = <?php echo $add_to_contacts ?>;
        var UPPSITE_COOKIE_PATH = "<?php echo COOKIEPATH ?>";
    </script>
    <script type="text/javascript" id="placeholder"></script>
    <script type="text/javascript" src="<?php echo sprintf("%s/js/%s/webapp_helper.js?%s", MYSITEAPP_WEBSERVICES_URL, esc_attr(uppsite_get_appid()), $cacheBuster) ?>"></script>
    <script type="text/javascript">(function(k){function J(a){for(var b in UPPSITE_COLOURS)a=a.replace(RegExp(b,"gi"),UPPSITE_COLOURS[b]);return a}function u(a){function b(a,m){var d=a.length,b,e;for(b=0;b<d;b++){e=a[b];var i=a,j=b,c=void 0;"string"==typeof e&&(e={path:e});e.shared?(e.version=e.shared,c=e.shared+e.path):(A.href=UPPSITE_ROOT_URL+e.path,c=A.href);e.uri=c;e.key=f+"-"+c;g[c]=e;i[j]=e;e.type=m;e.index=b;e.collection=a;e.ready=!1;e.evaluated=!1}return a}var d;"string"==typeof a?(d=a,a=B(d)):d=JSON.stringify(a);var f=a.id,
i=f+"-"+C+p,g={};this.key=i;this.css=b(a.css,"css");this.js=b(a.js,"js");this.assets=this.css.concat(this.js);this.getAsset=function(a){return g[a]};this.store=function(){q(i,d)}}function v(a,b){h.write('<meta name="'+a+'" content="'+b+'">')}function r(a,b,d){var f=new XMLHttpRequest,d=d||D,a=a+(-1==a.indexOf("?")?"?":"&")+Date.now();try{f.open("GET",a,!0),f.onreadystatechange=function(){if(4==f.readyState){var a=f.status,c=f.responseText;200<=a&&300>a||304==a||0==a&&0<c.length?b(c):d()}},f.send(null)}catch(c){d()}}
function K(a,b){var d=h.createElement("iframe");s.push({iframe:d,callback:b});d.src=a+".html";d.style.cssText="width:0;height:0;border:0;position:absolute;z-index:-999;visibility:hidden";h.body.appendChild(d)}function E(a,b,d){var c=!!a.shared;a.remote?b(""):(c?K:r)(a.uri,b,d)}function F(a){var b=a.data,a=a.source.window,d,c,i,g;d=0;for(c=s.length;d<c;d++)if(i=s[d],g=i.iframe,g.contentWindow===a){i.callback(b);h.body.removeChild(g);s.splice(d,1);break}}function G(a){"undefined"!=typeof console&&(console.error||
console.log).call(console,a)}function q(a,b){try{l.setItem(a,b)}catch(d){if(d.code==d.QUOTA_EXCEEDED_ERR&&n){var c=n.assets.map(function(a){return a.key}),i=0,g=l.length,h=!1,m;for(c.push(n.key);i<=g-1;)m=l.key(i),-1==c.indexOf(m)?(l.removeItem(m),h=!0,g--):i++;h&&q(a,b)}}}function t(a){try{return l.getItem(a)}catch(b){return null}}function w(a){function b(a,b){var c=a.collection,e=a.index,i=c.length,j;a.ready=!0;a.content=b;for(j=e-1;0<=j;j--)if(a=c[j],!a.ready||!a.evaluated)return;for(j=e;j<i;j++)if(a=
c[j],a.ready)a.evaluated||d(a);else break}function d(a){a.evaluated=!0;if("js"==a.type)try{eval(a.content)}catch(b){G("Error evaluating "+a.uri+" with message: "+b)}else{var c=h.createElement("style"),d;c.type="text/css";c.textContent=J(a.content);"id"in a&&(c.id=a.id);"disabled"in a&&(c.disabled=a.disabled);d=document.createElement("base");d.href=a.path.replace(/\/[^\/]*$/,"/");x.appendChild(d);x.appendChild(c);x.removeChild(d)}delete a.content;0==--g&&f()}function f(){function b(){j&&d()}function d(){var a=
o.onUpdated||D;if("onSetup"in o)o.onSetup(a);else a()}function i(){k.store();f.forEach(function(a){q(a.key,a.content)});d()}function e(){H("online",e,!1);r(p,function(d){n=k=new u(d);var e;k.assets.forEach(function(b){e=a.getAsset(b.uri);(!e||b.version!==e.version)&&f.push(b)});l=f.length;0==l?c.status==c.IDLE?b():g=b:f.forEach(function(b){function d(){E(b,function(a){b.content=a;0==--l&&i()})}var c=a.getAsset(b.uri),e=b.path,f=b.update;!c||!f||null===t(b.key)||"delta"!=f?d():r("deltas/"+e+"/"+c.version+
".json",function(a){try{var d=b,c;var e=t(b.key),f=B(a),a=[],g,h,m;if(0===f.length)c=e;else{h=0;for(m=f.length;h<m;h++)g=f[h],"number"===typeof g?a.push(e.substring(g,g+f[++h])):a.push(g);c=a.join("")}d.content=c;0==--l&&i()}catch(j){G("Malformed delta content received for "+b.uri)}},d)})})}var f=[],j=!1,g=function(){},h=function(){c.swapCache();j=!0;g()},l;H("message",F,!1);if(c.status==c.UPDATEREADY)h();else if(c.status==c.CHECKING||c.status==c.DOWNLOADING)c.onupdateready=h,c.onnoupdate=c.onobsolete=
function(){g()};!1!==navigator.onLine?e():y("online",e,!1)}var i=a.assets,g=i.length,k;n=a;y("message",F,!1);0==g?f():i.forEach(function(a){var c=t(a.key);null===c?E(a,function(c){a.remote||q(a.key,c);b(a,c)},function(){b(a,"")}):b(a,c)})}function I(a){null!==h.readyState.match(/interactive|complete|loaded/)?w(a):y("DOMContentLoaded",function(){navigator.standalone?setTimeout(function(){setTimeout(function(){w(a)},1)},1):w(a)},!1)}var D=function(){},s=[],h=k.document,x=h.head,y=k.addEventListener,
H=k.removeEventListener,l=k.localStorage,c=k.applicationCache,B=JSON.parse,A=h.createElement("a"),z=h.location,C=z.origin+z.pathname+z.search,p=UPPSITE_ROOT_URL+"app.json",n;if("undefined"===typeof o)var o=k.Ext={};o.blink=function(a){var b=t(a.id+"-"+C+p);v("viewport","width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no");v("apple-mobile-web-app-capable","yes");v("apple-touch-fullscreen","yes");b?(a=new u(b),I(a)):r(p,function(b){a=new u(b);a.store();I(a)})}})(this);;Ext.blink({"id":"e921b852-80cb-4039-b313-dc0efd4889eb"})</script>
    <?php feed_links() ?>
</head>
<body class="direction-<?php echo $direction; ?>">
<div id="appLoadingIndicator"></div>
<?php if (function_exists('browsi_footer')) { browsi_footer(); } ?>
</body>
</html>
