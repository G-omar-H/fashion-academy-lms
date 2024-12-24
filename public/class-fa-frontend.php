<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FA_Frontend {

    public function __construct() {
        // Register the shortcode
        add_shortcode('fa_homework_form', array($this, 'render_homework_form'));
        
        // Process form submissions
        add_action('init', array($this, 'handle_homework_submission'));
    }

    /**
     * 1) Render the homework form on the front end
     */
    public function render_homework_form() {
        // Ensure user is logged in
        if ( ! is_user_logged_in() ) {
            return '<p>You must be logged in to submit homework.</p>';
        }

        // If this page is a lesson, let's retrieve the lesson ID & course ID
        // If you're placing this shortcode on the "Lesson" post type view:
        $lesson_id = get_the_ID();
        // We stored the course ID in lesson meta as 'lesson_course_id'
        $course_id = get_post_meta($lesson_id, 'lesson_course_id', true);

        // Build the form HTML
        ob_start();
        ?>
        <form method="post" enctype="multipart/form-data" class="fa-homework-form">
            <input type="hidden" name="fa_action" value="submit_homework" />
            <input type="hidden" name="lesson_id" value="<?php echo esc_attr($lesson_id); ?>" />
            <input type="hidden" name="course_id" value="<?php echo esc_attr($course_id); ?>" />

            <p>
                <label for="homework_files">Upload your homework (images, PDFs, etc.):</label><br>
                <input type="file" name="homework_files[]" multiple="multiple" />
            </p>

            <p>
                <label for="homework_notes">Any notes or comments:</label><br>
                <textarea name="homework_notes" id="homework_notes" rows="4" cols="50"></textarea>
            </p>

            <p>
                <input type="submit" value="Submit Homework" />
            </p>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * 2) Handle form submission (runs on 'init')
     */
    public function handle_homework_submission() {
        if ( isset($_POST['fa_action']) && $_POST['fa_action'] === 'submit_homework' ) {

            // Must be logged in
            if ( ! is_user_logged_in() ) return;

            // Basic nonce check (optional but recommended)
            // We'll skip nonce for brevity, but in production you'd use wp_verify_nonce

            $user_id   = get_current_user_id();
            $lesson_id = !empty($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
            $course_id = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
            $notes     = !empty($_POST['homework_notes']) ? sanitize_textarea_field($_POST['homework_notes']) : '';

            // Make sure we have a lesson & course
            if ( ! $lesson_id || ! $course_id ) return;

            // Handle file uploads (if any)
            $uploaded_files = array();
            if ( !empty($_FILES['homework_files']['name'][0]) ) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');

                $file_count = count($_FILES['homework_files']['name']);
                for ($i = 0; $i < $file_count; $i++) {
                    $file = array(
                        'name'     => $_FILES['homework_files']['name'][$i],
                        'type'     => $_FILES['homework_files']['type'][$i],
                        'tmp_name' => $_FILES['homework_files']['tmp_name'][$i],
                        'error'    => $_FILES['homework_files']['error'][$i],
                        'size'     => $_FILES['homework_files']['size'][$i],
                    );

                    $upload_overrides = array( 'test_form' => false );
                    $movefile = wp_handle_upload($file, $upload_overrides);
                    
                    if ($movefile && ! isset($movefile['error'])) {
                        // Store the file URL in our array
                        $uploaded_files[] = $movefile['url'];
                    } 
                    // else you could handle errors
                }
            }

            // Now let's insert a row in the homework_submissions table
            global $wpdb;
            $table = $wpdb->prefix . 'homework_submissions';

            // We'll keep it simple and store file URLs as JSON in a new column or in notes column
            // But let's assume we didn't add a separate column for multiple file URLs.
            // If so, you could store them in post meta or a second table.
            // For demonstration, let's assume we just store them as a note with links:

            $files_note = '';
            if (!empty($uploaded_files)) {
                $files_note .= "Uploaded files:\n";
                foreach ($uploaded_files as $url) {
                    $files_note .= $url . "\n";
                }
            }

            $final_notes = $notes . "\n" . $files_note;

            $wpdb->insert($table, array(
                'user_id'         => $user_id,
                'course_id'       => $course_id,
                'lesson_id'       => $lesson_id,
                'submission_date' => current_time('mysql'),
                'status'          => 'pending',
                'grade'           => 0, // default
                // If you added a "feedback" column, you can store notes there, but let's keep it simple for now
            ));

            $insert_id = $wpdb->insert_id;

            // If you want to store the final_notes somewhere:
            //   1) Add a 'notes' TEXT column to your DB table, or
            //   2) Store notes in a separate meta table, or
            //   3) For demonstration, we skip it or store them in post meta with a unique key
            //      because the current table schema doesn't have a 'notes' column.

            add_post_meta($lesson_id, 'fa_submission_notes_'.$insert_id, $final_notes);

            // Optionally, redirect or show a success message
            wp_redirect(add_query_arg('homework_submitted', 'true', get_permalink($lesson_id)));
            exit;
        }
    }
}
