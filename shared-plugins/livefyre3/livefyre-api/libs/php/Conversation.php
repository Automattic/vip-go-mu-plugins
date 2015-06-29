<?php
require_once('JWT.php');

class Livefyre_Conversation {
    private $id;
    private $article;
    private $delegates;
    
    public function __construct( $conv_id = null, $article = null ) {
        $this->id = $conv_id;
        $this->article = $article;
        $this->delegates = array();
    }
    
    public function add_js_delegate( $delegate_name, $code ) {
        $this->delegates[ $delegate_name ] = $code;
    }
    
    public function render_js_delegates( ) {
        $str_out = '';
        if ( $this->delegates ) {
            $str_out = "var livefyreConvDelegates = {\n";
            foreach ($this->delegates as $handler => $code) {
                $str_out .= "    handle_$handler: " . $code . ", \n";
            }
            $str_out .= "}\nLF.ready( function() { LF.Dispatcher.addListener(livefyreConvDelegates); } )";
        }
        return $str_out;
    }

    public function to_initjs( $user = null, $display_name = null, $backplane = false, $jquery_ready = false, $include_source = true ) {
        /*
            **DEPRECATED**
            Please use to_initjs_v3() if you are on Livefyre comments V3
        */
        $article = $this->article;
        $site = $article->get_site();
        $domain = $site->get_domain();
        $network_name = $domain->get_host();
        $site_key = $site->get_key();
        $config = array(
            'site_id' => $site->get_id(),
            'article_id' => $article->get_id()
        );
        $builds_token = true;
        if ( $network_name != LF_DEFAULT_PROFILE_DOMAIN ) {
            $config[ 'domain' ] = $network_name;
        } else {
            // nobody but Livefyre can build tokens for livefyre.com profiles
            $builds_token = false;
        }
        $article_url = $article->get_url();
        $article_title = $article->get_title();
        if ( !empty($site_key) && !empty($article_url) && !empty($article_title) ) {
            // Produce a conv meta checksum if we have enough data
            $sig_fields = array($config['article_id'], $article_url, $article_title, $site_key);
            $config['conv_meta'] = array(
                'article_url' => $article_url,
                'title' => $article_title,
                'sig' => md5(implode(',', $sig_fields))
            );
        }
        if ( $backplane ) {
            $add_backplane = 'if ( typeof(Backplane) != \'undefined\' ) { lf_config.backplane = Backplane; };';
        } else {
            $add_backplane = '';
        }
        $login_js = '';
        if ( $user && $builds_token ) {
            $login_json = array( 'token' => $user->token( ), 'profile' => array('display_name' => $display_name) );
            $login_json_str = json_encode( $login_json );
            $login_js = "LF.ready( function() {LF.login($login_json_str);} );";
        }
        return ($include_source ? $domain->source_js_v1() : ''). '
            <script type="text/javascript">
                ' . ($jquery_ready ? 'jQuery(function(){' : '') . '
                var lf_config = ' . json_encode( $config ) . ';
                ' . $add_backplane . '
                var conv = LF(lf_config);
                ' . $login_js . '
                ' . $this->render_js_delegates() . '
                ' . ($jquery_ready ? '});' : '') . '
            </script>';
    }
    
    public function collection_meta() {
        $article = $this->article;
        $site = $article->get_site();
        $domain = $site->get_domain();
        $collectionMeta = array("title" => $article->get_title(),
                "url" => $article->get_url(),
                "tags" => $article->get_tags());
        
        $checksum = md5(json_encode($collectionMeta));
        $collectionMeta["checksum"] = $checksum;
        $collectionMeta["articleId"] = $article->get_id();
        $jwtString = JWT::encode($collectionMeta, $site->get_key());
        return array('collectionMeta' => $jwtString, 'checksum' => $checksum);
    }
    
    public function to_initjs_v3( $el = false, $config = null ) {
        // We have to build this string of JS in a weird way because we conditionally include
        // direct JS references, which isn't possible with json_encode
        $onload = '';
        if ( is_array($el) ) {
            // This parameter can optionally be the $config array, must include key 'el'
            $config = $el;
        } elseif ( is_string($el) && is_array($config) ) {
            $config['el'] = $el;
        }
        if ( is_string($config) or $config == null ) {
            $delegate = $config;
        } else {
            if ( isset( $config['delegate'] ) ) {
                $delegate = $config['delegate'];
            } else {
                $delegate = null;
            }
            if ( isset( $config['onload'] ) ) {
                $onload = ', ' . $config['onload'];
                unset( $config['onload'] );
            }
        }
        if ( !isset( $config['el'] ) ) {
            $error = 'Unable to initialize Livefyre - you must specify a target element for the interface as required parameter \'el\' in JavaScript or when calling $conversation->to_initjs_v3()';
            return '<!-- ' . $error . ' --> <script type="text/javascript">console.log("' . $error . '")</script>';
        }
        $article = $this->article;
        $site = $article->get_site();
        $domain = $site->get_domain();
        $network_name = $domain->get_host();
        $js_config = $this->collection_meta();
        $js_config['siteId'] = $site->get_id();
        $js_config['articleId'] = $article->get_id();
        foreach ( $config as $k => $v ) {
            $js_config[$k] = $v;
        }
        $delegate_str = '';
        if ( $delegate ) {
            $delegate_str = ', "authDelegate": ' . $delegate;
        }
        $fyre_config = '{}';
        if ( $network_name != LF_DEFAULT_PROFILE_DOMAIN ) {
            $fyre_config = '{"network": "' . $network_name . '"' . $delegate_str . '}';
        }
        return '<script type="text/javascript">' .
                'var lf_config = ' . json_encode( array($js_config) ) . ';' . 
                'var conv = fyre.conv.load(' . $fyre_config . ', lf_config' . $onload . ');' .
                '</script>';
    }
    
    public function to_html( ) {
        assert('$this->article != null /* Article is necessary to get HTML */');
        $site_id = $this->article->get_site()->get_id();
        $article_id = $this->article->get_id();
        $site = $this->article->get_site();
        $domain = $site->get_domain();
        $dhost = $domain->get_host();
        $article_id_b64 = urlencode(base64_encode($article_id));
        $url = "http://bootstrap.$dhost/api/v1.1/public/bootstrap/html/$site_id/$article_id_b64.html";
        $result = $domain->http->request($url, array('method' => 'GET'));
        if (is_array( $result ) && isset($result['response']) && $result['response']['code'] == 200) {
            return $result['body'];
        } else {
            return false;
        }
    }
}

?>
