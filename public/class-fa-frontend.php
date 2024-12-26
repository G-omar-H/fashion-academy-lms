<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FA_Frontend {

    public function __construct() {
        // Register the shortcode
        add_shortcode('fa_homework_form', array($this, 'render_homework_form'));

        // Process form submissions
        add_action('init', array($this, 'handle_homework_submission'));

        // Restrict lesson access
        add_action('template_redirect', array($this, 'restrict_lesson_access'));
    }


    /**
     * 1) Render the homework form on the front end
     */
    public function render_homework_form() {
        // Ensure user is logged in
        if ( ! is_user_logged_in() ) {
            return '<p>' . __('You must be logged in to submit homework.', 'fashion-academy-lms') . '</p>';
        }

        // Retrieve the current lesson ID and course ID
        $lesson_id = get_the_ID();
        $course_id = get_post_meta($lesson_id, 'lesson_course_id', true);

        // Fetch any existing submissions by the user for this lesson
        global $wpdb;
        $submission_table = $wpdb->prefix . 'homework_submissions';
        $existing_submission = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $submission_table WHERE user_id = %d AND lesson_id = %d ORDER BY submission_date DESC LIMIT 1",
                get_current_user_id(),
                $lesson_id
            )
        );

        // If a submission exists, fetch uploaded files and notes directly from the table
        $uploaded_files = $existing_submission ? json_decode($existing_submission->uploaded_files, true) : array();
        $notes          = $existing_submission ? esc_textarea($existing_submission->notes) : '';

        // Build the form HTML with file preview and removal option
        ob_start();
        ?>
        <form method="post" enctype="multipart/form-data" class="fa-homework-form" id="fa-homework-form">
            <?php wp_nonce_field('fa_homework_submission', 'fa_homework_nonce'); ?>
            <input type="hidden" name="fa_action" value="submit_homework" />
            <input type="hidden" name="lesson_id" value="<?php echo esc_attr($lesson_id); ?>" />
            <input type="hidden" name="course_id" value="<?php echo esc_attr($course_id); ?>" />

            <p>
                <label for="homework_files"><?php _e('Upload your homework (images, PDFs, etc.):', 'fashion-academy-lms'); ?></label><br>
                <input type="file" name="homework_files[]" id="homework_files" multiple="multiple" accept=".jpg,.jpeg,.png,.pdf" />
            </p>

            <div id="file_preview">
                <?php if ( ! empty($uploaded_files) && is_array($uploaded_files) ) : ?>
                    <?php foreach ($uploaded_files as $index => $file_url) : ?>
                        <div class="fa-file-preview">
                            <span><?php echo esc_html(basename($file_url)); ?></span>
                            <button type="button" class="fa-remove-file" data-index="<?php echo esc_attr($index); ?>">Remove</button>
                            <input type="hidden" name="existing_files[]" value="<?php echo esc_attr($file_url); ?>" />
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <p>
                <label for="homework_notes"><?php _e('Any notes or comments:', 'fashion-academy-lms'); ?></label><br>
                <textarea name="homework_notes" id="homework_notes" rows="4" cols="50"><?php echo esc_textarea($notes); ?></textarea>
            </p>

            <p>
                <input type="submit" value="<?php _e('Submit Homework', 'fashion-academy-lms'); ?>" />
            </p>
        </form>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const fileInput = document.getElementById('homework_files');
                const filePreview = document.getElementById('file_preview');

                // Initialize a DataTransfer object to manage the files
                const dt = new DataTransfer();

                // Handle new file selections
                fileInput.addEventListener('change', function () {
                    const files = Array.from(this.files);

                    files.forEach((file) => {
                        // Check for duplicates in the DataTransfer
                        const duplicate = Array.from(dt.files).some(
                            (f) =>
                                f.name === file.name &&
                                f.size === file.size &&
                                f.lastModified === file.lastModified
                        );

                        if (!duplicate) {
                            // Add the file to the DataTransfer
                            dt.items.add(file);

                            // Append the preview
                            const fileDiv = document.createElement('div');
                            fileDiv.className = 'fa-file-preview';

                            const fileName = document.createElement('span');
                            fileName.textContent = file.name;
                            fileDiv.appendChild(fileName);

                            const removeButton = document.createElement('button');
                            removeButton.type = 'button';
                            removeButton.className = 'fa-remove-file';
                            removeButton.textContent = 'Remove';

                            // Attach event listener to remove files
                            removeButton.addEventListener('click', function () {
                                const fileIndex = Array.from(dt.files).findIndex(
                                    (f) =>
                                        f.name === file.name &&
                                        f.size === file.size &&
                                        f.lastModified === file.lastModified
                                );

                                if (fileIndex > -1) {
                                    dt.items.remove(fileIndex); // Remove from DataTransfer
                                    fileInput.files = dt.files; // Update input's FileList
                                    fileDiv.remove(); // Remove preview
                                }
                            });

                            fileDiv.appendChild(removeButton);
                            filePreview.appendChild(fileDiv);
                        }
                    });

                    // Sync DataTransfer with file input
                    fileInput.files = dt.files;

                    // Clear the file input value to allow re-selecting the same file
                    this.value = '';
                });

                // Ensure files are properly synced before form submission
                const form = document.getElementById('fa-homework-form');
                if (form) {
                    form.addEventListener('submit', function () {
                        fileInput.files = dt.files; // Update file input with DataTransfer files
                    });
                }
            });
        </script>



        <style>
            .fa-file-preview {
                display: flex;
                align-items: center;
                margin-bottom: 10px;
            }

            .fa-file-preview span {
                flex: 1;
            }

            .fa-remove-file {
                padding: 2px 5px;
                background-color: #dc3545;
                color: #fff;
                border: none;
                cursor: pointer;
                border-radius: 3px;
                margin-left: 10px;
                font-size: 0.9em;
            }

            .fa-remove-file:hover {
                background-color: #c82333;
            }
            .fa-success-message {
                padding: 10px;
                background-color: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
                border-radius: 4px;
                margin-bottom: 15px;
            }
        </style>
        <?php

        if ( isset($_GET['homework_submitted']) && $_GET['homework_submitted'] === 'true' ) {
            echo '<p class="fa-success-message">' . __('Your homework has been submitted successfully!', 'fashion-academy-lms') . '</p>';
        }

        return ob_get_clean();
    }

    /**
     * 2) Handle form submission (runs on 'init')
     */
    public function handle_homework_submission() {
        if ( isset($_POST['fa_action']) && $_POST['fa_action'] === 'submit_homework' ) {

            // Log the $_FILES array for debugging
            fa_plugin_log("Homework Files: " . print_r($_FILES['homework_files'], true));

            // Verify nonce for security
            if ( ! isset($_POST['fa_homework_nonce']) || ! wp_verify_nonce($_POST['fa_homework_nonce'], 'fa_homework_submission') ) {
                wp_die(__('Security check failed.', 'fashion-academy-lms'));
            }

            // Ensure the user is logged in
            if ( ! is_user_logged_in() ) return;

            $user_id   = get_current_user_id();
            $lesson_id = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
            $course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
            $notes     = isset($_POST['homework_notes']) ? sanitize_textarea_field($_POST['homework_notes']) : '';

            // Validate lesson and course IDs
            if ( ! $lesson_id || ! $course_id ) {
                fa_plugin_log("Invalid submission data: lesson_id = $lesson_id, course_id = $course_id");
                wp_die(__('Invalid submission data.', 'fashion-academy-lms'));
            }

            // Handle existing files (from previous submissions)
            $existing_files = isset($_POST['existing_files']) ? array_map('esc_url_raw', $_POST['existing_files']) : array();

            // Handle new file uploads
            $uploaded_files = array();
            if (empty($_FILES['homework_files']['name'][0])) {
                fa_plugin_log("No files were uploaded or the file input is empty.");
            } else {
                fa_plugin_log("Files received: " . print_r($_FILES['homework_files'], true));
            }

            if (isset($_FILES['homework_files']['error'][0])) {
                switch ($_FILES['homework_files']['error'][0]) {
                    case UPLOAD_ERR_OK:
                        fa_plugin_log("File uploaded successfully.");
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        fa_plugin_log("No file was uploaded.");
                        break;
                    case UPLOAD_ERR_INI_SIZE:
                        fa_plugin_log("File exceeds the upload_max_filesize directive in php.ini.");
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        fa_plugin_log("File exceeds the MAX_FILE_SIZE directive in the HTML form.");
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        fa_plugin_log("File was only partially uploaded.");
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        fa_plugin_log("Missing temporary folder.");
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        fa_plugin_log("Failed to write file to disk.");
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        fa_plugin_log("A PHP extension stopped the file upload.");
                        break;
                    default:
                        fa_plugin_log("Unknown upload error.");
                        break;
                }
            }

            if ( ! empty($_FILES['homework_files']['name'][0]) ) {
                require_once( ABSPATH . 'wp-admin/includes/file.php' );

                $allowed_types = array('image/jpeg', 'image/png', 'application/pdf');
                $max_size = 5 * 1024 * 1024; // 5 MB per file

                $file_count = count($_FILES['homework_files']['name']);
                for ($i = 0; $i < $file_count; $i++) {
                    // Check for upload errors
                    if ($_FILES['homework_files']['error'][$i] !== UPLOAD_ERR_OK) {
                        fa_plugin_log("File upload error for file index $i: " . $_FILES['homework_files']['error'][$i]);
                        continue; // Skip this file
                    }

                    // Validate file type
                    if ( ! in_array($_FILES['homework_files']['type'][$i], $allowed_types) ) {
                        fa_plugin_log("Invalid file type for file: " . $_FILES['homework_files']['name'][$i]);
                        continue; // Skip invalid file types
                    }

                    // Validate file size
                    if ( $_FILES['homework_files']['size'][$i] > $max_size ) {
                        fa_plugin_log("File size exceeded for file: " . $_FILES['homework_files']['name'][$i]);
                        continue; // Skip large files
                    }

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
                        // Store the file URL
                        $uploaded_files[] = esc_url_raw($movefile['url']);
                        fa_plugin_log("File uploaded successfully: " . $movefile['url']);
                    } else {
                        fa_plugin_log("File upload failed for file: " . $_FILES['homework_files']['name'][$i] . " Error: " . $movefile['error']);
                    }
                }
            }

            // Combine existing and new files
            $all_files = array_merge($existing_files, $uploaded_files);

            // Encode the files array as JSON for storage
            $json_files = wp_json_encode($all_files);

            // Log the JSON data for debugging
            fa_plugin_log("JSON Uploaded Files: " . $json_files);

            // Insert or update the submission in the database
            global $wpdb;
            $submission_table = $wpdb->prefix . 'homework_submissions';

            // Check if a submission already exists for this user and lesson
            $existing_submission = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $submission_table WHERE user_id = %d AND lesson_id = %d ORDER BY submission_date DESC LIMIT 1",
                    $user_id,
                    $lesson_id
                )
            );

            if ( $existing_submission ) {
                // Update the existing submission
                $update_result = $wpdb->update(
                    $submission_table,
                    array(
                        'submission_date' => current_time('mysql'),
                        'status'          => 'pending',
                        'grade'           => 0, // Reset grade
                        'uploaded_files'  => $json_files,
                        'notes'           => $notes,
                    ),
                    array( 'id' => $existing_submission->id ),
                    array(
                        '%s',
                        '%s',
                        '%f',
                        '%s',
                        '%s',
                    ),
                    array( '%d' )
                );

                if ( false === $update_result ) {
                    fa_plugin_log("Failed to update submission ID {$existing_submission->id}");
                    wp_die(__('Failed to update your submission. Please try again.', 'fashion-academy-lms'));
                }

                $submission_id = $existing_submission->id;
                fa_plugin_log("Updated submission ID: {$submission_id}");
            } else {
                // Insert a new submission
                $insert_result = $wpdb->insert(
                    $submission_table,
                    array(
                        'user_id'         => $user_id,
                        'course_id'       => $course_id,
                        'lesson_id'       => $lesson_id,
                        'submission_date' => current_time('mysql'),
                        'status'          => 'pending',
                        'grade'           => 0, // default
                        'uploaded_files'  => $json_files,
                        'notes'           => $notes,
                    ),
                    array(
                        '%d',
                        '%d',
                        '%d',
                        '%s',
                        '%s',
                        '%f',
                        '%s',
                        '%s',
                    )
                );

                if ( false === $insert_result ) {
                    fa_plugin_log("Failed to insert new submission for user ID $user_id, lesson ID $lesson_id");
                    wp_die(__('Failed to submit your homework. Please try again.', 'fashion-academy-lms'));
                }

                $submission_id = $wpdb->insert_id;
                fa_plugin_log("Inserted new submission ID: {$submission_id}");
            }

            // Optional: Log successful submission
            fa_plugin_log("Homework submission successful. Submission ID: $submission_id");

            // Redirect with a success message
            wp_redirect(add_query_arg('homework_submitted', 'true', get_permalink($lesson_id)));
            exit;
        }
    }

    /**
     * Restrict access to lessons based on user's progress
     */
    public function restrict_lesson_access() {
        if ( ! is_singular('lesson') ) {
            return; // Only restrict single lesson pages
        }

        if ( ! is_user_logged_in() ) {
            // Redirect non-logged-in users to login page
            wp_redirect(wp_login_url(get_permalink()));
            exit;
        }

        global $post, $wpdb;
        $user_id = get_current_user_id();
        $lesson_id = $post->ID;

        // Fetch course ID from lesson meta
        $course_id = get_post_meta($lesson_id, 'lesson_course_id', true);

        if ( !$course_id ) {
            // If no course ID is associated, allow access
            return;
        }

        // Fetch lesson order
        $current_order = get_post_meta($lesson_id, 'lesson_order', true);
        if (!$current_order) {
            // If no lesson order is set, allow access
            return;
        }

        // Fetch all lessons in the course up to the current one
        $required_lessons = get_posts(array(
            'post_type'      => 'lesson',
            'posts_per_page' => -1,
            'meta_key'       => 'lesson_order',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'meta_query'     => array(
                array(
                    'key'     => 'lesson_course_id',
                    'value'   => $course_id,
                    'compare' => '='
                ),
                array(
                    'key'     => 'lesson_order',
                    'value'   => intval($current_order) - 1,
                    'compare' => '<=',
                    'type'    => 'NUMERIC'
                )
            )
        ));

        // Check if all required lessons are marked as 'passed' in course_progress
        foreach ($required_lessons as $lesson) {
            $progress = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT progress_status FROM {$wpdb->prefix}course_progress WHERE user_id = %d AND lesson_id = %d",
                    $user_id,
                    $lesson->ID
                )
            );

            if ( $progress !== 'passed' ) {
                // If any required lesson is not passed, restrict access
                // Set a transient to display a notice after redirection
                set_transient('fa_restricted_lesson_notice_' . $user_id, true, 30);
                wp_redirect(get_permalink($course_id)); // Redirect to course overview
                exit;
            }
        }

        // Display notice if set
        add_action('wp_footer', array($this, 'display_restricted_lesson_notice'));
    }

    /**
     * Display a notice to the user about restricted access
     */
    public function display_restricted_lesson_notice() {
        if ( ! is_user_logged_in() ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( get_transient('fa_restricted_lesson_notice_' . $user_id) ) {
            echo '<div class="notice notice-error is-dismissible fa-restricted-lesson-notice">
                    <p>' . __('You must complete the previous lessons to access this one.', 'fashion-academy-lms') . '</p>
                  </div>';
            delete_transient('fa_restricted_lesson_notice_' . $user_id);
        }
    }
}
?>
