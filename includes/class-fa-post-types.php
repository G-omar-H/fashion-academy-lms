<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FA_Post_Types {

    public function __construct() {
        // We'll register our "course" and "lesson" post types here
        add_action('init', array($this, 'register_post_types'));
    }

    public function register_post_types() {
        // code to register CPT
    }
}
