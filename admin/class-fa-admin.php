<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FA_Admin {

    public function __construct() {
        // Hook for adding admin menus/pages
        add_action('admin_menu', array($this, 'add_admin_pages'));

        // Hook into post deletion to handle Module deletions
        add_action('before_delete_post', array($this, 'handle_module_deletion'));

        // Additional hooks can be added here if needed
    }


    public function add_admin_pages() {
        // Main "Fashion Academy" menu
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
            'fa-dashboard',
            __('Submissions', 'fashion-academy-lms'),
            __('Submissions', 'fashion-academy-lms'),
            'manage_options',
            'fa-submissions',
            array($this, 'render_submissions_page')
        );
    }

    /**
     * Render the main Dashboard page
     */
    public function render_dashboard() {
        echo '<div class="wrap"><h1>' . __('Fashion Academy Dashboard', 'fashion-academy-lms') . '</h1></div>';
    }

    /**
     * Render the Submissions admin page
     */
    public function render_submissions_page() {
        // Handle actions like "view" or "grade" by checking $_GET
        if ( isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id']) ) {
            $this->render_single_submission( intval($_GET['id']) );
            return;
        }

        global $wpdb;
        $submission_table = $wpdb->prefix . 'homework_submissions';

        // Get submissions, optionally filter by status and module
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $module_filter = isset($_GET['module']) ? intval($_GET['module']) : 0;

        $query = "SELECT * FROM $submission_table";
        $where = array();
        $params = array();

        if ( ! empty($status_filter) ) {
            $where[] = "status = %s";
            $params[] = $status_filter;
        }

        if ( ! empty($module_filter) ) {
            // To filter by module, we need to join with the lessons to get their module assignments
            // Assuming that 'lesson_module_id' is stored as post meta for lessons
            // This requires a subquery to get lesson IDs that belong to the selected module

            $lesson_ids = $wpdb->get_col( $wpdb->prepare(
                "
                SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'lesson'
                AND ID IN (
                    SELECT post_id FROM {$wpdb->postmeta}
                    WHERE meta_key = '_fa_lesson_module_id'
                    AND meta_value = %d
                )
                ",
                $module_filter
            ) );

            if ( ! empty($lesson_ids) ) {
                $placeholders = implode( ',', array_fill( 0, count($lesson_ids), '%d' ) );
                $where[] = "lesson_id IN ($placeholders)";
                $params = array_merge( $params, $lesson_ids );
            } else {
                // If no lessons are found for the module, ensure no results are returned
                $where[] = "1=0";
            }
        }

        if ( ! empty( $where ) ) {
            $query .= " WHERE " . implode( ' AND ', $where );
        }

        $query .= " ORDER BY submission_date DESC";

        // Prepare the query with parameters
        $submissions = $wpdb->get_results( $wpdb->prepare( $query, $params ) );

        echo '<div class="wrap"><h1>' . __('Homework Submissions', 'fashion-academy-lms') . '</h1>';

        // Add filter form
        echo '<form method="get" style="margin-bottom: 20px;">';
        echo '<input type="hidden" name="page" value="fa-submissions" />';

        // Status Filter
        echo '<label for="status_filter">' . __('Filter by Status:', 'fashion-academy-lms') . '</label> ';
        echo '<select name="status" id="status_filter" style="margin-right: 10px;">';
        echo '<option value="">' . __('-- All --', 'fashion-academy-lms') . '</option>';
        echo '<option value="pending"' . selected( $status_filter, 'pending', false ) . '>' . __('Pending', 'fashion-academy-lms') . '</option>';
        echo '<option value="graded"' . selected( $status_filter, 'graded', false ) . '>' . __('Graded', 'fashion-academy-lms') . '</option>';
        echo '<option value="passed"' . selected( $status_filter, 'passed', false ) . '>' . __('Passed', 'fashion-academy-lms') . '</option>';
        echo '</select>';

        // Module Filter
        echo '<label for="module_filter">' . __('Filter by Module:', 'fashion-academy-lms') . '</label> ';
        echo '<select name="module" id="module_filter" style="margin-right: 10px;">';
        echo '<option value="0">' . __('-- All Modules --', 'fashion-academy-lms') . '</option>';
        // Fetch all modules
        $modules = get_posts(array(
            'post_type'      => 'module',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'meta_value_num',
            'meta_key'       => 'module_order',
            'order'          => 'ASC'
        ));
        if ( $modules ) {
            foreach ( $modules as $module ) {
                echo '<option value="' . esc_attr( $module->ID ) . '"' . selected( $module_filter, $module->ID, false ) . '>' . esc_html( $module->post_title ) . '</option>';
            }
        }
        echo '</select>';

        submit_button( __('Filter', 'fashion-academy-lms'), 'secondary', 'submit', false );
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
                    <th>' . __('Module', 'fashion-academy-lms') . '</th>
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

            // Get Module information from Lesson
            $module_id = get_post_meta( $submission->lesson_id, '_fa_lesson_module_id', true );
            if ( $module_id ) {
                $module = get_post( $module_id );
                $module_title = $module ? esc_html( $module->post_title ) : __('Unknown', 'fashion-academy-lms');
            } else {
                $module_title = __('Not Assigned', 'fashion-academy-lms');
            }

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
            echo '<td>' . $module_title . '</td>';
            echo '<td>' . $lesson_title . '</td>';
            echo '<td>' . esc_html( ucfirst( $submission->status ) ) . '</td>';
            echo '<td>' . esc_html( $submission->grade ) . '</td>';
            echo '<td>' . esc_html( $submission->submission_date ) . '</td>';
            echo '<td><a class="button" href="' . esc_url( $view_url ) . '">' . __('View / Grade', 'fashion-academy-lms') . '</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    /**
     * Render a single submission for viewing and grading
     *
     * @param int $submission_id
     */
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
        fa_plugin_log("Rendering submission ID: {$submission_id}");
        fa_plugin_log("Serialized Uploaded Files: " . $submission->uploaded_files);
        fa_plugin_log("Notes: " . $submission->notes);

        // Handle form submission to update grade/status
        if ( isset($_POST['fa_grade_submission']) ) {
            $new_grade  = floatval($_POST['grade']);
            $new_status = 'graded';

            // Define passing threshold
            $passing_grade = 75;

            // If grade >= passing_grade, mark as passed
            if ( $new_grade >= $passing_grade ) {
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
                fa_plugin_log("Failed to update submission ID {$submission_id}");
                wp_die(__('Failed to update the submission. Please try again.', 'fashion-academy-lms'));
            }

            // If passed, first mark the *current* lesson as 'passed'
            if ($new_status === 'passed') {
                // 1) Mark the current lesson's progress as 'passed'
                $progress_table = $wpdb->prefix . 'course_progress';

                // Check if there's an existing record for this user+lesson
                $existing_progress = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM $progress_table WHERE user_id = %d AND lesson_id = %d",
                        $submission->user_id,
                        $submission->lesson_id
                    )
                );

                if ($existing_progress) {
                    // Update existing record
                    $wpdb->update(
                        $progress_table,
                        array('progress_status' => 'passed'),
                        array('id' => $existing_progress->id),
                        array(
                            '%s'
                        ),
                        array(
                            '%d'
                        )
                    );
                    fa_plugin_log("Set lesson ID {$submission->lesson_id} as 'passed' for user ID {$submission->user_id}. (updated existing row)");
                } else {
                    // Insert new record
                    $wpdb->insert(
                        $progress_table,
                        array(
                            'user_id'         => $submission->user_id,
                            'course_id'       => $submission->course_id,
                            'lesson_id'       => $submission->lesson_id,
                            'progress_status' => 'passed'
                        ),
                        array(
                            '%d',
                            '%d',
                            '%d',
                            '%s'
                        )
                    );
                    fa_plugin_log("Set lesson ID {$submission->lesson_id} as 'passed' for user ID {$submission->user_id}. (inserted new row)");
                }

                // 2) Now unlock the *next* lesson
                $this->unlock_next_lesson($submission->user_id, $submission->lesson_id);
            }

            // Redirect to avoid form resubmission
            wp_redirect( add_query_arg(
                array(
                    'page'     => 'fa-submissions',
                    'action'   => 'view',
                    'id'       => $submission_id,
                    'updated'  => 'true'
                ),
                admin_url('admin.php')
            ) );
            exit;
        }

        // Re-fetch the submission after potential updates
        $submission = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $submission_table WHERE id = %d", $submission_id) );

        // Unserialize the uploaded_files
        $uploaded_files = json_decode($submission->uploaded_files, true);
        $notes          = $submission->notes;

        // Fetch user info
        $user_info = get_userdata($submission->user_id);
        $user_display = $user_info ? esc_html($user_info->display_name) . ' (' . esc_html($user_info->user_email) . ')' : __('Unknown', 'fashion-academy-lms');

        // Fetch course and lesson info
        $course = get_post($submission->course_id);
        $course_title = $course ? esc_html($course->post_title) : __('Unknown', 'fashion-academy-lms');

        $lesson = get_post($submission->lesson_id);
        $lesson_title = $lesson ? esc_html($lesson->post_title) : __('Unknown', 'fashion-academy-lms');

        // Get Module information from Lesson
        $module_id = get_post_meta( $submission->lesson_id, '_fa_lesson_module_id', true );
        if ( $module_id ) {
            $module = get_post( $module_id );
            $module_title = $module ? esc_html( $module->post_title ) : __('Unknown', 'fashion-academy-lms');
        } else {
            $module_title = __('Not Assigned', 'fashion-academy-lms');
        }

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
        echo '<tr><th>' . __('Module', 'fashion-academy-lms') . '</th><td>' . $module_title . '</td></tr>';
        echo '<tr><th>' . __('Lesson', 'fashion-academy-lms') . '</th><td>' . $lesson_title . '</td></tr>';
        echo '<tr><th>' . __('Status', 'fashion-academy-lms') . '</th><td>' . esc_html( ucfirst( $submission->status ) ) . '</td></tr>';
        echo '<tr><th>' . __('Grade', 'fashion-academy-lms') . '</th><td>' . esc_html( $submission->grade ) . '</td></tr>';
        echo '<tr><th>' . __('Submission Date', 'fashion-academy-lms') . '</th><td>' . esc_html( $submission->submission_date ) . '</td></tr>';
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

    /**
     * Unlock the next lesson for the user based on the current lesson's Module
     *
     * @param int $user_id
     * @param int $current_lesson_id
     */
    public function unlock_next_lesson($user_id, $current_lesson_id) {
        global $wpdb;
        $progress_table = $wpdb->prefix . 'course_progress';

        // Fetch the current lesson's order and module ID
        $current_lesson = get_post($current_lesson_id);
        if (!$current_lesson) {
            fa_plugin_log("Current lesson ID $current_lesson_id not found.");
            return;
        }

        $current_order = get_post_meta($current_lesson_id, 'lesson_order', true);
        $module_id     = get_post_meta($current_lesson_id, '_fa_lesson_module_id', true);

        if (!$module_id || !$current_order) {
            fa_plugin_log("Missing module ID or lesson order for lesson ID $current_lesson_id.");
            return;
        }

        // Fetch the next lesson in the same Module based on order
        $next_lesson = get_posts(array(
            'post_type'      => 'lesson',
            'posts_per_page' => 1,
            'meta_key'       => 'lesson_order',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'meta_query'     => array(
                array(
                    'key'     => '_fa_lesson_module_id',
                    'value'   => $module_id,
                    'compare' => '='
                ),
                array(
                    'key'     => 'lesson_order',
                    'value'   => intval($current_order) + 1,
                    'compare' => '=',
                    'type'    => 'NUMERIC'
                )
            )
        ));

        if (empty($next_lesson)) {
            fa_plugin_log("No next lesson found for module ID $module_id after lesson order $current_order.");
            return;
        }

        $next_lesson_id = $next_lesson[0]->ID;

        // Check if progress already exists for the next lesson
        $existing_progress = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $progress_table WHERE user_id = %d AND lesson_id = %d",
                $user_id,
                $next_lesson_id
            )
        );

        if ($existing_progress) {
            // Update the existing progress status to 'incomplete' (or 'unlocked')
            $wpdb->update(
                $progress_table,
                array(
                    'progress_status' => 'incomplete' // or 'unlocked'
                ),
                array(
                    'id' => $existing_progress->id
                ),
                array(
                    '%s'
                ),
                array(
                    '%d'
                )
            );
            fa_plugin_log("Updated progress for user ID $user_id to unlock lesson ID $next_lesson_id.");
        } else {
            // Insert new progress record
            $wpdb->insert(
                $progress_table,
                array(
                    'user_id'         => $user_id,
                    'course_id'       => get_post_meta($next_lesson_id, 'lesson_course_id', true) ?: 0,
                    'lesson_id'       => $next_lesson_id,
                    'progress_status' => 'incomplete' // or 'unlocked'
                ),
                array(
                    '%d',
                    '%d',
                    '%d',
                    '%s'
                )
            );
            fa_plugin_log("Inserted progress for user ID $user_id to unlock lesson ID $next_lesson_id.");
        }

        // Optional: Notify the user via email
        $user_info = get_userdata($user_id);
        if ($user_info && !empty($user_info->user_email)) {
            $next_lesson_title = get_the_title($next_lesson_id);
            $next_lesson_url = get_permalink($next_lesson_id);

            $subject = __('New Lesson Unlocked!', 'fashion-academy-lms');
            $message = sprintf(
                __('Congratulations! You have unlocked the next lesson: %s. You can access it here: %s', 'fashion-academy-lms'),
                $next_lesson_title,
                $next_lesson_url
            );

            wp_mail($user_info->user_email, $subject, $message);
            fa_plugin_log("Notification email sent to user ID $user_id for lesson ID $next_lesson_id.");
        }
    }

    /**
     * Handle Module Deletion by Unassigning it from Lessons
     *
     * @param int $post_id
     */
    public function handle_module_deletion($post_id) {
        $post = get_post($post_id);
        if ($post->post_type !== 'module') {
            return;
        }

        // Fetch all Lessons assigned to this Module
        $lessons = get_posts(array(
            'post_type'      => 'lesson',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => '_fa_lesson_module_id',
                    'value'   => $post_id,
                    'compare' => '='
                )
            )
        ));

        // Unassign the Module from these Lessons
        foreach ($lessons as $lesson) {
            update_post_meta($lesson->ID, '_fa_lesson_module_id', '');
        }

        fa_plugin_log("Module ID $post_id deleted. Unassigned from " . count($lessons) . " Lessons.");
    }

}
?>
