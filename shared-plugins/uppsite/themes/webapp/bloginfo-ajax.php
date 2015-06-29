<?php
print json_encode(
    array(
        'name' => get_bloginfo('name'),
        'url' => site_url(),
        'version' => get_bloginfo('version'),
        'tagline' => get_bloginfo('description')
    )
);