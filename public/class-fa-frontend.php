<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FA_Frontend {

    public function __construct() {
        // ===========[ Shortcodes ]===========

        // 1) Homework Form (existing)
        add_shortcode('fa_homework_form', array($this, 'render_homework_form'));

        // 2) Registration & Login (existing from Milestone 1)
        add_shortcode('fa_custom_register', array($this, 'render_registration_form'));
        add_shortcode('fa_custom_login', array($this, 'render_login_form'));

        // 3) STUDENT DASHBOARD (from Milestone 2)
        add_shortcode('fa_student_dashboard', array($this, 'render_student_dashboard'));

        // 4) MILESTONE 3: FRONT-END ADMIN DASHBOARD
        add_shortcode('fa_admin_dashboard', array($this, 'render_admin_dashboard'));

        // ===========[ Form Submissions ]===========

        // Registration & Login
        add_action('init', array($this, 'process_registration_form'));
        add_action('init', array($this, 'process_login_form'));

        // Homework submission
        add_action('init', array($this, 'handle_homework_submission'));

        // Restrict lesson access
        add_action('template_redirect', array($this, 'restrict_lesson_access'));
    }


    /* ------------------------------------------------------------------------ */
    /* (1) REGISTRATION & LOGIN (FROM MILESTONE 1, ALREADY IMPLEMENTED)
    /* ------------------------------------------------------------------------ */

    // Render Registration Form [fa_custom_register]
    public function render_registration_form() {
        if ( is_user_logged_in() ) {
            return '<p>' . __('أنت مسجل دخول بالفعل', 'fashion-academy-lms') . '</p>';
        }

        ob_start();
        ?>
        <form method="post" id="fa-register-form">
            <p>
                <label for="reg_name"><?php _e('اسم المستخدم', 'fashion-academy-lms'); ?></label><br/>
                <input type="text" name="reg_name" id="reg_name" required />
            </p>
            <p>
                <label for="reg_email"><?php _e('البريد الإلكتروني', 'fashion-academy-lms'); ?></label><br/>
                <input type="email" name="reg_email" id="reg_email" required />
            </p>
            <p>
                <label for="reg_password"><?php _e('كلمة المرور', 'fashion-academy-lms'); ?></label><br/>
                <input type="password" name="reg_password" id="reg_password" required />
            </p>

            <input type="hidden" name="fa_registration_action" value="fa_register_user" />
            <?php wp_nonce_field('fa_register_nonce', 'fa_register_nonce_field'); ?>

            <p>
                <input type="submit" value="<?php esc_attr_e('تسجيل حساب', 'fashion-academy-lms'); ?>" />
            </p>
        </form>
        <?php
        return ob_get_clean();
    }

    // Process Registration Form
    public function process_registration_form() {
        if ( isset($_POST['fa_registration_action']) && $_POST['fa_registration_action'] === 'fa_register_user' ) {
            // Check nonce
            if ( ! isset($_POST['fa_register_nonce_field'])
                || ! wp_verify_nonce($_POST['fa_register_nonce_field'], 'fa_register_nonce') ) {
                wp_die(__('فشل التحقق الأمني', 'fashion-academy-lms'));
            }

            // Extract data
            $name     = sanitize_text_field($_POST['reg_name'] ?? '');
            $email    = sanitize_email($_POST['reg_email'] ?? '');
            $password = sanitize_text_field($_POST['reg_password'] ?? '');

            // Validate
            if ( empty($name) || empty($email) || empty($password) ) {
                wp_die(__('يجب تعبئة كافة الحقول المطلوبة', 'fashion-academy-lms'));
            }
            if ( username_exists($name) || email_exists($email) ) {
                wp_die(__('اسم المستخدم أو البريد الإلكتروني مستخدم مسبقًا', 'fashion-academy-lms'));
            }

            // Create user
            $user_id = wp_create_user($name, $password, $email);
            if ( is_wp_error($user_id) ) {
                wp_die($user_id->get_error_message());
            }

            // Assign 'student' role
            $user = new WP_User($user_id);
            $user->set_role('student');

            // Auto Login
            $this->auto_login_user($name, $password);

            // Redirect
            wp_redirect(site_url('/student-dashboard'));
            exit;
        }
    }

    private function auto_login_user($username, $password) {
        $creds = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => true,
        );
        $user = wp_signon($creds, false);
        if ( is_wp_error($user) ) {
            wp_die($user->get_error_message());
        }
    }

    // Render Login Form [fa_custom_login]
    public function render_login_form() {
        if ( is_user_logged_in() ) {
            return '<p>' . __('أنت مسجل دخول بالفعل', 'fashion-academy-lms') . '</p>';
        }

        ob_start(); ?>
        <form method="post" id="fa-login-form">
            <p>
                <label for="fa_user_login"><?php _e('اسم المستخدم أو البريد الإلكتروني', 'fashion-academy-lms'); ?></label><br/>
                <input type="text" name="fa_user_login" id="fa_user_login" required />
            </p>
            <p>
                <label for="fa_user_pass"><?php _e('كلمة المرور', 'fashion-academy-lms'); ?></label><br/>
                <input type="password" name="fa_user_pass" id="fa_user_pass" required />
            </p>

            <input type="hidden" name="fa_login_action" value="fa_do_login" />
            <?php wp_nonce_field('fa_login_nonce', 'fa_login_nonce_field'); ?>

            <p>
                <input type="submit" value="<?php esc_attr_e('دخول', 'fashion-academy-lms'); ?>" />
            </p>
        </form>
        <?php
        return ob_get_clean();
    }

    // Process Login Form
    public function process_login_form() {
        if ( isset($_POST['fa_login_action']) && $_POST['fa_login_action'] === 'fa_do_login' ) {
            if ( ! isset($_POST['fa_login_nonce_field'])
                || ! wp_verify_nonce($_POST['fa_login_nonce_field'], 'fa_login_nonce') ) {
                wp_die(__('فشل التحقق الأمني', 'fashion-academy-lms'));
            }

            $user_login = sanitize_text_field($_POST['fa_user_login'] ?? '');
            $user_pass  = sanitize_text_field($_POST['fa_user_pass'] ?? '');

            $creds = [
                'user_login'    => $user_login,
                'user_password' => $user_pass,
                'remember'      => true
            ];
            $user = wp_signon($creds, false);

            if ( is_wp_error($user) ) {
                wp_die($user->get_error_message());
            }

            // If admin -> admin dashboard, else -> student dashboard
            if ( user_can($user, 'manage_options') ) {
                wp_redirect(site_url('/admin-dashboard'));
            } else {
                wp_redirect(site_url('/student-dashboard'));
            }
            exit;
        }
    }

    /* ------------------------------------------------------------------------ */
    /* (2) STUDENT DASHBOARD (NEW FOR MILESTONE 2)
    /* ------------------------------------------------------------------------ */

    /**
     * Shortcode: [fa_student_dashboard]
     * Renders the student’s main front-end dashboard with a lessons sidebar
     * and main lesson details (video + homework form).
     */
    public function render_student_dashboard() {
        // 1) Check if logged in as student (or admin can also see it)
        if ( ! is_user_logged_in() ) {
            return '<p>' . __('الرجاء تسجيل الدخول', 'fashion-academy-lms') . '</p>';
        }
        // If you want to strictly block non-students:
        // if ( ! current_user_can('student') && ! current_user_can('manage_options') ) {
        //     return '<p>' . __('لا تملك صلاحية الوصول', 'fashion-academy-lms') . '</p>';
        // }

        // 2) Fetch all lessons to display in a sidebar
        $args = array(
            'post_type'      => 'lesson',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value_num',
            'meta_key'       => 'lesson_order',
            'order'          => 'ASC'
        );
        $lessons = get_posts($args);

        ob_start(); ?>
        <div class="fa-student-dashboard-container">
            <h2><?php _e('لوحة تحكم الطالب', 'fashion-academy-lms'); ?></h2>
            <div class="fa-student-dashboard-layout">
                <!-- Sidebar / Lessons List -->
                <div class="fa-lessons-sidebar">
                    <h3><?php _e('الدروس المتاحة', 'fashion-academy-lms'); ?></h3>
                    <ul>
                        <?php
                        foreach ($lessons as $lesson) {
                            $lesson_order = get_post_meta($lesson->ID, 'lesson_order', true);
                            $locked = $this->is_lesson_locked_for_current_user($lesson->ID);

                            echo '<li>';
                            if ( ! $locked ) {
                                echo '<a href="?lesson_id=' . esc_attr($lesson->ID) . '">';
                                echo esc_html($lesson->post_title . ' (درس #' . $lesson_order . ')');
                                echo '</a>';
                            } else {
                                echo esc_html($lesson->post_title . ' (مغلق)');
                            }
                            echo '</li>';
                        }
                        ?>
                    </ul>
                </div>

                <!-- Main Content / Lesson Details -->
                <div class="fa-lesson-content">
                    <?php
                    // If user clicked a lesson in the query string
                    $current_lesson_id = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
                    if ($current_lesson_id) {
                        // Check locked
                        if ($this->is_lesson_locked_for_current_user($current_lesson_id)) {
                            echo '<p>' . __('هذا الدرس مغلق حالياً. الرجاء إكمال الدروس السابقة أو الدفع.', 'fashion-academy-lms') . '</p>';
                        } else {
                            $this->render_lesson_details($current_lesson_id);
                        }
                    } else {
                        echo '<p>' . __('مرحباً! اختر أحد الدروس من القائمة على اليسار.', 'fashion-academy-lms') . '</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
        <style>
            .fa-student-dashboard-layout {
                display: flex;
            }
            .fa-lessons-sidebar {
                width: 25%;
                margin-right: 20px;
                background: #f9f9f9;
                padding: 10px;
                border-radius: 4px;
            }
            .fa-lessons-sidebar ul {
                list-style: none;
                padding-left: 0;
            }
            .fa-lessons-sidebar li {
                margin-bottom: 8px;
            }
            .fa-lesson-content {
                flex: 1;
                border: 1px solid #eee;
                padding: 10px;
                border-radius: 4px;
                background: #fff;
            }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Checks if a given lesson is locked for the current user.
     * You can integrate your existing "sequential unlock" or "paid" logic here.
     */
    private function is_lesson_locked_for_current_user($lesson_id) {
        // Example: If lesson_order > 3 and user not "paid", lock
        // Or if user hasn't passed the previous lesson, lock
        // For now, let's do a simple placeholder returning false => all unlocked.
        // Adjust to reflect your plugin's sequential/payment logic.

        return false;
    }

    /**
     * Renders the detail of a specific lesson: video + homework form.
     */
    private function render_lesson_details($lesson_id) {
        $lesson = get_post($lesson_id);
        if ( ! $lesson ) {
            echo '<p>' . __('الدرس غير موجود', 'fashion-academy-lms') . '</p>';
            return;
        }

        echo '<h2>' . esc_html($lesson->post_title) . '</h2>';

        // Suppose you store the video URL in meta "lesson_video_url"
        $video_url = get_post_meta($lesson_id, 'lesson_video_url', true);
        fa_plugin_log($video_url);
        if ($video_url) {
            ?>
            <div class="fa-lesson-video" style="margin-bottom:20px;">
                <video width="600" controls>
                    <source src="<?php echo esc_url($video_url); ?>" type="video/mp4">
                    <?php _e('متصفحك لا يدعم فيديو.', 'fashion-academy-lms'); ?>
                </video>
            </div>
            <?php
        }

        // Insert the existing homework form
        // We can do do_shortcode('[fa_homework_form]') if your form uses get_the_ID()
        // But because we are not on an actual "lesson" post page, let's do a trick:
        global $post;
        $original_post = $post;
        $post = $lesson; // Temporarily set global $post to the lesson
        setup_postdata($post);

        echo do_shortcode('[fa_homework_form]');

        wp_reset_postdata();
        $post = $original_post; // revert
    }

    /* ------------------------------------------------------------------------ */
    /* (3) MILESTONE 3: FRONT-END ADMIN DASHBOARD
       A new shortcode [fa_admin_dashboard] that:
        - Checks if user is admin
        - Lists students or homework
        - Lets admin grade, add lessons, etc.
    /* ------------------------------------------------------------------------ */

    /**
     * Renders the Admin Dashboard [fa_admin_dashboard].
     */
    public function render_admin_dashboard() {
        // 1) Check if current user is admin
        if ( ! is_user_logged_in() || ! current_user_can('manage_options') ) {
            return '<p>' . __('لا تملك صلاحية الوصول هنا', 'fashion-academy-lms') . '</p>';
        }

        // We'll create a simple "tabs" or nav approach:
        ob_start();
        ?>
        <div class="fa-admin-dashboard-wrapper">
            <h2><?php _e('لوحة تحكم المشرف (Admin Dashboard)', 'fashion-academy-lms'); ?></h2>

            <!-- Nav links for different tasks: "Homeworks", "Add Lessons", "Search Students" etc. -->
            <ul class="fa-admin-nav">
                <li><a href="?admin_page=homeworks"><?php _e('إدارة الواجبات', 'fashion-academy-lms'); ?></a></li>
                <li><a href="?admin_page=lessons"><?php _e('إدارة الدروس', 'fashion-academy-lms'); ?></a></li>
                <li><a href="?admin_page=students"><?php _e('البحث عن الطلاب', 'fashion-academy-lms'); ?></a></li>
            </ul>

            <div class="fa-admin-content">
                <?php
                // Which admin_page is active?
                $admin_page = isset($_GET['admin_page']) ? sanitize_text_field($_GET['admin_page']) : 'homeworks';

                switch ($admin_page) {
                    case 'homeworks':
                        $this->render_admin_homeworks_page();
                        break;
                    case 'lessons':
                        $this->render_admin_lessons_page();
                        break;
                    case 'students':
                        $this->render_admin_students_page();
                        break;
                    default:
                        $this->render_admin_homeworks_page(); // default tab
                }
                ?>
            </div>
        </div>

        <style>
            .fa-admin-dashboard-wrapper {
                margin: 20px;
            }
            .fa-admin-nav {
                list-style: none;
                padding: 0;
                margin-bottom: 15px;
                display: flex;
                gap: 15px;
            }
            .fa-admin-nav li a {
                background: #0073aa;
                color: #fff;
                padding: 6px 12px;
                border-radius: 3px;
                text-decoration: none;
            }
            .fa-admin-nav li a:hover {
                background: #005177;
            }
            .fa-admin-content {
                border: 1px solid #ccc;
                padding: 15px;
                background: #fff;
                border-radius: 4px;
            }
        </style>
        <?php
        return ob_get_clean();
    }

    /* ------------------- (A) HOMEWORKS PAGE ------------------- */

    private function render_admin_homeworks_page() {
        // Lists all homeworks from the custom DB table "homework_submissions"
        global $wpdb;
        $submission_table = $wpdb->prefix . 'homework_submissions';

        // Optional filter by status
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $query = "SELECT * FROM $submission_table";
        if ( ! empty($status_filter) ) {
            $query .= $wpdb->prepare(" WHERE status = %s", $status_filter);
        }
        $query .= " ORDER BY submission_date DESC";

        $submissions = $wpdb->get_results($query);

        // Display filter form
        ?>
        <h3><?php _e('إدارة الواجبات', 'fashion-academy-lms'); ?></h3>
        <form method="get" style="margin-bottom: 20px;">
            <input type="hidden" name="admin_page" value="homeworks" />
            <label for="status_filter"><?php _e('Filter by Status:', 'fashion-academy-lms'); ?></label>
            <select name="status" id="status_filter" style="margin-right: 10px;">
                <option value=""><?php _e('-- All --', 'fashion-academy-lms'); ?></option>
                <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php _e('Pending', 'fashion-academy-lms'); ?></option>
                <option value="graded" <?php selected($status_filter, 'graded'); ?>><?php _e('Graded', 'fashion-academy-lms'); ?></option>
                <option value="passed" <?php selected($status_filter, 'passed'); ?>><?php _e('Passed', 'fashion-academy-lms'); ?></option>
            </select>
            <button type="submit"><?php _e('تصفية', 'fashion-academy-lms'); ?></button>
        </form>
        <?php

        if (!$submissions) {
            echo '<p>' . __('لا يوجد واجبات لعرضها', 'fashion-academy-lms') . '</p>';
            return;
        }

        // Render table
        ?>
        <table class="widefat" style="width:100%;">
            <thead>
            <tr>
                <th><?php _e('ID', 'fashion-academy-lms'); ?></th>
                <th><?php _e('User', 'fashion-academy-lms'); ?></th>
                <th><?php _e('Lesson', 'fashion-academy-lms'); ?></th>
                <th><?php _e('Status', 'fashion-academy-lms'); ?></th>
                <th><?php _e('Grade', 'fashion-academy-lms'); ?></th>
                <th><?php _e('Submitted On', 'fashion-academy-lms'); ?></th>
                <th><?php _e('Action', 'fashion-academy-lms'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($submissions as $submission) {
                $user_info  = get_userdata($submission->user_id);
                $user_name  = $user_info ? $user_info->display_name : __('Unknown', 'fashion-academy-lms');
                $lesson     = get_post($submission->lesson_id);
                $lessonName = $lesson ? $lesson->post_title : __('Unknown Lesson', 'fashion-academy-lms');
                ?>
                <tr>
                    <td><?php echo esc_html($submission->id); ?></td>
                    <td><?php echo esc_html($user_name); ?></td>
                    <td><?php echo esc_html($lessonName); ?></td>
                    <td><?php echo esc_html($submission->status); ?></td>
                    <td><?php echo esc_html($submission->grade); ?></td>
                    <td><?php echo esc_html($submission->submission_date); ?></td>
                    <td>
                        <a href="?admin_page=homeworks&view_submission=<?php echo esc_attr($submission->id); ?>" class="button">
                            <?php _e('عرض / تصحيح', 'fashion-academy-lms'); ?>
                        </a>
                    </td>
                </tr>
                <?php
            }
            ?>
            </tbody>
        </table>
        <?php

        // Check if user clicked "view_submission"
        if (isset($_GET['view_submission'])) {
            $this->render_admin_homework_detail( intval($_GET['view_submission']) );
        }
    }

    /**
     * Renders detail for a single homework submission, with a grading form.
     */
    private function render_admin_homework_detail($submission_id) {
        global $wpdb;
        $submission_table = $wpdb->prefix . 'homework_submissions';

        $submission = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $submission_table WHERE id=%d", $submission_id)
        );
        if ( ! $submission ) {
            echo '<p>' . __('Submission غير موجود', 'fashion-academy-lms') . '</p>';
            return;
        }

        // If form submitted to grade
        if ( isset($_POST['fa_grade_submission']) ) {
            $new_grade  = floatval($_POST['grade']);
            $new_status = 'graded';

            // Passing threshold
            $passing_grade = 75;
            if ($new_grade >= $passing_grade) {
                $new_status = 'passed';
            }

            // Update in DB
            $res = $wpdb->update(
                $submission_table,
                array('grade' => $new_grade, 'status' => $new_status),
                array('id' => $submission_id),
                array('%f','%s'),
                array('%d')
            );
            if (false === $res) {
                fa_plugin_log("Failed to update submission ID {$submission_id}");
                echo '<p>' . __('فشل تحديث التقييم', 'fashion-academy-lms') . '</p>';
                return;
            }

            // If passed => mark current lesson as passed + unlock next
            if ($new_status === 'passed') {
                $this->mark_lesson_as_passed($submission->user_id, $submission->lesson_id);
                $this->unlock_next_lesson($submission->user_id, $submission->lesson_id);
            }

            echo '<div class="notice notice-success"><p>' . __('تم تحديث التقييم!', 'fashion-academy-lms') . '</p></div>';
            // Re-fetch
            $submission = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $submission_table WHERE id=%d", $submission_id) );
        }

        // Show details
        $uploaded_files = json_decode($submission->uploaded_files, true);
        $notes = $submission->notes;

        // Show a form to grade
        ?>
        <hr>
        <h4><?php _e('تفاصيل الواجب:', 'fashion-academy-lms'); ?> #<?php echo esc_html($submission_id); ?></h4>
        <p><strong><?php _e('الحالة', 'fashion-academy-lms'); ?>:</strong> <?php echo esc_html($submission->status); ?></p>
        <p><strong><?php _e('الدرجة', 'fashion-academy-lms'); ?>:</strong> <?php echo esc_html($submission->grade); ?></p>
        <p><strong><?php _e('ملاحظات الطالب', 'fashion-academy-lms'); ?>:</strong> <?php echo esc_html($notes); ?></p>

        <?php if (!empty($uploaded_files)) : ?>
            <h5><?php _e('الملفات المرفقة:', 'fashion-academy-lms'); ?></h5>
            <ul>
                <?php foreach ($uploaded_files as $file_url) : ?>
                    <li><a href="<?php echo esc_url($file_url); ?>" target="_blank"><?php echo esc_html(basename($file_url)); ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" style="margin-top:15px;">
            <p>
                <label for="grade"><?php _e('التقييم (%):', 'fashion-academy-lms'); ?></label>
                <input type="number" name="grade" id="grade" step="1" min="0" max="100"
                       value="<?php echo esc_attr($submission->grade); ?>" required>
            </p>
            <input type="hidden" name="fa_grade_submission" value="true" />
            <button type="submit" class="button button-primary"><?php _e('حفظ التقييم', 'fashion-academy-lms'); ?></button>
        </form>
        <?php
    }

    /**
     * Marks the current lesson as 'passed' in course_progress for that user.
     */
    private function mark_lesson_as_passed($user_id, $lesson_id) {
        global $wpdb;
        $progress_table = $wpdb->prefix . 'course_progress';

        // Check existing
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $progress_table WHERE user_id=%d AND lesson_id=%d",
            $user_id,
            $lesson_id
        ));

        if ($existing) {
            $wpdb->update(
                $progress_table,
                array('progress_status' => 'passed'),
                array('id' => $existing->id),
                array('%s'),
                array('%d')
            );
            fa_plugin_log("Set lesson #$lesson_id to 'passed' for user #$user_id (existing progress).");
        } else {
            // Insert
            // Need course_id from meta if needed
            $course_id = get_post_meta($lesson_id, 'lesson_course_id', true);
            $wpdb->insert(
                $progress_table,
                array(
                    'user_id' => $user_id,
                    'course_id' => $course_id ?: 0,
                    'lesson_id' => $lesson_id,
                    'progress_status' => 'passed'
                ),
                array('%d','%d','%d','%s')
            );
            fa_plugin_log("Set lesson #$lesson_id to 'passed' for user #$user_id (new progress).");
        }
    }

    /**
     * Unlock the next lesson for the user (already implemented in Milestone 2 admin code).
     */
    private function unlock_next_lesson($user_id, $current_lesson_id) {
        // Reuse the same logic from class-fa-admin or milest. 2
        global $wpdb;
        $progress_table = $wpdb->prefix . 'course_progress';

        $current_order = get_post_meta($current_lesson_id, 'lesson_order', true);
        $course_id     = get_post_meta($current_lesson_id, 'lesson_course_id', true);
        if (!$course_id || !$current_order) {
            return;
        }

        $next_lesson = get_posts(array(
            'post_type' => 'lesson',
            'posts_per_page' => 1,
            'meta_key' => 'lesson_order',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'meta_query' => array(
                array('key' => 'lesson_course_id', 'value' => $course_id),
                array('key' => 'lesson_order', 'value' => intval($current_order)+1, 'compare' => '=', 'type' => 'NUMERIC')
            )
        ));
        if (empty($next_lesson)) {
            fa_plugin_log("No next lesson after #$current_lesson_id for user #$user_id");
            return;
        }

        $next_lesson_id = $next_lesson[0]->ID;
        // Check existing
        $existing_progress = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $progress_table WHERE user_id=%d AND lesson_id=%d",
            $user_id,
            $next_lesson_id
        ));
        if ($existing_progress) {
            $wpdb->update(
                $progress_table,
                array('progress_status' => 'incomplete'),
                array('id'=>$existing_progress->id),
                array('%s'),
                array('%d')
            );
            fa_plugin_log("Unlocked next lesson #$next_lesson_id for user #$user_id (existing progress).");
        } else {
            $wpdb->insert(
                $progress_table,
                array(
                    'user_id' => $user_id,
                    'course_id' => $course_id,
                    'lesson_id' => $next_lesson_id,
                    'progress_status' => 'incomplete'
                ),
                array('%d','%d','%d','%s')
            );
            fa_plugin_log("Unlocked next lesson #$next_lesson_id for user #$user_id (new progress).");
        }
    }

    /* ------------------- (B) LESSONS PAGE ------------------- */

    private function render_admin_lessons_page() {
        // 1) If "Add Lesson" form is submitted, handle it
        if ( isset($_POST['fa_create_lesson_action']) && $_POST['fa_create_lesson_action'] === 'create_lesson' ) {
            check_admin_referer('fa_create_lesson_nonce', 'fa_create_lesson_nonce_field');

            $lesson_title = sanitize_text_field($_POST['lesson_title'] ?? '');
            $course_id    = intval($_POST['course_id'] ?? 0);
            $video_url    = ''; // We’ll fill this if we upload a file
            $lesson_order = intval($_POST['lesson_order'] ?? 0);

            if (empty($lesson_title)) {
                echo '<p style="color:red;">' . __('يجب إدخال عنوان الدرس', 'fashion-academy-lms') . '</p>';
            } else {
                // Upload the video file if provided
                if ( isset($_FILES['video_file']) && !empty($_FILES['video_file']['name']) ) {
                    $upload = $this->fa_admin_upload_video_file($_FILES['video_file']);
                    if ( is_wp_error($upload) ) {
                        echo '<p style="color:red;">' . $upload->get_error_message() . '</p>';
                    } else {
                        // $upload is an attachment ID or URL depending on implementation
                        // Let’s assume we store the attachment ID in $video_url
                        $video_url = $upload;
                    }
                }

                // Insert the lesson post
                $lesson_id = wp_insert_post(array(
                    'post_title'   => $lesson_title,
                    'post_type'    => 'lesson',
                    'post_status'  => 'publish',
                    'post_content' => '' // or something if you want content
                ), true);

                if (! is_wp_error($lesson_id)) {
                    // Update meta
                    if ($course_id) {
                        update_post_meta($lesson_id, 'lesson_course_id', $course_id);
                    }
                    if ($lesson_order > 0) {
                        update_post_meta($lesson_id, 'lesson_order', $lesson_order);
                    }
                    if ($video_url) {
                        update_post_meta($lesson_id, 'lesson_video_url', $video_url);
                    }

                    echo '<div class="notice notice-success"><p>'
                        . __('تم إنشاء الدرس بنجاح!', 'fashion-academy-lms')
                        . ' (ID=' . $lesson_id . ')</p></div>';
                } else {
                    echo '<p style="color:red;">' . $lesson_id->get_error_message() . '</p>';
                }
            }
        }

        // 2) Render the "Add Lesson" form
        $courses = get_posts(array(
            'post_type'   => 'course',
            'numberposts' => -1,
            'post_status' => 'publish'
        ));
        ?>
        <h3><?php _e('إضافة درس جديد', 'fashion-academy-lms'); ?></h3>
        <form method="post" enctype="multipart/form-data" style="margin-bottom:20px;">
            <?php wp_nonce_field('fa_create_lesson_nonce', 'fa_create_lesson_nonce_field'); ?>
            <input type="hidden" name="fa_create_lesson_action" value="create_lesson" />

            <p>
                <label for="lesson_title"><?php _e('عنوان الدرس:', 'fashion-academy-lms'); ?></label><br>
                <input type="text" name="lesson_title" id="lesson_title" style="width:300px;">
            </p>
            <p>
                <label for="course_id"><?php _e('اختر الكورس:', 'fashion-academy-lms'); ?></label><br>
                <select name="course_id" id="course_id">
                    <option value="0"><?php _e('-- لا يوجد --', 'fashion-academy-lms'); ?></option>
                    <?php foreach($courses as $course) {
                        echo '<option value="' . $course->ID . '">' . esc_html($course->post_title) . '</option>';
                    } ?>
                </select>
            </p>
            <p>
                <label for="lesson_order"><?php _e('ترتيب الدرس (lesson_order):', 'fashion-academy-lms'); ?></label><br>
                <input type="number" name="lesson_order" id="lesson_order" style="width:100px;" value="1" min="1">
            </p>
            <p>
                <label for="video_file"><?php _e('رفع ملف الفيديو:', 'fashion-academy-lms'); ?></label><br>
                <input type="file" name="video_file" id="video_file" accept="video/*">
                <small style="color:#666;">
                    <?php _e('يمكنك رفع ملف فيديو من جهازك', 'fashion-academy-lms'); ?>
                </small>
            </p>
            <button type="submit" class="button button-primary"><?php _e('إضافة الدرس', 'fashion-academy-lms'); ?></button>
        </form>
        <?php

        // 3) List existing lessons
        $lessons = get_posts(array(
            'post_type'=>'lesson',
            'numberposts'=>-1,
            'orderby'=>'meta_value_num',
            'meta_key'=>'lesson_order',
            'order'=>'ASC'
        ));
        if (!$lessons) {
            echo '<p>' . __('لا يوجد دروس.', 'fashion-academy-lms') . '</p>';
            return;
        }
        ?>
        <h3><?php _e('كل الدروس', 'fashion-academy-lms'); ?></h3>
        <table class="widefat">
            <thead>
            <tr>
                <th><?php _e('ID', 'fashion-academy-lms'); ?></th>
                <th><?php _e('عنوان الدرس', 'fashion-academy-lms'); ?></th>
                <th><?php _e('الكورس', 'fashion-academy-lms'); ?></th>
                <th><?php _e('الترتيب', 'fashion-academy-lms'); ?></th>
                <th><?php _e('الفيديو', 'fashion-academy-lms'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($lessons as $lesson) {
                $course_id  = get_post_meta($lesson->ID, 'lesson_course_id', true);
                $course     = get_post($course_id);
                $courseName = $course ? $course->post_title : __('--', 'fashion-academy-lms');
                $order      = get_post_meta($lesson->ID, 'lesson_order', true);
                $video      = get_post_meta($lesson->ID, 'lesson_video_url', true);

                echo '<tr>';
                echo '<td>' . esc_html($lesson->ID) . '</td>';
                echo '<td>' . esc_html($lesson->post_title) . '</td>';
                echo '<td>' . esc_html($courseName) . '</td>';
                echo '<td>' . esc_html($order) . '</td>';
                echo '<td>' . esc_html($video ? basename($video) : '--') . '</td>';
                echo '</tr>';
            }
            ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Helper to handle uploading a video file from the admin's "Add Lesson" form.
     * Returns either WP_Error or a string with the attachment URL (or ID).
     */
    private function fa_admin_upload_video_file($file_array) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        if ($file_array['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', __('حدث خطأ عند رفع الملف', 'fashion-academy-lms'));
        }

        // Optional: restrict to certain mime types if you want only mp4, avi, etc.
        $allowed_mimes = array('video/mp4'=>'video/mp4','video/quicktime'=>'video/quicktime');
        $check_filetype = wp_check_filetype_and_ext($file_array['tmp_name'], $file_array['name'], false);
        if (! in_array($check_filetype['type'], $allowed_mimes)) {
            return new WP_Error('upload_error', __('هذا النوع من الفيديو غير مسموح به', 'fashion-academy-lms'));
        }

        // Use WordPress API to handle the upload, store in Media Library
        $attach_id = media_handle_upload('video_file', 0);
        if (is_wp_error($attach_id)) {
            return $attach_id; // some error
        }

        // Return the URL or the attachment ID
        $video_url = wp_get_attachment_url($attach_id);
        return $video_url;
    }


    /* ------------------- (C) STUDENTS PAGE ------------------- */

    private function render_admin_students_page() {
        // Example: let admin search by username or email
        ?>
        <h3><?php _e('البحث عن الطلاب', 'fashion-academy-lms'); ?></h3>
        <form method="get">
            <input type="hidden" name="admin_page" value="students" />
            <label for="student_search"><?php _e('البحث:', 'fashion-academy-lms'); ?></label>
            <input type="text" name="student_search" id="student_search" value="<?php echo esc_attr($_GET['student_search']??''); ?>" />
            <button type="submit" class="button"><?php _e('بحث', 'fashion-academy-lms'); ?></button>
        </form>
        <?php

        if (isset($_GET['student_search']) && $_GET['student_search'] !== '') {
            $search = sanitize_text_field($_GET['student_search']);

            // Query users with 'student' role matching that string
            $args = array(
                'role'    => 'student',
                'search'  => "*{$search}*",
                'search_columns' => array('user_login','user_email','display_name')
            );
            $users = get_users($args);

            if (!$users) {
                echo '<p>' . __('لا يوجد طلاب مطابقين.', 'fashion-academy-lms') . '</p>';
            } else {
                ?>
                <table class="widefat" style="margin-top:15px;">
                    <thead>
                    <tr>
                        <th><?php _e('ID', 'fashion-academy-lms'); ?></th>
                        <th><?php _e('اسم المستخدم', 'fashion-academy-lms'); ?></th>
                        <th><?php _e('البريد الإلكتروني', 'fashion-academy-lms'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    foreach($users as $u) {
                        echo '<tr>';
                        echo '<td>' . esc_html($u->ID) . '</td>';
                        echo '<td>' . esc_html($u->display_name) . '</td>';
                        echo '<td>' . esc_html($u->user_email) . '</td>';
                        echo '</tr>';
                    }
                    ?>
                    </tbody>
                </table>
                <?php
            }
        }
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
