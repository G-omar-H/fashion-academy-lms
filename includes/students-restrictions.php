<?php

/**
 * Register custom user roles.
 * This function will be called during plugin activation to define roles.
 */
function fa_lms_register_roles() {
    // Add "Student" role
    add_role(
        'student', // Internal role name
        __('Student', 'fashion-academy-lms'), // Display name
        array(
            'read' => true, // Allow access to the front end
            'edit_posts' => false, // No access to editing posts
            'delete_posts' => false // No access to deleting posts
        )
    );
}

// Block "students" from accessing the WordPress admin panel
add_action('admin_init', 'fa_block_wp_admin_for_students');
function fa_block_wp_admin_for_students() {
    if (is_admin() && ! current_user_can('manage_options')) {
        wp_redirect(site_url('/student-dashboard'));
        exit;
    }
}

// Hide the WordPress admin bar for "students"
add_filter('show_admin_bar', 'fa_hide_admin_bar_for_students');
function fa_hide_admin_bar_for_students($show) {
    if (! current_user_can('manage_options')) {
        return false;
    }
    return $show;
}