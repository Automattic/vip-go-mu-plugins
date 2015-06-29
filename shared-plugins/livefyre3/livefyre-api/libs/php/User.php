<?php

include("Token.php");

class Livefyre_User {
    private $uid;
    private $domain;
    private $display_name;
    
    public function __construct($uid, $domain, $display_name = null) {
        $this->uid = $uid;
        $this->domain = $domain;
        $this->display_name = $display_name;
    }
    
    public function get_uid() { return $this->uid; }
    public function get_domain() { return $this->domain; }
    public function get_display_name() { return $this->display_name; }
    
    public function jid() {
        return $this->$uid.'@'.$this->domain->get_host();
    }
    
    public function token( $max_age = 86400 ) {
        $domain_key = $this->domain->get_key();
        assert('$domain_key != null /* Domain key is necessary to generate token */');
        return Livefyre_Token::from_user($this, $max_age);
    }
    
    public function auth_json( $max_age = 86400 ) {
        return json_encode( 
            array(
                "token" => $this->token( $max_age ),
                "profile" => array(
                    "display_name" => $this->get_display_name()
                )
            )
        );
    }
    
    public function push( $user_data ) {
        $post_data = array( 'data' => json_encode( $user_data ) );
        $token_base64 = $this->token();
        $domain = $this->get_domain( );
        $remote_url = "http://{$domain->get_host()}/profiles/?actor_token={$token_base64}&id={$user_data['id']}";
        $result = $domain->http->request($remote_url, array('method' => 'POST', 'data' => $post_data));
        if (is_array( $result ) && isset($result['response']) && $result['response']['code'] == 200) {
            return $result['body'];
        } else {
            return false;
        }
    }
}

?>
