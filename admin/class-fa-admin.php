<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FA_Admin {

    public function __construct() {
        // Hook for adding admin menus/pages
        add_action('admin_menu', array($this, 'add_admin_pages'));
    }

    public function add_admin_pages() {
        // We'll create "Fashion Academy" menu in WP Admin
    }
}
