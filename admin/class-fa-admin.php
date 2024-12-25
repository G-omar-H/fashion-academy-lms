<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FA_Admin {

    public function __construct() {
        // Hook for adding admin menus/pages
        add_action('admin_menu', array($this, 'add_admin_pages'));
    }


    public function add_admin_pages() {
        // Main "Fashion Academy" menu (if not already created)
        add_menu_page(
            __('Fashion Academy', 'fashion-academy-lms'),
            __('Fashion Academy', 'fashion-academy-lms'),
            'manage_options',
            'fa-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-welcome-learn-more',
            3
        );
    
        // Submissions page
        add_submenu_page(
            'fa-dashboard',               // Parent slug
            __('Submissions', 'fashion-academy-lms'), // Page title
            __('Submissions', 'fashion-academy-lms'), // Menu title
            'manage_options',             // Capability
            'fa-submissions',             // Menu slug
            array($this, 'render_submissions_page')   // Callback
        );
    }

    public function render_submissions_page() {
        // Handle actions like "view" or "grade" by checking $_GET
        if ( isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id']) ) {
            $this->render_single_submission( intval($_GET['id']) );
            return;
        }
    
        global $wpdb;
        $submission_table = $wpdb->prefix . 'homework_submissions';
    
        // Get submissions, optionally filter by status
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $query = "SELECT * FROM $submission_table";
        if ( ! empty($status_filter) ) {
            $query .= $wpdb->prepare(" WHERE status = %s", $status_filter);
        }
        $query .= " ORDER BY submission_date DESC";
    
        $submissions = $wpdb->get_results($query);
    
        echo '<div class="wrap"><h1>' . __('Homework Submissions', 'fashion-academy-lms') . '</h1>';
    
        // Add filter form
        echo '<form method="get" style="margin-bottom: 20px;">';
        echo '<input type="hidden" name="page" value="fa-submissions" />';
        echo '<label for="status_filter">' . __('Filter by Status:', 'fashion-academy-lms') . '</label> ';
        echo '<select name="status" id="status_filter" style="margin-right: 10px;">';
        echo '<option value="">' . __('-- All --', 'fashion-academy-lms') . '</option>';
        echo '<option value="pending"' . selected( $status_filter, 'pending', false ) . '>' . __('Pending', 'fashion-academy-lms') . '</option>';
        echo '<option value="graded"' . selected( $status_filter, 'graded', false ) . '>' . __('Graded', 'fashion-academy-lms') . '</option>';
        echo '<option value="passed"' . selected( $status_filter, 'passed', false ) . '>' . __('Passed', 'fashion-academy-lms') . '</option>';
        echo '</select>';
        submit_button(__('Filter', 'fashion-academy-lms'), 'secondary', 'submit', false);
        echo '</form>';
    
        if ( ! $submissions ) {
            echo '<p>' . __('No submissions found.', 'fashion-academy-lms') . '</p></div>';
            return;
        }
    
        echo '<table class="widefat fixed" cellspacing="0">';
        echo '<thead>
                <tr>
                    <th>' . __('ID', 'fashion-academy-lms') . '</th>
                    <th>' . __('User', 'fashion-academy-lms') . '</th>
                    <th>' . __('Course', 'fashion-academy-lms') . '</th>
                    <th>' . __('Lesson', 'fashion-academy-lms') . '</th>
                    <th>' . __('Status', 'fashion-academy-lms') . '</th>
                    <th>' . __('Grade', 'fashion-academy-lms') . '</th>
                    <th>' . __('Submitted On', 'fashion-academy-lms') . '</th>
                    <th>' . __('Action', 'fashion-academy-lms') . '</th>
                </tr>
              </thead>';
        echo '<tbody>';
    
        foreach ( $submissions as $submission ) {
            // Get user info
            $user_info = get_userdata($submission->user_id);
            $user_display = $user_info ? esc_html($user_info->display_name) . ' (' . esc_html($user_info->user_email) . ')' : __('Unknown', 'fashion-academy-lms');
    
            // Get course and lesson titles
            $course = get_post($submission->course_id);
            $course_title = $course ? esc_html($course->post_title) : __('Unknown', 'fashion-academy-lms');
    
            $lesson = get_post($submission->lesson_id);
            $lesson_title = $lesson ? esc_html($lesson->post_title) : __('Unknown', 'fashion-academy-lms');
    
            $view_url = add_query_arg(
                array(
                    'page'   => 'fa-submissions',
                    'action' => 'view',
                    'id'     => $submission->id
                ),
                admin_url('admin.php')
            );
    
            echo '<tr>';
            echo '<td>' . esc_html($submission->id) . '</td>';
            echo '<td>' . $user_display . '</td>';
            echo '<td>' . $course_title . '</td>';
            echo '<td>' . $lesson_title . '</td>';
            echo '<td>' . esc_html(ucfirst($submission->status)) . '</td>';
            echo '<td>' . esc_html($submission->grade) . '</td>';
            echo '<td>' . esc_html($submission->submission_date) . '</td>';
            echo '<td><a class="button" href="' . esc_url($view_url) . '">' . __('View / Grade', 'fashion-academy-lms') . '</a></td>';
            echo '</tr>';
        }
    
        echo '</tbody></table></div>';
    }

    public function render_single_submission($submission_id) {
        global $wpdb;
        $submission_table = $wpdb->prefix . 'homework_submissions';

        // Fetch the submission record
        $submission = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $submission_table WHERE id = %d", $submission_id) );

        if ( ! $submission ) {
            echo '<div class="wrap"><h1>' . __('Submission Not Found', 'fashion-academy-lms') . '</h1></div>';
            return;
        }

        // Log the fetched submission data for debugging
        error_log("Rendering submission ID: {$submission_id}");
        error_log("Serialized Uploaded Files: " . $submission->uploaded_files);
        error_log("Notes: " . $submission->notes);

        // Handle form submission to update grade/status
        if ( isset($_POST['fa_grade_submission']) ) {
            $new_grade  = floatval($_POST['grade']);
            $new_status = 'graded';

            // If grade >= 75, mark as passed
            if ( $new_grade >= 75 ) {
                $new_status = 'passed';
            }

            // Update the submission record
            $update_result = $wpdb->update(
                $submission_table,
                array(
                    'grade'  => $new_grade,
                    'status' => $new_status
                ),
                array( 'id' => $submission_id ),
                array(
                    '%f',
                    '%s',
                ),
                array( '%d' )
            );

            if ( false === $update_result ) {
                error_log("Failed to update submission ID {$submission_id}");
                wp_die(__('Failed to update the submission. Please try again.', 'fashion-academy-lms'));
            }

            // If passed, unlock the next lesson
            if ( $new_status === 'passed' ) {
                $this->unlock_next_lesson($submission->user_id, $submission->lesson_id);
            }

            // Redirect to avoid form resubmission
            wp_redirect( add_query_arg(
                array(
                    'page'   => 'fa-submissions',
                    'action' => 'view',
                    'id'     => $submission_id,
                    'updated' => 'true'
                ),
                admin_url('admin.php')
            ) );
            exit;
        }

        // Re-fetch the submission after potential updates
        $submission = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $submission_table WHERE id = %d", $submission_id) );

        // Unserialize the uploaded_files
        $uploaded_files = maybe_unserialize($submission->uploaded_files);
        $notes          = $submission->notes;

        // Fetch user info
        $user_info = get_userdata($submission->user_id);
        $user_display = $user_info ? esc_html($user_info->display_name) . ' (' . esc_html($user_info->user_email) . ')' : __('Unknown', 'fashion-academy-lms');

        // Fetch course and lesson info
        $course = get_post($submission->course_id);
        $course_title = $course ? esc_html($course->post_title) : __('Unknown', 'fashion-academy-lms');

        $lesson = get_post($submission->lesson_id);
        $lesson_title = $lesson ? esc_html($lesson->post_title) : __('Unknown', 'fashion-academy-lms');

        // Begin rendering the submission details
        echo '<div class="wrap">';
        echo '<h1>' . __('View / Grade Submission', 'fashion-academy-lms') . ' #' . esc_html($submission->id) . '</h1>';

        // Display success notice if updated
        if ( isset($_GET['updated']) ) {
            echo '<div class="notice notice-success"><p>' . __('Submission Updated!', 'fashion-academy-lms') . '</p></div>';
        }

        // Display submission details
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>' . __('User', 'fashion-academy-lms') . '</th><td>' . $user_display . '</td></tr>';
        echo '<tr><th>' . __('Course', 'fashion-academy-lms') . '</th><td>' . $course_title . '</td></tr>';
        echo '<tr><th>' . __('Lesson', 'fashion-academy-lms') . '</th><td>' . $lesson_title . '</td></tr>';
        echo '<tr><th>' . __('Status', 'fashion-academy-lms') . '</th><td>' . esc_html(ucfirst($submission->status)) . '</td></tr>';
        echo '<tr><th>' . __('Grade', 'fashion-academy-lms') . '</th><td>' . esc_html($submission->grade) . '</td></tr>';
        echo '<tr><th>' . __('Submission Date', 'fashion-academy-lms') . '</th><td>' . esc_html($submission->submission_date) . '</td></tr>';
        echo '</tbody></table>';

        // Display Uploaded Files
        if ( ! empty($uploaded_files) && is_array($uploaded_files) ) {
            echo '<h2>' . __('Uploaded Files', 'fashion-academy-lms') . '</h2><ul>';
            foreach ( $uploaded_files as $file_url ) {
                echo '<li><a href="' . esc_url($file_url) . '" target="_blank">' . basename($file_url) . '</a></li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . __('No files uploaded.', 'fashion-academy-lms') . '</p>';
        }

        // Display Notes
        if ( ! empty($notes) ) {
            echo '<h2>' . __('Notes', 'fashion-academy-lms') . '</h2><p>' . esc_html($notes) . '</p>';
        }

        // Form to update grade
        echo '<h2>' . __('Grade Submission', 'fashion-academy-lms') . '</h2>';
        echo '<form method="post">';
        echo '<p><label for="grade">' . __('Grade (%): ', 'fashion-academy-lms') . '</label>';
        echo '<input type="number" name="grade" id="grade" value="' . esc_attr($submission->grade) . '" step="1" min="0" max="100" required /></p>';
        echo '<input type="hidden" name="fa_grade_submission" value="true" />';
        submit_button(__('Save Grade', 'fashion-academy-lms'));
        echo '</form>';

        echo '</div>';
    }
}
