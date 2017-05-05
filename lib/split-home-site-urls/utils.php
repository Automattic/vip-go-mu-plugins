<?php

namespace Automattic\VIP\Split_Home_Site_URLs;

/**
* Parse home URL into pieces needed by setcookie()
*/
function parse_home_url_for_cookie() {
$url    = home_url( '/' );
$domain = parse_url( $url, PHP_URL_HOST );
$path   = parse_url( $url, PHP_URL_PATH );
$secure = 'https' === parse_url( $url, PHP_URL_SCHEME );

return compact( 'domain', 'path', 'secure' );
}
