<?php
/*
Plugin Name: Fashion Academy LMS
...
*/
if ( ! defined( 'ABSPATH' ) ) exit;

// 1. Includes
require_once plugin_dir_path(__FILE__) . 'includes/class-fa-post-types.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-fa-activator.php';
require_once plugin_dir_path(__FILE__) . 'admin/class-fa-admin.php';
require_once plugin_dir_path(__FILE__) . 'public/class-fa-frontend.php';

// 2. Activation Hook - create DB tables, etc.
register_activation_hook( __FILE__, array( 'FA_Activator', 'activate' ) );

// 3. Initialize Classes after plugins are loaded
add_action( 'plugins_loaded', 'fa_lms_init' );
function fa_lms_init() {
    // Custom Post Types
    new FA_Post_Types();
    
    // Admin Menus/Pages
    new FA_Admin();
    
    // Frontend Shortcodes, etc.
    new FA_Frontend();
}
