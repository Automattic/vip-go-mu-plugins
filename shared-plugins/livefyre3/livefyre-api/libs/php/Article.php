<?php

include("Conversation.php");

class Livefyre_Article {
    private $id;
    private $site;
    private $tags;
    private $url;
    private $title;
    
    public function __construct($id, $site, $url, $title, $tags) {
        $this->id = $id;
        $this->site = $site;
        $this->tags = $tags;
        $this->url = $url;
        $this->title = $title;
    }
    
    public function get_id() {
        return $this->id;
    }
    
    public function get_site() {
        return $this->site;
    }
    
    public function get_tags(){
        return $this->tags;
    }
    
    public function get_url(){
        return $this->url;
    }
    
    public function get_title(){
        return $this->title;
    }
    
    public function conversation() {
        return new Livefyre_Conversation(null, $this);
    }
}

?>