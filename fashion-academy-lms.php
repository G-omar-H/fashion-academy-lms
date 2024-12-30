<?php

/*
Plugin Name: Fashion Academy LMS
Plugin URI:  https://fashion-academy.ma
Description: Custom LMS features for Fashion Academy
Version:     1.0
Author:      OGhazi
Author URI:  https://TheCyberQuery.com
License:     GPL2
Text Domain: fashion-academy-lms
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// Custom logging functionality for this plugin
function fa_plugin_log( $message ) {
    $log_file = plugin_dir_path( __FILE__ ) . 'fashion-academy-debug.log';
    $timestamp = date( 'Y-m-d H:i:s' );
    $log_message = "[$timestamp] $message" . PHP_EOL;
    file_put_contents( $log_file, $log_message, FILE_APPEND | LOCK_EX );
}

function fa_error_handler( $errno, $errstr, $errfile, $errline ) {
    if ( strpos( $errfile, plugin_dir_path( __FILE__ ) ) === 0 ) {
        fa_plugin_log( "Error [$errno]: $errstr in $errfile on line $errline" );
    }
    return false; // Continue with the default error handler
}
set_error_handler( 'fa_error_handler' );

// 1. Includes
require_once plugin_dir_path(__FILE__) . 'includes/class-fa-post-types.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-fa-activator.php';
require_once plugin_dir_path(__FILE__) . 'admin/class-fa-admin.php';
require_once plugin_dir_path(__FILE__) . 'public/class-fa-frontend.php';
require_once plugin_dir_path(__FILE__) . 'includes/students-restrictions.php';

// 2. Activation Hook - create DB tables, etc.
register_activation_hook( __FILE__, 'fa_lms_activation_hook' );
function fa_lms_activation_hook() {
    // Create custom user roles
    fa_lms_register_roles();
    FA_Activator::activate();
    fa_plugin_log( 'Plugin activated successfully.' );
}

// 3. Initialize Classes after plugins are loaded
add_action( 'plugins_loaded', 'fa_lms_init' );

add_action( 'init', function() {
    // Check if the log has already been written for this request
    if ( ! defined( 'FA_LMS_LOGGED' ) ) {
        define( 'FA_LMS_LOGGED', true );
        fa_plugin_log( 'FA_LMS plugin initialized.' );
    }
} );



// AJAX actions for checking a submission's status in real-time
add_action('wp_ajax_fa_check_submission', 'fa_ajax_check_submission');
add_action('wp_ajax_nopriv_fa_check_submission', 'fa_ajax_check_submission');

function fa_ajax_check_submission() {
    global $wpdb;
    $submission_id = (int) $_GET['submission_id'];
    $table = $wpdb->prefix . 'homework_submissions';

    fa_plugin_log("AJAX Check Submission: submission_id = $submission_id");

    $submission = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $submission_id)
    );

    if ($submission) {
        wp_send_json_success([
            'status' => $submission->status,
            'grade'  => $submission->grade,
        ]);
    } else {
        wp_send_json_error('Submission not found.', 404);
    }
}

register_deactivation_hook( __FILE__, 'fa_lms_deactivation_hook' );
function fa_lms_deactivation_hook() {
    // Remove "Student" role
    remove_role('student');
}

function fa_lms_init() {
    // Custom Post Types
    new FA_Post_Types();

    // Admin Menus/Pages
    new FA_Admin();

    // Frontend Shortcodes, etc.
    new FA_Frontend();
}

?>
