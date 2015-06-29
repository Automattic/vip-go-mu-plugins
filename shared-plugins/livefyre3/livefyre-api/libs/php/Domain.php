<?php

if ( !defined( 'LF_DEFAULT_TLD' ) ) {
    define( 'LF_DEFAULT_TLD', 'livefyre.com' );
}
if ( !defined( 'LF_DEFAULT_PROFILE_DOMAIN' ) ) {
    define( 'LF_DEFAULT_PROFILE_DOMAIN', 'livefyre.com' );
}

define( 'LF_COOKIE_PREFIX', 'livefyre_' );

include("User.php");
include("Site.php");

class Livefyre_Domain {
    private $host;
    private $key;
    private $livefyre_tld;
    private $engage_app_name;
    
    public function __construct( $network, $key = null, $http_api = null, $options = null ) {
        if ( isset( $options['livefyre_tld'] ) ) {
            $this->livefyre_tld = $options['livefyre_tld'];
        } else {
            $this->livefyre_tld = LF_DEFAULT_TLD;
        }
        if ( isset( $options['engage_app_name'] ) ) {
            $this->engage_app_name = $options['engage_app_name'];
        }
        $this->host = $network;
        $this->key = $key;
        if ( defined('LF_DEFAULT_HTTP_LIBRARY') ) {
            $httplib = LF_DEFAULT_HTTP_LIBRARY;
            $this->http = new $httplib;
        } else {
            include_once("Http.php");
            $this->http = new Livefyre_http; 
        }
    }
    
    public function get_livefyre_tld() {
        return $this->livefyre_tld;
    }
    
    public function get_host() {
        return $this->host;
    }
    
    public function get_key() {
        return $this->key;
    }
    
    public function get_engage_app() {
        return $this->engage_app_name;
    }
    
    public function user($uid, $display_name = null) {
        return new Livefyre_User($uid, $this, $display_name);
    }
    
    public function push_user_data( $data ) {
        $systemuser = $this->user( 'system' );
        $systemuser->push( $data );
    }
    
    public function set_pull_url( $url_template ) {
        $request_url = 'http://' . $this->get_host() . '/?pull_profile_url=' . urlencode($url_template) . '&actor_token=' . $this->user('system')->token();
        return $this->http->request( $request_url, array( 'method' => 'POST' ) );
    }
    
    public function set_user_affiliation( $user_id, $type, $scope = 'domain', $target_id = null ) {
        $allowed_types = array( 'admin', 'member', 'none', 'outcast', 'owner' );
        $allowed_scope = array( 'domain', 'site', 'conv' );
        if ( !in_array( $type, $allowed_types ) ) {
            trigger_error( 'You cannot set a Livefyre user\'s affiliation to a type other than the allowed: ' . implode( ', ', $allowed_types ), E_USER_ERROR );
            return false;
        } else {
            if ( !in_array( $scope, $allowed_scope ) ) {
                trigger_error( 'You cannot set a Livefyre user\'s affiliation within a scope other than the allowed: ' . implode( ', ', $allowed_scope ), E_USER_ERROR );
                return false;
            }
            $user_jid = $user_id . '@' . $this->get_host();
            $systemuser = $this->user( 'system' );
            $request_url = 'http://' . $this->get_host() . '/api/v1.1/private/management/user/' . $user_jid . '/role/?lftoken=' . $this->user('system')->token();
            $post_data = array(
                'affiliation' => $type
            );
            if ($scope == 'domain') { 
                $post_data['domain_wide'] = '1';
            } elseif ($scope == 'conv') {
                $post_data['conv_id'] = $target_id;
            } elseif ($scope == 'site') {
                $post_data['site_id'] = $target_id;
            }
            return $this->http->request( $request_url, array('method'=>'POST', 'data'=>$post_data) );
        }
        return false;
    }
    
    public function token_cookie_name() {
        return LF_COOKIE_PREFIX . 'token_' . $this->get_host();
    }
    
    public function dname_cookie_name() {
        return LF_COOKIE_PREFIX . 'display_name_' . $this->get_host();
    }
    
    public function set_token_cookie( $token, $cookie_path, $cookie_domain, $expire = null, $secure = false ) {
        $this->set_cookie($this->token_cookie_name(), $token, $cookie_path, $cookie_domain, $expire, $secure = false);
    }
    
    public function set_display_name_cookie( $display_name, $cookie_path, $cookie_domain, $expire = null, $secure = false ) {
        if ($expire == null) {
            $expire = time() + 1210000;
        }
        $this->set_cookie($this->dname_cookie_name(), $display_name, $cookie_path, $cookie_domain, $expire, $secure = false);
    }
    
    public function set_cookie( $name, $value, $cookie_path, $cookie_domain, $expire = null, $secure = false ) {
        if ( $expire == null ) {
            $expire = time() + 86400;
        }
        setcookie( $name, $value, $expire, $cookie_path, $cookie_domain, $secure, false );
    }
    
    public function clear_cookies( $cookie_path, $cookie_domain ) {
        setcookie( $this->dname_cookie_name(), ' ', time() - 31536000, $cookie_path, $cookie_domain );
        setcookie( $this->token_cookie_name(), ' ', time() - 31536000, $cookie_path, $cookie_domain );
    }
    
    public function source_js_v1() {
        return '<script type="text/javascript" src="http://zor.' . $this->get_livefyre_tld() . '/wjs/v1.0/javascripts/livefyre_init.js"></script>';
    }
    
    public function source_js_v3() {
        return '<script type="text/javascript" src="http://zor.' . $this->get_livefyre_tld() . '/wjs/v3.0/javascripts/livefyre.js"></script>';
    }
    
    public function authenticate_js( $token_url = '', $cookie_path = '/', $token_cookie = null, $dname_cookie = null  ) {
        
        /*
            This script should be rendered when it appears the user is logged in
            Now we attempt to fetch Livefyre credentials from a cookie,
            falling back to ajax as needed.
        */
        $token_cookie = $token_cookie ? $token_cookie : $this->token_cookie_name();
        $dname_cookie = $dname_cookie ? $dname_cookie : $this->dname_cookie_name();
        ?>
            <script type="text/javascript">
                LF.ready(function() {
                    var lfTokenCookie = '<?php echo $token_cookie; ?>';
                    var lfDnameCookie = '<?php echo $dname_cookie; ?>';
                    if (!$jl.cookie(lfTokenCookie)) {
                        <?php
                        if ( !empty($token_url) ) {
                            ?>
                            // fetch via ajax
                            $jl.ajax({
                                url: '<?php echo $token_url; ?>',
                                type: 'json',
                                success: function(json){
                                    LF.login(json);
                                    $jl.cookie(lfTokenCookie, json.token, {expires:1, path:'<?php echo $cookie_path ?>'});
                                    $jl.cookie(lfDnameCookie, json.profile.display_name, {expires:1, path:'<?php echo $cookie_path ?>'});
                                },
                                error: function(a, b){
                                    console.log("There was some problem fetching a livefyre token. ", a, b);
                                }
                            });
                            <?php
                        }
                        ?>
                    } else {
                        try {
                            LF.login({
                                token: $jl.cookie(lfTokenCookie),
                                profile:{
                                    display_name: $jl.cookie(lfDnameCookie)
                                }
                            });
                        } catch (e) {
                            console.log("Error attempting to login with ", lfTokenCookie, " cookie value: ", $jl.cookie(lfTokenCookie), " ", e);
                        }
                    }
                });
            </script>
        <?php
    
    }

    public function authenticate_js_v3( $token_url = '', $cookie_path = '/', $token_cookie = null, $dname_cookie = null  ) {
        
        /*
            This script should be rendered when it appears the user is logged in
            Now we attempt to fetch Livefyre credentials from a cookie,
            falling back to ajax as needed.
        */
        $token_cookie = $token_cookie ? $token_cookie : $this->token_cookie_name();
        //$dname_cookie = $dname_cookie ? $dname_cookie : $this->dname_cookie_name();
        ?>
            <script type="text/javascript">
                if (document.location.href.indexOf('http://localhost') == 0) {
                    console.log('Livefyre needs at least one . (dot) in the domain name.  Therefore, localhost is considered invalid - try using 127.0.0.1 instead.');
                }
                // these are just utility methods for working with cookies
                function lfSetCookie(a,b,c){if(c){var d=new Date;d.setTime(d.getTime()+c*24*60*60*1e3);var e="; expires="+d.toGMTString()}else var e="";document.cookie=a+"="+b+e+"; path=/"}function lfGetCookie(a){var b=a+"=";var c=document.cookie.split(";");for(var d=0;d<c.length;d++){var e=c[d];while(e.charAt(0)==" ")e=e.substring(1,e.length);if(e.indexOf(b)==0)return e.substring(b.length,e.length)}return null}function lfDeleteCookie(a){lfSetCookie(a,"",-1)}
                
                function doLivefyreAuth() {
                    var lfTokenCookie = '<?php echo $token_cookie; ?>';
                    if (!lfGetCookie(lfTokenCookie)) {
                        <?php
                        if ( !empty($token_url) ) {
                            $sep = strpos($token_url, '?') === FALSE ? '?' : '&' ;
                            ?>
                            // fetch via JSONP
                            window.lfTokenCallback = function(json){
                                fyre.conv.login(json.token);
                                lfSetCookie(lfTokenCookie, json.token, 1);
                            };
                            var h = document.getElementsByTagName("head")[0];
                            var s = document.createElement('script');
                            s.type = 'text/javascript';
                            s.src = '<?php echo $token_url . $sep . "callback=lfTokenCallback"; ?>';
                            h.appendChild(s);
                            <?php
                        }
                        ?>
                    } else {
                        try {
                            fyre.conv.login(lfGetCookie(lfTokenCookie));
                        } catch (e) {
                            console.log("Error attempting to login with ", lfTokenCookie, " cookie value: ", lfGetCookie(lfTokenCookie), " ", e);
                        }
                    }
                }
            </script>
        <?php
    
    }


    public function site($site_id, $key = null) {
        return new Livefyre_Site($site_id, $key, $this);
    }

    public function validate_system_token($token) {
        // This replaces the below - it uses JWT to verify that the token is valid for user id = 'system'
        return lftokenValidateSystemToken($token, $this->get_key(), $this->get_host());
    }

    public function validate_server_token($token) {
        return lftokenValidateServerToken($token, $this->get_key());
    }
}

?>
