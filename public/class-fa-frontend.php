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
        $uploaded_files = $existing_submission ? maybe_unserialize($existing_submission->uploaded_files) : array();
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
            document.addEventListener('DOMContentLoaded', function() {
                const fileInput = document.getElementById('homework_files');
                const filePreview = document.getElementById('file_preview');

                // Handle new file selections
                fileInput.addEventListener('change', function() {
                    const files = Array.from(this.files);
                    files.forEach((file, index) => {
                        const fileDiv = document.createElement('div');
                        fileDiv.className = 'fa-file-preview';

                        const fileName = document.createElement('span');
                        fileName.textContent = file.name;
                        fileDiv.appendChild(fileName);

                        const removeButton = document.createElement('button');
                        removeButton.type = 'button';
                        removeButton.className = 'fa-remove-file';
                        removeButton.textContent = 'Remove';
                        removeButton.dataset.index = 'new_' + index;
                        removeButton.addEventListener('click', function() {
                            // Remove the file from the input
                            const dt = new DataTransfer();
                            const updatedFiles = Array.from(fileInput.files).filter((_, i) => i !== index);
                            updatedFiles.forEach(f => dt.items.add(f));
                            fileInput.files = dt.files;
                            // Remove the preview
                            fileDiv.remove();
                        });
                        fileDiv.appendChild(removeButton);

                        filePreview.appendChild(fileDiv);
                    });

                    // Clear the file input to allow re-selection of the same file if needed
                    fileInput.value = '';
                });

                // Handle removal of existing files
                filePreview.addEventListener('click', function(e) {
                    if (e.target && e.target.classList.contains('fa-remove-file')) {
                        const index = e.target.dataset.index;
                        // Remove the corresponding hidden input
                        const hiddenInput = document.querySelector(`input[name="existing_files[]"][value="${e.target.previousElementSibling.value}"]`);
                        if (hiddenInput) {
                            hiddenInput.remove();
                        }
                        // Remove the preview div
                        e.target.parentElement.remove();
                    }
                });
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
        </style>
        <?php
        return ob_get_clean();
    }




    /**
     * 2) Handle form submission (runs on 'init')
     */
    public function handle_homework_submission() {
        if ( isset($_POST['fa_action']) && $_POST['fa_action'] === 'submit_homework' ) {

            // Log the $_FILES array
            error_log("$_FILES Homework Files: " . print_r($_FILES['homework_files'], true));

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
                wp_die(__('Invalid submission data.', 'fashion-academy-lms'));
            }

            // Handle existing files (from previous submissions)
            $existing_files = isset($_POST['existing_files']) ? array_map('esc_url_raw', $_POST['existing_files']) : array();

            // Handle new file uploads
            $uploaded_files = array();
            if ( ! empty($_FILES['homework_files']['name'][0]) ) {
                require_once( ABSPATH . 'wp-admin/includes/file.php' );

                $allowed_types = array('image/jpeg', 'image/png', 'application/pdf');
                $max_size = 5 * 1024 * 1024; // 5 MB per file

                $file_count = count($_FILES['homework_files']['name']);
                for ($i = 0; $i < $file_count; $i++) {
                    // Check for upload errors
                    if ($_FILES['homework_files']['error'][$i] !== UPLOAD_ERR_OK) {
                        error_log("File upload error for file index $i: " . $_FILES['homework_files']['error'][$i]);
                        continue; // Skip this file
                    }

                    // Validate file type
                    if ( ! in_array($_FILES['homework_files']['type'][$i], $allowed_types) ) {
                        error_log("Invalid file type for file: " . $_FILES['homework_files']['name'][$i]);
                        continue; // Skip invalid file types
                    }

                    // Validate file size
                    if ( $_FILES['homework_files']['size'][$i] > $max_size ) {
                        error_log("File size exceeded for file: " . $_FILES['homework_files']['name'][$i]);
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
                    } else {
                        error_log("File upload failed for file: " . $_FILES['homework_files']['name'][$i] . " Error: " . $movefile['error']);
                    }
                }
            }

            // Combine existing and new files
            $all_files = array_merge($existing_files, $uploaded_files);

            // Serialize the files array for storage
            $serialized_files = maybe_serialize($all_files);

            // Log the serialized data for debugging
            error_log("Serialized Uploaded Files: " . $serialized_files);

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
                        'uploaded_files'  => $serialized_files,
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
                    error_log("Failed to update submission ID {$existing_submission->id}");
                    wp_die(__('Failed to update your submission. Please try again.', 'fashion-academy-lms'));
                }

                $submission_id = $existing_submission->id;
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
                        'uploaded_files'  => $serialized_files,
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
                    error_log("Failed to insert new submission for user ID $user_id, lesson ID $lesson_id");
                    wp_die(__('Failed to submit your homework. Please try again.', 'fashion-academy-lms'));
                }

                $submission_id = $wpdb->insert_id;
            }

            // Optional: Log successful submission
            error_log("Homework submission successful. Submission ID: $submission_id");

            // Redirect with a success message
            wp_redirect(add_query_arg('homework_submitted', 'true', get_permalink($lesson_id)));
            exit;
        }
    }

}

