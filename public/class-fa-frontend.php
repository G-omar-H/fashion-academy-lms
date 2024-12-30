<?php
if (!defined('ABSPATH')) exit;

class FA_Frontend
{
    public function __construct()
    {
        // ===========[ Shortcodes ]===========

        // 1) Homework Form (existing)
        add_shortcode('fa_homework_form', array($this, 'render_homework_form'));

        // 2) Registration & Login (Milestone 1)
        add_shortcode('fa_custom_register', array($this, 'render_registration_form'));
        add_shortcode('fa_custom_login', array($this, 'render_login_form'));

        // 3) STUDENT DASHBOARD (Milestone 2)
        add_shortcode('fa_student_dashboard', array($this, 'render_student_dashboard'));

        // 4) ADMIN DASHBOARD (Milestone 3)
        add_shortcode('fa_admin_dashboard', array($this, 'render_admin_dashboard'));

        // ===========[ Form Submissions ]===========

        // Registration & Login
        add_action('init', array($this, 'process_registration_form'));
        add_action('init', array($this, 'process_login_form'));

        // Homework submission
        add_action('init', array($this, 'handle_homework_submission'));

        // Restrict lesson access
        add_action('template_redirect', array($this, 'restrict_lesson_access'));

        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));

    }

    public function enqueue_styles()
    {
        // Enqueue the main frontend stylesheet
        wp_enqueue_style(
            'fa-frontend-style',
            plugin_dir_url(__FILE__) . '../assets/css/frontend.css', // Ensure the path matches
            array(),
            '1.2.0',
            'all'
        );

        // Enqueue Google Fonts and Font Awesome
        wp_enqueue_style(
            'fa-google-fonts',
            'https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Tajawal:wght@300;400;500;700&display=swap',
            array(),
            null
        );

        wp_enqueue_style(
            'fa-font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
            array(),
            '6.4.0',
            'all'
        );
    }



    /* ------------------------------------------------------------------------ */
    /* (1) REGISTRATION & LOGIN (MILESTONE 1)
    /* ------------------------------------------------------------------------ */

    // Render Registration Form [fa_custom_register]
    public function render_registration_form()
    {
        if (is_user_logged_in()) {
            return '<p>' . __('أنت مسجل دخول بالفعل', 'fashion-academy-lms') . '</p>';
        }

        ob_start(); ?>
        <form method="post" id="fa-register-form">
            <p>
                <label for="reg_name"><?php _e('اسم المستخدم', 'fashion-academy-lms'); ?></label><br/>
                <input type="text" name="reg_name" id="reg_name" required/>
            </p>
            <p>
                <label for="reg_email"><?php _e('البريد الإلكتروني', 'fashion-academy-lms'); ?></label><br/>
                <input type="email" name="reg_email" id="reg_email" required/>
            </p>
            <p>
                <label for="reg_password"><?php _e('كلمة المرور', 'fashion-academy-lms'); ?></label><br/>
                <input type="password" name="reg_password" id="reg_password" required/>
            </p>

            <input type="hidden" name="fa_registration_action" value="fa_register_user"/>
            <?php wp_nonce_field('fa_register_nonce', 'fa_register_nonce_field'); ?>

            <p>
                <input type="submit" value="<?php esc_attr_e('تسجيل حساب', 'fashion-academy-lms'); ?>"/>
            </p>
        </form>
        <?php
        return ob_get_clean();
    }

    // Process Registration Form
    public function process_registration_form()
    {
        if (isset($_POST['fa_registration_action']) && $_POST['fa_registration_action'] === 'fa_register_user') {
            if (!isset($_POST['fa_register_nonce_field']) ||
                !wp_verify_nonce($_POST['fa_register_nonce_field'], 'fa_register_nonce')) {
                wp_die(__('فشل التحقق الأمني', 'fashion-academy-lms'));
            }

            $name     = sanitize_text_field($_POST['reg_name'] ?? '');
            $email    = sanitize_email($_POST['reg_email'] ?? '');
            $password = sanitize_text_field($_POST['reg_password'] ?? '');

            if (empty($name) || empty($email) || empty($password)) {
                wp_die(__('يجب تعبئة كافة الحقول المطلوبة', 'fashion-academy-lms'));
            }
            if (username_exists($name) || email_exists($email)) {
                wp_die(__('اسم المستخدم أو البريد الإلكتروني مستخدم مسبقًا', 'fashion-academy-lms'));
            }

            // Create user
            $user_id = wp_create_user($name, $password, $email);
            if (is_wp_error($user_id)) {
                wp_die($user_id->get_error_message());
            }

            // Assign 'student' role
            $user = new WP_User($user_id);
            $user->set_role('student');

            // Auto Login
            $this->auto_login_user($name, $password);

            // Redirect to student dashboard
            wp_redirect(site_url('/student-dashboard'));
            exit;
        }
    }

    private function auto_login_user($username, $password)
    {
        $creds = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => true,
        );
        $user = wp_signon($creds, false);
        if (is_wp_error($user)) {
            wp_die($user->get_error_message());
        }
    }

    // Render Login Form [fa_custom_login]
    public function render_login_form()
    {
        if (is_user_logged_in()) {
            return '<p>' . __('أنت مسجل دخول بالفعل', 'fashion-academy-lms') . '</p>';
        }

        ob_start(); ?>
        <form method="post" id="fa-login-form">
            <p>
                <label for="fa_user_login"><?php _e('اسم المستخدم أو البريد الإلكتروني', 'fashion-academy-lms'); ?></label><br/>
                <input type="text" name="fa_user_login" id="fa_user_login" required/>
            </p>
            <p>
                <label for="fa_user_pass"><?php _e('كلمة المرور', 'fashion-academy-lms'); ?></label><br/>
                <input type="password" name="fa_user_pass" id="fa_user_pass" required/>
            </p>

            <input type="hidden" name="fa_login_action" value="fa_do_login"/>
            <?php wp_nonce_field('fa_login_nonce', 'fa_login_nonce_field'); ?>

            <p>
                <input type="submit" value="<?php esc_attr_e('دخول', 'fashion-academy-lms'); ?>"/>
            </p>
        </form>
        <?php
        return ob_get_clean();
    }

    // Process Login Form
    public function process_login_form()
    {
        if (isset($_POST['fa_login_action']) && $_POST['fa_login_action'] === 'fa_do_login') {
            if (!isset($_POST['fa_login_nonce_field']) ||
                !wp_verify_nonce($_POST['fa_login_nonce_field'], 'fa_login_nonce')) {
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

            if (is_wp_error($user)) {
                wp_die($user->get_error_message());
            }

            // If admin => admin dashboard, else => student dashboard
            if (user_can($user, 'manage_options')) {
                wp_redirect(site_url('/admin-dashboard'));
            } else {
                wp_redirect(site_url('/student-dashboard'));
            }
            exit;
        }
    }

    /* ------------------------------------------------------------------------ */
    /* (2) STUDENT DASHBOARD (MILESTONE 2)
    /* ------------------------------------------------------------------------ */

    // Shortcode: [fa_student_dashboard]
    /* ------------------------------------------------------------------------ */
    /* (2) STUDENT DASHBOARD (MILESTONE 1)
    /* ------------------------------------------------------------------------ */

// Shortcode: [fa_student_dashboard]
    public function render_student_dashboard()
    {
        if (!is_user_logged_in()) {
            return '<p>' . __('الرجاء تسجيل الدخول', 'fashion-academy-lms') . '</p>';
        }

        // Fetch all modules ordered by 'module_order'
        $modules = get_posts([
            'post_type'      => 'module',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value_num',
            'meta_key'       => 'module_order',
            'order'          => 'ASC'
        ]);

        // Fetch all lessons ordered by 'lesson_order'
        $lessons = get_posts([
            'post_type'      => 'lesson',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value_num',
            'meta_key'       => 'lesson_order',
            'order'          => 'ASC'
        ]);

        // Group lessons by module
        $lessons_by_module = array();
        $unassigned_lessons = array();

        foreach ($lessons as $lesson) {
            $module_id = get_post_meta($lesson->ID, 'lesson_module_id', true);
            if ($module_id) {
                if (!isset($lessons_by_module[$module_id])) {
                    $lessons_by_module[$module_id] = array();
                }
                $lessons_by_module[$module_id][] = $lesson;
            } else {
                $unassigned_lessons[] = $lesson;
            }
        }

        ob_start(); ?>
        <div class="fa-student-dashboard-container">
            <h2><?php _e('لوحة تحكم الطالب', 'fashion-academy-lms'); ?></h2>
            <div class="fa-student-dashboard-layout">
                <div class="fa-lessons-sidebar">
                    <h3><?php _e('المواد والدروس المتاحة', 'fashion-academy-lms'); ?></h3>
                    <ul>
                        <?php
                        $current_lesson_id = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;

                        // Iterate through each module
                        foreach ($modules as $module) {
                            echo '<li><strong>' . esc_html($module->post_title) . '</strong>';
                            echo '<ul>';

                            if (isset($lessons_by_module[$module->ID])) {
                                foreach ($lessons_by_module[$module->ID] as $lesson) {
                                    $lesson_order = get_post_meta($lesson->ID, 'lesson_order', true);
                                    $locked = $this->is_lesson_locked_for_current_user($lesson->ID);
                                    $is_active = ($lesson->ID == $current_lesson_id);

                                    echo '<li>';
                                    if (!$locked) {
                                        $active_class = $is_active ? ' active-lesson' : '';
                                        echo '<a href="?lesson_id=' . esc_attr($lesson->ID) . '" class="' . esc_attr($active_class) . '">';
                                        echo esc_html($lesson_order . '. ' . $lesson->post_title);
                                        echo '</a>';
                                    } else {
                                        echo esc_html($lesson_order . '. ' . $lesson->post_title . ' (مغلق)');
                                    }
                                    echo '</li>';
                                }
                            } else {
                                echo '<li>' . __('لا توجد دروس في هذه المادة.', 'fashion-academy-lms') . '</li>';
                            }

                            echo '</ul></li>';
                        }

                        // List unassigned lessons under a separate heading
                        if (!empty($unassigned_lessons)) {
                            echo '<li><strong>' . __('دروس بدون مادة', 'fashion-academy-lms') . '</strong>';
                            echo '<ul>';
                            foreach ($unassigned_lessons as $lesson) {
                                $lesson_order = get_post_meta($lesson->ID, 'lesson_order', true);
                                $locked = $this->is_lesson_locked_for_current_user($lesson->ID);
                                $is_active = ($lesson->ID == $current_lesson_id);

                                echo '<li>';
                                if (!$locked) {
                                    $active_class = $is_active ? ' active-lesson' : '';
                                    echo '<a href="?lesson_id=' . esc_attr($lesson->ID) . '" class="' . esc_attr($active_class) . '">';
                                    echo esc_html($lesson_order . '. ' . $lesson->post_title);
                                    echo '</a>';
                                } else {
                                    echo esc_html($lesson_order . '. ' . $lesson->post_title . ' (مغلق)');
                                }
                                echo '</li>';
                            }
                            echo '</ul></li>';
                        }
                        ?>
                    </ul>
                </div>

                <div class="fa-lesson-content">
                    <?php
                    $current_lesson_id = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
                    if ($current_lesson_id) {
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
        <?php
        return ob_get_clean();
    }


    // If user hasn't paid or hasn't passed a prior lesson, return true. (Placeholder)
    private function is_lesson_locked_for_current_user($lesson_id)
    {
        return false; // placeholder => everything unlocked
    }

    // Show lesson details (video + homework form)
    private function render_lesson_details($lesson_id)
    {
        $lesson = get_post($lesson_id);

        if (!$lesson) {
            echo '<p>' . __('الدرس غير موجود', 'fashion-academy-lms') . '</p>';
            return;
        }

        echo '<h2>' . esc_html($lesson->post_title) . '</h2>';

        $video_url = get_post_meta($lesson_id, 'lesson_video_url', true);
        if ($video_url) {
            echo '<div class="fa-lesson-video" style="margin-bottom:20px; text-align:center;">';
            echo '<video width="600" controls>';
            echo '<source src="' . esc_url($video_url) . '" type="video/mp4">';
            _e('متصفحك لا يدعم فيديو.', 'fashion-academy-lms');
            echo '</video>';
            echo '</div>';
        }

        // Fetch the submission
        $submission = $this->get_current_submission_for_user(get_current_user_id(), $lesson_id);

        // 1) If no submission or submission is 'retake', show the form
        if (!$submission || $submission->status === 'retake') {
            // Show the form container with an ID for JS
            echo '<div id="fa-homework-container">';
            echo do_shortcode('[fa_homework_form lesson_id="' . $lesson_id . '"]');
            echo '</div>';
            // We'll add a small JS snippet to hide the form and show spinner on submit
        }
        // 2) If submission is "pending," remove the form, show spinner
        elseif ($submission->status === 'pending') {
            echo '<div class="fa-spinner-section">';
            echo '<div class="fa-spinner"></div>'; // Replacing spinner.gif with a CSS spinner
            echo '<p class="fa-waiting-msg">'
                . __('تم إرسال الواجب. بانتظار تصحيح الأستاذ...', 'fashion-academy-lms') . '</p>';
            echo '</div>';
            ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const submissionId  = <?php echo (int) $submission->id; ?>;
                    const currentStatus = "<?php echo esc_js($submission->status); ?>";
                    const pollInterval  = 15000; // 15 seconds

                    // We only poll if the current status is 'pending'
                    if (currentStatus === 'pending') {
                        setInterval(checkSubmissionStatus, pollInterval);
                    }

                    function checkSubmissionStatus() {
                        // Request via admin-ajax
                        const ajaxUrl  = "<?php echo admin_url('admin-ajax.php'); ?>";
                        const endpoint = ajaxUrl + '?action=fa_check_submission&submission_id=' + submissionId;

                        fetch(endpoint, { credentials: 'same-origin' })
                            .then(response => response.json())
                            .then(data => {
                                // Log the response for debugging
                                console.log('AJAX Response:', data);

                                // Check if the request was successful
                                if (!data.success) {
                                    console.warn('AJAX Error:', data.data);
                                    return;
                                }

                                const newStatus = data.data.status;

                                if (newStatus !== currentStatus) {
                                    // If the status changed (pending->graded or passed), reload the page
                                    window.location.reload();
                                }
                            })
                            .catch(err => console.log('Fetch error:', err));
                    }
                });
            </script>
            <?php
        }
        // 3) If submission is "graded" or "passed," show results
        else {
            echo '<div class="fa-homework-results">';

            if ($submission->status === 'graded') {
                echo '<p>' . sprintf(__('تم تصحيح الواجب. درجتك: %s%%', 'fashion-academy-lms'), $submission->grade) . '</p>';
            } elseif ($submission->status === 'passed') {
                echo '<p>' . sprintf(__('أحسنت! لقد تجاوزت هذا الواجب بنجاح. درجتك: %s%%', 'fashion-academy-lms'), $submission->grade) . '</p>';
            }

            // If you want to display the instructor feedback files, do so here:
            $instructor_files = json_decode($submission->instructor_files, true);
            if (!empty($instructor_files)) {
                echo '<h4>' . __('مرفقات الأستاذ / التصحيح:', 'fashion-academy-lms') . '</h4><ul>';
                foreach ($instructor_files as $ifile) {
                    echo '<li><a href="' . esc_url($ifile) . '" target="_blank">'
                        . esc_html(basename($ifile)) . '</a></li>';
                }
                echo '</ul>';
            }

            // Retake button
            echo '<button class="fa-retake-button" onclick="retakeHomework(' . intval($submission->id) . ')">'
                . __('إعادة المحاولة', 'fashion-academy-lms') . '</button>';

            echo '</div>';
        }

        // Add some small JS to handle "Retake" + the spinner on submission
        ?>
        <script>
            function retakeHomework(submissionId) {
                if (!confirm('<?php echo esc_js(__('هل أنت متأكد من إعادة الواجب؟', 'fashion-academy-lms')); ?>')) return;
                window.location.href = '?retake_homework=' + submissionId;
            }

            // On form submit => hide the form, show spinner
            document.addEventListener('DOMContentLoaded', function() {
                var homeworkForm = document.getElementById('fa-homework-form');
                if (homeworkForm) {
                    homeworkForm.addEventListener('submit', function(e) {
                        // Hide the form container
                        document.getElementById('fa-homework-container').style.display='none';
                        // Show a quick inline spinner:
                        var spinnerDiv = document.createElement('div');
                        spinnerDiv.innerHTML = '<img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'assets/img/spinner.gif'); ?>" ' +
                            'class="fa-spinner-img" alt="Spinner">' +
                            '<p class="fa-waiting-msg"><?php echo esc_js(__('جاري إرسال الواجب...', 'fashion-academy-lms')); ?></p>';
                        document.getElementById('fa-homework-container').parentNode.appendChild(spinnerDiv);
                    });
                }
            });
        </script>
        <?php
    }


    /**
     * Helper to fetch the current submission for user + lesson.
     */
    private function get_current_submission_for_user($user_id, $lesson_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'homework_submissions';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id=%d AND lesson_id=%d ORDER BY submission_date DESC LIMIT 1",
            $user_id,
            $lesson_id
        ));
    }


    /* ------------------------------------------------------------------------ */
    /* (3) ADMIN DASHBOARD (MILESTONE 3)
    /* ------------------------------------------------------------------------ */

    // Shortcode: [fa_admin_dashboard]
    public function render_admin_dashboard()
    {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return '<p>' . __('لا تملك صلاحية الوصول هنا', 'fashion-academy-lms') . '</p>';
        }

        $admin_page = isset($_GET['admin_page']) ? sanitize_text_field($_GET['admin_page']) : 'homeworks';

        function is_active_tab($tab)
        {
            return (isset($_GET['admin_page']) && $_GET['admin_page'] === $tab);
        }

        ob_start(); ?>
        <div class="fa-admin-dashboard-wrapper">
            <h2><?php _e('لوحة تحكم المشرف (Admin Dashboard)', 'fashion-academy-lms'); ?></h2>

            <ul class="fa-admin-nav">
                <li><a href="?admin_page=homeworks" class="<?php echo is_active_tab('homeworks') ? 'active-tab' : ''; ?>">
                        <?php _e('إدارة الواجبات', 'fashion-academy-lms'); ?></a></li>
                <li><a href="?admin_page=lessons" class="<?php echo is_active_tab('lessons') ? 'active-tab' : ''; ?>">
                        <?php _e('إدارة الدروس', 'fashion-academy-lms'); ?></a></li>
                <li><a href="?admin_page=modules" class="<?php echo is_active_tab('modules') ? 'active-tab' : ''; ?>">
                        <?php _e('إدارة المواد', 'fashion-academy-lms'); ?></a></li>
                <li><a href="?admin_page=students" class="<?php echo is_active_tab('students') ? 'active-tab' : ''; ?>">
                        <?php _e('البحث عن الطلاب', 'fashion-academy-lms'); ?></a></li>
            </ul>

            <div class="fa-admin-content">
                <?php
                switch ($admin_page) {
                    case 'homeworks':
                        $this->render_admin_homeworks_page();
                        break;
                    case 'lessons':
                        $this->render_admin_lessons_page();
                        break;
                    case 'modules':
                        $this->render_admin_modules_page();
                        break;
                    case 'students':
                        $this->render_admin_students_page();
                        break;
                    default:
                        $this->render_admin_homeworks_page();
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }


    // Homeworks
    private function render_admin_homeworks_page()
    {
        global $wpdb;
        $submission_table = $wpdb->prefix . 'homework_submissions';

        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $query = "SELECT * FROM $submission_table";
        if (!empty($status_filter)) {
            $query .= $wpdb->prepare(" WHERE status = %s", $status_filter);
        }
        $query .= " ORDER BY submission_date DESC";

        $submissions = $wpdb->get_results($query);
        ?>
        <h3><?php _e('إدارة الواجبات', 'fashion-academy-lms'); ?></h3>
        <form method="get" style="margin-bottom: 20px;">
            <input type="hidden" name="admin_page" value="homeworks"/>
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
        ?>
        <table class="widefat">
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
                $user_info = get_userdata($submission->user_id);
                $user_name = $user_info ? $user_info->display_name : __('Unknown', 'fashion-academy-lms');
                $lesson = get_post($submission->lesson_id);
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
                        <a href="?admin_page=homeworks&view_submission=<?php echo esc_attr($submission->id); ?>"
                           class="button button-inline">
                            <span class="dashicons dashicons-visibility"></span>
                            <span class="button-text"><?php _e('عرض / تصحيح', 'fashion-academy-lms'); ?></span>
                        </a>
                    </td>
                </tr>
                <?php
            }
            ?>
            </tbody>
        </table>
        <?php

        if (isset($_GET['view_submission'])) {
            $this->render_admin_homework_detail(intval($_GET['view_submission']));
        }
    }

    // Single homework detail + grading
    private function render_admin_homework_detail($submission_id)
    {
        global $wpdb;
        $submission_table = $wpdb->prefix . 'homework_submissions';

        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $submission_table WHERE id=%d", $submission_id
        ));
        if (!$submission) {
            echo '<p>' . __('Submission غير موجود', 'fashion-academy-lms') . '</p>';
            return;
        }

        // If form submitted to grade + attach instructor files
        if (isset($_POST['fa_grade_submission']) && $_POST['fa_grade_submission'] === 'true') {
            $new_grade = floatval($_POST['grade']);
            $passing_grade = 75;
            $new_status = ($new_grade >= $passing_grade) ? 'passed' : 'graded';

            // 1) Handle instructor files
            $instructor_files = array();
            if (!empty($_FILES['instructor_files']['name'][0])) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');

                $file_count = count($_FILES['instructor_files']['name']);
                for ($i=0; $i < $file_count; $i++) {
                    if ($_FILES['instructor_files']['error'][$i] === UPLOAD_ERR_OK) {
                        $file = array(
                            'name'     => $_FILES['instructor_files']['name'][$i],
                            'type'     => $_FILES['instructor_files']['type'][$i],
                            'tmp_name' => $_FILES['instructor_files']['tmp_name'][$i],
                            'error'    => $_FILES['instructor_files']['error'][$i],
                            'size'     => $_FILES['instructor_files']['size'][$i],
                        );
                        $movefile = wp_handle_upload($file, ['test_form'=>false]);
                        if ($movefile && !isset($movefile['error'])) {
                            $instructor_files[] = esc_url_raw($movefile['url']);
                        }
                    }
                }
            }

            // 2) Merge with existing instructor_files if we want to keep them
            $existing_ifiles = json_decode($submission->instructor_files, true);
            if (!is_array($existing_ifiles)) {
                $existing_ifiles = [];
            }
            $all_ifiles = array_merge($existing_ifiles, $instructor_files);
            $json_ifiles = wp_json_encode($all_ifiles);

            // 3) Update submission
            $res = $wpdb->update(
                $submission_table,
                [
                    'grade'            => $new_grade,
                    'status'           => $new_status,
                    'instructor_files' => $json_ifiles
                ],
                ['id'=>$submission_id],
                ['%f','%s','%s'],
                ['%d']
            );
            if ($res === false) {
                echo '<p style="color:red;">' . __('خطأ في تحديث الواجب', 'fashion-academy-lms') . '</p>';
            } else {
                // If 'passed', mark lesson + unlock next
                if ($new_status === 'passed') {
                    $this->mark_lesson_as_passed($submission->user_id, $submission->lesson_id);
                    $this->unlock_next_lesson($submission->user_id, $submission->lesson_id);
                }
                echo '<div class="notice notice-success"><p>' . __('تم حفظ التقييم وملفات المعلم!', 'fashion-academy-lms') . '</p></div>';

                // Refresh submission object
                $submission = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $submission_table WHERE id=%d", $submission_id
                ));
            }
        }

        // Now display the submission details, including any instructor_files
        $uploaded_files = json_decode($submission->uploaded_files, true);
        $notes = $submission->notes;
        $instructor_files = json_decode($submission->instructor_files, true);

        echo '<hr>';
        echo '<h4>' . sprintf(__('تفاصيل الواجب: #%d', 'fashion-academy-lms'), $submission_id) . '</h4>';
        echo '<p><strong>' . __('الحالة', 'fashion-academy-lms') . ':</strong> ' . esc_html($submission->status) . '</p>';
        echo '<p><strong>' . __('الدرجة', 'fashion-academy-lms') . ':</strong> ' . esc_html($submission->grade) . '</p>';
        echo '<p><strong>' . __('ملاحظات الطالب', 'fashion-academy-lms') . ':</strong> ' . esc_html($notes) . '</p>';

        if (!empty($uploaded_files)) {
            echo '<h5>' . __('الملفات المرفقة (من الطالب):', 'fashion-academy-lms') . '</h5><ul>';
            foreach ($uploaded_files as $file_url) {
                echo '<li><a href="' . esc_url($file_url) . '" target="_blank">' . esc_html(basename($file_url)) . '</a></li>';
            }
            echo '</ul>';
        }

        // Show instructor_files if any
        if (!empty($instructor_files)) {
            echo '<h5>' . __('ملفات المدرس المرفقة سابقاً:', 'fashion-academy-lms') . '</h5><ul>';
            foreach ($instructor_files as $ifile_url) {
                echo '<li><a href="' . esc_url($ifile_url) . '" target="_blank">'
                    . esc_html(basename($ifile_url)) . '</a></li>';
            }
            echo '</ul>';
        }

        // Grading form with new input for instructor_files
        ?>
        <form method="post" enctype="multipart/form-data" style="margin-top:15px;">
            <p>
                <label for="grade"><?php _e('التقييم (%):', 'fashion-academy-lms'); ?></label>
                <input type="number" name="grade" id="grade" step="1" min="0" max="100"
                       value="<?php echo esc_attr($submission->grade); ?>" required>
            </p>
            <p>
                <label for="instructor_files"><?php _e('رفع ملفات التصحيح / التعليق (اختياري):', 'fashion-academy-lms'); ?></label>
                <input type="file" name="instructor_files[]" id="instructor_files" multiple />
            </p>
            <input type="hidden" name="fa_grade_submission" value="true" />
            <button type="submit" class="button button-primary"><?php _e('حفظ التقييم', 'fashion-academy-lms'); ?></button>
        </form>
        <?php
    }


    private function mark_lesson_as_passed($user_id, $lesson_id)
    {
        global $wpdb;
        $progress_table = $wpdb->prefix . 'course_progress';

        $existing = $wpdb->get_row($wpdb->prepare(
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
        } else {
            $course_id = get_post_meta($lesson_id, 'lesson_course_id', true);
            $wpdb->insert(
                $progress_table,
                array(
                    'user_id'         => $user_id,
                    'course_id'       => $course_id ?: 0,
                    'lesson_id'       => $lesson_id,
                    'progress_status' => 'passed'
                ),
                array('%d','%d','%d','%s')
            );
        }
    }

    private function unlock_next_lesson($user_id, $current_lesson_id)
    {
        global $wpdb;
        $progress_table = $wpdb->prefix . 'course_progress';

        $current_order = get_post_meta($current_lesson_id, 'lesson_order', true);
        $course_id     = get_post_meta($current_lesson_id, 'lesson_course_id', true);
        if (!$course_id || !$current_order) return;

        $next_lesson = get_posts([
            'post_type'      => 'lesson',
            'posts_per_page' => 1,
            'meta_key'       => 'lesson_order',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'meta_query'     => [
                ['key' => 'lesson_course_id','value' => $course_id],
                ['key' => 'lesson_order','value' => intval($current_order)+1, 'compare' => '=', 'type' => 'NUMERIC']
            ]
        ]);
        if (empty($next_lesson)) return;

        $next_lesson_id = $next_lesson[0]->ID;
        $existing_progress = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $progress_table WHERE user_id=%d AND lesson_id=%d",
            $user_id,
            $next_lesson_id
        ));
        if ($existing_progress) {
            $wpdb->update(
                $progress_table,
                ['progress_status'=>'incomplete'],
                ['id'=>$existing_progress->id],
                ['%s'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $progress_table,
                [
                    'user_id'         => $user_id,
                    'course_id'       => $course_id,
                    'lesson_id'       => $next_lesson_id,
                    'progress_status' => 'incomplete'
                ],
                ['%d','%d','%d','%s']
            );
        }
    }

    /* (B) LESSONS PAGE (CREATE, EDIT, DELETE) */
    /* (B) LESSONS PAGE (CREATE, EDIT, DELETE) */
    private function render_admin_lessons_page()
    {
        // 1) Handle "Add Lesson"
        if (isset($_POST['fa_create_lesson_action']) && $_POST['fa_create_lesson_action'] === 'create_lesson') {
            check_admin_referer('fa_create_lesson_nonce', 'fa_create_lesson_nonce_field');

            $lesson_title  = sanitize_text_field($_POST['lesson_title'] ?? '');
            $course_id     = intval($_POST['course_id'] ?? 0);
            $module_id     = intval($_POST['module_id'] ?? 0); // New field for module assignment
            $video_url     = '';
            $lesson_order  = intval($_POST['lesson_order'] ?? 0);

            if (empty($lesson_title)) {
                echo '<p style="color:red;">' . __('يجب إدخال عنوان الدرس', 'fashion-academy-lms') . '</p>';
            } else {
                // Ensure that if a Module is selected, it belongs to the selected Course
                if ($module_id > 0) {
                    $module_course_id = get_post_meta($module_id, 'module_course_id', true);
                    if ($module_course_id != $course_id) {
                        echo '<p style="color:red;">' . __('المادة المختارة لا تنتمي إلى الكورس المحدد.', 'fashion-academy-lms') . '</p>';
                        return;
                    }
                }

                if (isset($_FILES['video_file']) && !empty($_FILES['video_file']['name'])) {
                    $upload = $this->fa_admin_upload_video_file($_FILES['video_file']);
                    if (is_wp_error($upload)) {
                        echo '<p style="color:red;">' . $upload->get_error_message() . '</p>';
                    } else {
                        $video_url = $upload;
                    }
                }

                $lesson_id = wp_insert_post([
                    'post_title'   => $lesson_title,
                    'post_type'    => 'lesson',
                    'post_status'  => 'publish'
                ], true);

                if (!is_wp_error($lesson_id)) {
                    if ($course_id) {
                        update_post_meta($lesson_id, 'lesson_course_id', $course_id);
                    }
                    if ($module_id) {
                        update_post_meta($lesson_id, 'lesson_module_id', $module_id);
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

        // 2) Check edit or delete
        if (isset($_GET['edit_lesson'])) {
            $this->render_admin_edit_lesson(intval($_GET['edit_lesson']));
            return;
        }
        if (isset($_GET['delete_lesson'])) {
            $this->admin_delete_lesson(intval($_GET['delete_lesson']));
        }

        // "Add Lesson" form
        ?>
        <h3><?php _e('إضافة درس جديد', 'fashion-academy-lms'); ?></h3>
        <form method="post" enctype="multipart/form-data" style="margin-bottom:20px;">
            <?php wp_nonce_field('fa_create_lesson_nonce', 'fa_create_lesson_nonce_field'); ?>
            <input type="hidden" name="fa_create_lesson_action" value="create_lesson"/>

            <p>
                <label for="lesson_title"><?php _e('عنوان الدرس:', 'fashion-academy-lms'); ?></label><br>
                <input type="text" name="lesson_title" id="lesson_title" style="width:300px;">
            </p>
            <p>
                <label for="course_id"><?php _e('اختر الكورس:', 'fashion-academy-lms'); ?></label><br>
                <?php
                $courses = get_posts([
                    'post_type'=>'course',
                    'numberposts'=>-1,
                    'post_status'=>'publish'
                ]);
                ?>
                <select name="course_id" id="course_id">
                    <option value="0"><?php _e('-- لا يوجد --', 'fashion-academy-lms'); ?></option>
                    <?php foreach($courses as $c) {
                        echo '<option value="'. esc_attr($c->ID) .'">'. esc_html($c->post_title) .'</option>';
                    } ?>
                </select>
            </p>
            <p>
                <label for="module_id"><?php _e('اختر المادة (اختياري):', 'fashion-academy-lms'); ?></label><br>
                <?php
                $modules = get_posts([
                    'post_type'=>'module',
                    'numberposts'=>-1,
                    'post_status'=>'publish',
                    'orderby'=>'meta_value_num',
                    'meta_key'=>'module_order',
                    'order'=>'ASC'
                ]);
                ?>
                <select name="module_id" id="module_id">
                    <option value="0"><?php _e('-- لا يوجد --', 'fashion-academy-lms'); ?></option>
                    <?php foreach($modules as $m) {
                        echo '<option value="'. esc_attr($m->ID) .'">'. esc_html($m->post_title) .'</option>';
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
            </p>
            <button type="submit" class="button button-primary"><?php _e('إضافة الدرس', 'fashion-academy-lms'); ?></button>
        </form>
        <?php

        // 3) List existing lessons
        $lessons = get_posts([
            'post_type'=>'lesson',
            'numberposts'=>-1,
            'orderby'=>'meta_value_num',
            'meta_key'=>'lesson_order',
            'order'=>'ASC'
        ]);
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
                <th><?php _e('المادة', 'fashion-academy-lms'); ?></th>
                <th><?php _e('الترتيب', 'fashion-academy-lms'); ?></th>
                <th><?php _e('الفيديو', 'fashion-academy-lms'); ?></th>
                <th><?php _e('إدارة', 'fashion-academy-lms'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($lessons as $lesson) {
                $course_id  = get_post_meta($lesson->ID, 'lesson_course_id', true);
                $course     = get_post($course_id);
                $courseName = $course ? $course->post_title : __('--', 'fashion-academy-lms');

                $module_id  = get_post_meta($lesson->ID, 'lesson_module_id', true);
                $module     = get_post($module_id);
                $moduleName = $module ? $module->post_title : __('--', 'fashion-academy-lms');

                $order      = get_post_meta($lesson->ID, 'lesson_order', true);
                $video      = get_post_meta($lesson->ID, 'lesson_video_url', true);

                echo '<tr>';
                echo '<td>' . esc_html($lesson->ID) . '</td>';
                echo '<td>' . esc_html($lesson->post_title) . '</td>';
                echo '<td>' . esc_html($courseName) . '</td>';
                echo '<td>' . esc_html($moduleName) . '</td>';
                echo '<td>' . esc_html($order) . '</td>';
                echo '<td>' . esc_html($video ? basename($video) : '--') . '</td>';
                // Edit + Delete links
                echo '<td>
                <a href="?admin_page=lessons&edit_lesson='. esc_attr($lesson->ID) .'" class="button button-inline button-primary">
                    <span class="dashicons dashicons-edit"></span> <span class="button-text">تعديل</span>
                </a>
                <a href="?admin_page=lessons&delete_lesson='. esc_attr($lesson->ID) .'" 
                   class="button button-danger button-inline"
                   onclick="return confirm(\'هل أنت متأكد من حذف هذا الدرس؟\');">
                   <span class="dashicons dashicons-trash"></span> <span class="button-text">حذف</span>
                </a>
            </td>';
                echo '</tr>';
            }
            ?>
            </tbody>
        </table>
        <?php
    }


    // The function to handle file uploads (Video)
    private function fa_admin_upload_video_file($file_array)
    {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        if ($file_array['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', __('حدث خطأ عند رفع الملف', 'fashion-academy-lms'));
        }
        // Restrict to certain mime types if desired
        $allowed_mimes = ['video/mp4'=>'video/mp4','video/quicktime'=>'video/quicktime'];
        $check_filetype = wp_check_filetype_and_ext($file_array['tmp_name'], $file_array['name'], false);
        if (! in_array($check_filetype['type'], $allowed_mimes)) {
            return new WP_Error('upload_error', __('هذا النوع من الفيديو غير مسموح به', 'fashion-academy-lms'));
        }

        $attach_id = media_handle_upload('video_file', 0);
        if (is_wp_error($attach_id)) {
            return $attach_id;
        }
        return wp_get_attachment_url($attach_id);
    }

    // Edit existing lesson
    // Edit existing lesson
    private function render_admin_edit_lesson($lesson_id)
    {
        $lesson = get_post($lesson_id);
        if (!$lesson || $lesson->post_type !== 'lesson') {
            echo '<p style="color:red;">' . __('الدرس غير موجود', 'fashion-academy-lms') . '</p>';
            return;
        }

        if (isset($_POST['fa_edit_lesson_action']) && $_POST['fa_edit_lesson_action'] === 'update_lesson') {
            check_admin_referer('fa_edit_lesson_nonce', 'fa_edit_lesson_nonce_field');

            $new_title  = sanitize_text_field($_POST['lesson_title'] ?? '');
            $course_id  = intval($_POST['course_id'] ?? 0);
            $module_id  = intval($_POST['module_id'] ?? 0); // New field for module assignment
            $new_order  = intval($_POST['lesson_order'] ?? 0);
            $video_url  = get_post_meta($lesson_id, 'lesson_video_url', true);

            if (empty($new_title)) {
                echo '<p style="color:red;">' . __('يجب إدخال عنوان الدرس', 'fashion-academy-lms') . '</p>';
            } else {
                // Ensure that if a Module is selected, it belongs to the selected Course
                if ($module_id > 0) {
                    $module_course_id = get_post_meta($module_id, 'module_course_id', true);
                    if ($module_course_id != $course_id) {
                        echo '<p style="color:red;">' . __('المادة المختارة لا تنتمي إلى الكورس المحدد.', 'fashion-academy-lms') . '</p>';
                        return;
                    }
                }

                $update_res = wp_update_post([
                    'ID'         => $lesson_id,
                    'post_title' => $new_title
                ], true);

                if (!is_wp_error($update_res)) {
                    update_post_meta($lesson_id, 'lesson_course_id', $course_id);
                    update_post_meta($lesson_id, 'lesson_order', $new_order);

                    if ($module_id) {
                        update_post_meta($lesson_id, 'lesson_module_id', $module_id);
                    } else {
                        delete_post_meta($lesson_id, 'lesson_module_id'); // Remove module assignment if none selected
                    }

                    if (isset($_FILES['video_file']) && !empty($_FILES['video_file']['name'])) {
                        $upload = $this->fa_admin_upload_video_file($_FILES['video_file']);
                        if (!is_wp_error($upload)) {
                            $video_url = $upload;
                        } else {
                            echo '<p style="color:red;">' . $upload->get_error_message() . '</p>';
                        }
                    }
                    if ($video_url) {
                        update_post_meta($lesson_id, 'lesson_video_url', $video_url);
                    }

                    echo '<div class="notice notice-success"><p>' . __('تم تحديث الدرس بنجاح!', 'fashion-academy-lms') . '</p></div>';
                    $lesson = get_post($lesson_id);
                } else {
                    echo '<p style="color:red;">' . $update_res->get_error_message() . '</p>';
                }
            }
        }

        $current_course_id = get_post_meta($lesson_id, 'lesson_course_id', true);
        $current_module_id = get_post_meta($lesson_id, 'lesson_module_id', true);
        $current_order     = get_post_meta($lesson_id, 'lesson_order', true);
        $current_video     = get_post_meta($lesson_id, 'lesson_video_url', true);

        $courses = get_posts([
            'post_type'=>'course',
            'numberposts'=>-1,
            'post_status'=>'publish'
        ]);

        $modules = get_posts([
            'post_type'=>'module',
            'numberposts'=>-1,
            'post_status'=>'publish',
            'orderby'=>'meta_value_num',
            'meta_key'=>'module_order',
            'order'=>'ASC'
        ]);
        ?>
        <h3><?php _e('تعديل الدرس', 'fashion-academy-lms'); ?></h3>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('fa_edit_lesson_nonce', 'fa_edit_lesson_nonce_field'); ?>
            <input type="hidden" name="fa_edit_lesson_action" value="update_lesson"/>

            <p>
                <label for="lesson_title"><?php _e('عنوان الدرس:', 'fashion-academy-lms'); ?></label><br/>
                <input type="text" name="lesson_title" id="lesson_title" style="width:300px;"
                       value="<?php echo esc_attr($lesson->post_title); ?>"/>
            </p>
            <p>
                <label for="course_id"><?php _e('اختر الكورس:', 'fashion-academy-lms'); ?></label><br/>
                <select name="course_id" id="course_id">
                    <option value="0"><?php _e('-- لا يوجد --', 'fashion-academy-lms'); ?></option>
                    <?php
                    foreach ($courses as $c) {
                        $selected = ($c->ID == $current_course_id) ? 'selected' : '';
                        echo '<option value="' . esc_attr($c->ID) . '" ' . $selected . '>' . esc_html($c->post_title) . '</option>';
                    }
                    ?>
                </select>
            </p>
            <p>
                <label for="module_id"><?php _e('اختر المادة (اختياري):', 'fashion-academy-lms'); ?></label><br/>
                <select name="module_id" id="module_id">
                    <option value="0"><?php _e('-- لا يوجد --', 'fashion-academy-lms'); ?></option>
                    <?php
                    foreach ($modules as $m) {
                        // Only list modules that belong to the selected course
                        $module_course_id = get_post_meta($m->ID, 'module_course_id', true);
                        if ($module_course_id != $current_course_id) continue;

                        $selected = ($m->ID == $current_module_id) ? 'selected' : '';
                        echo '<option value="' . esc_attr($m->ID) . '" ' . $selected . '>' . esc_html($m->post_title) . '</option>';
                    }
                    ?>
                </select>
            </p>
            <p>
                <label for="lesson_order"><?php _e('ترتيب الدرس:', 'fashion-academy-lms'); ?></label><br/>
                <input type="number" name="lesson_order" id="lesson_order" style="width:100px;"
                       value="<?php echo esc_attr($current_order); ?>" min="1"/>
            </p>
            <p>
                <?php if ($current_video): ?>
                    <strong><?php _e('الفيديو الحالي:', 'fashion-academy-lms'); ?></strong>
                    <br/>
                    <?php echo esc_html(basename($current_video)); ?>
                    <br/><br/>
                <?php endif; ?>
                <label for="video_file"><?php _e('رفع فيديو جديد (اختياري):', 'fashion-academy-lms'); ?></label><br/>
                <input type="file" name="video_file" accept="video/*"/>
            </p>
            <button type="submit" class="button button-primary"><?php _e('حفظ التعديلات', 'fashion-academy-lms'); ?></button>
        </form>
        <p><a href="?admin_page=lessons" class="button"><?php _e('عودة إلى الدروس', 'fashion-academy-lms'); ?></a></p>
        <?php
    }


    // Delete lesson
    private function admin_delete_lesson($lesson_id)
    {
        $lesson = get_post($lesson_id);
        if (!$lesson || $lesson->post_type !== 'lesson') {
            echo '<p style="color:red;">' . __('الدرس غير موجود', 'fashion-academy-lms') . '</p>';
            return;
        }
        wp_delete_post($lesson_id, true);
        echo '<div class="notice notice-success"><p>' . __('تم حذف الدرس', 'fashion-academy-lms') . '</p></div>';
    }

    /**
     * Render Admin Modules Management Page
     */
    private function render_admin_modules_page()
    {
        // 1) Handle "Add Module"
        if (isset($_POST['fa_create_module_action']) && $_POST['fa_create_module_action'] === 'create_module') {
            check_admin_referer('fa_create_module_nonce', 'fa_create_module_nonce_field');

            $module_title  = sanitize_text_field($_POST['module_title'] ?? '');
            $course_id     = intval($_POST['course_id'] ?? 0);
            $module_order  = intval($_POST['module_order'] ?? 0);

            if (empty($module_title)) {
                echo '<p style="color:red;">' . __('يجب إدخال عنوان المادة', 'fashion-academy-lms') . '</p>';
            } else {
                $module_id = wp_insert_post([
                    'post_title'   => $module_title,
                    'post_type'    => 'module',
                    'post_status'  => 'publish'
                ], true);

                if (!is_wp_error($module_id)) {
                    if ($course_id) {
                        update_post_meta($module_id, 'module_course_id', $course_id);
                    }
                    if ($module_order > 0) {
                        update_post_meta($module_id, 'module_order', $module_order);
                    }
                    echo '<div class="notice notice-success"><p>'
                        . __('تم إنشاء المادة بنجاح!', 'fashion-academy-lms')
                        . ' (ID=' . $module_id . ')</p></div>';
                } else {
                    echo '<p style="color:red;">' . $module_id->get_error_message() . '</p>';
                }
            }
        }

        // 2) Check edit or delete
        if (isset($_GET['edit_module'])) {
            $this->render_admin_edit_module(intval($_GET['edit_module']));
            return;
        }
        if (isset($_GET['delete_module'])) {
            $this->admin_delete_module(intval($_GET['delete_module']));
        }

        // "Add Module" form
        ?>
        <h3><?php _e('إضافة مادة جديدة', 'fashion-academy-lms'); ?></h3>
        <form method="post" style="margin-bottom:20px;">
            <?php wp_nonce_field('fa_create_module_nonce', 'fa_create_module_nonce_field'); ?>
            <input type="hidden" name="fa_create_module_action" value="create_module"/>

            <p>
                <label for="module_title"><?php _e('عنوان المادة:', 'fashion-academy-lms'); ?></label><br>
                <input type="text" name="module_title" id="module_title" style="width:300px;">
            </p>
            <p>
                <label for="course_id"><?php _e('اختر الكورس:', 'fashion-academy-lms'); ?></label><br>
                <?php
                $courses = get_posts([
                    'post_type'=>'course',
                    'numberposts'=>-1,
                    'post_status'=>'publish'
                ]);
                ?>
                <select name="course_id" id="course_id">
                    <option value="0"><?php _e('-- لا يوجد --', 'fashion-academy-lms'); ?></option>
                    <?php foreach($courses as $c) {
                        echo '<option value="'. esc_attr($c->ID) .'">'. esc_html($c->post_title) .'</option>';
                    } ?>
                </select>
            </p>
            <p>
                <label for="module_order"><?php _e('ترتيب المادة (module_order):', 'fashion-academy-lms'); ?></label><br>
                <input type="number" name="module_order" id="module_order" style="width:100px;" value="1" min="1">
            </p>
            <button type="submit" class="button button-primary"><?php _e('إضافة المادة', 'fashion-academy-lms'); ?></button>
        </form>
        <?php

        // 3) List existing modules
        $modules = get_posts([
            'post_type'=>'module',
            'numberposts'=>-1,
            'orderby'=>'meta_value_num',
            'meta_key'=>'module_order',
            'order'=>'ASC'
        ]);
        if (!$modules) {
            echo '<p>' . __('لا توجد مواد.', 'fashion-academy-lms') . '</p>';
            return;
        }
        ?>
        <h3><?php _e('كل المواد', 'fashion-academy-lms'); ?></h3>
        <table class="widefat">
            <thead>
            <tr>
                <th><?php _e('ID', 'fashion-academy-lms'); ?></th>
                <th><?php _e('عنوان المادة', 'fashion-academy-lms'); ?></th>
                <th><?php _e('الكورس', 'fashion-academy-lms'); ?></th>
                <th><?php _e('الترتيب', 'fashion-academy-lms'); ?></th>
                <th><?php _e('إدارة', 'fashion-academy-lms'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($modules as $module) {
                $course_id  = get_post_meta($module->ID, 'module_course_id', true);
                $course     = get_post($course_id);
                $courseName = $course ? $course->post_title : __('--', 'fashion-academy-lms');
                $order      = get_post_meta($module->ID, 'module_order', true);

                echo '<tr>';
                echo '<td>' . esc_html($module->ID) . '</td>';
                echo '<td>' . esc_html($module->post_title) . '</td>';
                echo '<td>' . esc_html($courseName) . '</td>';
                echo '<td>' . esc_html($order) . '</td>';
                // Edit + Delete links
                echo '<td>
                <a href="?admin_page=modules&edit_module='. esc_attr($module->ID) .'" class="button button-inline button-primary">
                    <span class="dashicons dashicons-edit"></span> <span class="button-text">تعديل</span>
                </a>
                <a href="?admin_page=modules&delete_module='. esc_attr($module->ID) .'" 
                   class="button button-danger button-inline"
                   onclick="return confirm(\'هل أنت متأكد من حذف هذه المادة؟\');">
                   <span class="dashicons dashicons-trash"></span> <span class="button-text">حذف</span>
                </a>
            </td>';
                echo '</tr>';
            }
            ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Handle File Uploads for Modules (if needed)
     * If Modules have associated media, implement similar to lessons
     * Currently, assuming Modules don't require media uploads
     */

    /**
     * Edit existing module
     */
    private function render_admin_edit_module($module_id)
    {
        $module = get_post($module_id);
        if (!$module || $module->post_type !== 'module') {
            echo '<p style="color:red;">' . __('المادة غير موجودة', 'fashion-academy-lms') . '</p>';
            return;
        }

        if (isset($_POST['fa_edit_module_action']) && $_POST['fa_edit_module_action'] === 'update_module') {
            check_admin_referer('fa_edit_module_nonce', 'fa_edit_module_nonce_field');

            $new_title    = sanitize_text_field($_POST['module_title'] ?? '');
            $course_id    = intval($_POST['course_id'] ?? 0);
            $new_order    = intval($_POST['module_order'] ?? 0);

            if (empty($new_title)) {
                echo '<p style="color:red;">' . __('يجب إدخال عنوان المادة', 'fashion-academy-lms') . '</p>';
            } else {
                $update_res = wp_update_post([
                    'ID'         => $module_id,
                    'post_title' => $new_title
                ], true);

                if (!is_wp_error($update_res)) {
                    update_post_meta($module_id, 'module_course_id', $course_id);
                    update_post_meta($module_id, 'module_order', $new_order);

                    echo '<div class="notice notice-success"><p>' . __('تم تحديث المادة بنجاح!', 'fashion-academy-lms') . '</p></div>';
                    $module = get_post($module_id);
                } else {
                    echo '<p style="color:red;">' . $update_res->get_error_message() . '</p>';
                }
            }
        }

        $current_course_id = get_post_meta($module_id, 'module_course_id', true);
        $current_order     = get_post_meta($module_id, 'module_order', true);

        $courses = get_posts([
            'post_type'=>'course',
            'numberposts'=>-1,
            'post_status'=>'publish'
        ]);
        ?>
        <h3><?php _e('تعديل المادة', 'fashion-academy-lms'); ?></h3>
        <form method="post">
            <?php wp_nonce_field('fa_edit_module_nonce', 'fa_edit_module_nonce_field'); ?>
            <input type="hidden" name="fa_edit_module_action" value="update_module"/>

            <p>
                <label for="module_title"><?php _e('عنوان المادة:', 'fashion-academy-lms'); ?></label><br/>
                <input type="text" name="module_title" id="module_title" style="width:300px;"
                       value="<?php echo esc_attr($module->post_title); ?>"/>
            </p>
            <p>
                <label for="course_id"><?php _e('اختر الكورس:', 'fashion-academy-lms'); ?></label><br/>
                <select name="course_id" id="course_id">
                    <option value="0"><?php _e('-- لا يوجد --', 'fashion-academy-lms'); ?></option>
                    <?php
                    foreach ($courses as $c) {
                        $selected = ($c->ID == $current_course_id) ? 'selected' : '';
                        echo '<option value="' . esc_attr($c->ID) . '" ' . $selected . '>' . esc_html($c->post_title) . '</option>';
                    }
                    ?>
                </select>
            </p>
            <p>
                <label for="module_order"><?php _e('ترتيب المادة:', 'fashion-academy-lms'); ?></label><br/>
                <input type="number" name="module_order" id="module_order" style="width:100px;"
                       value="<?php echo esc_attr($current_order); ?>" min="1"/>
            </p>
            <button type="submit" class="button button-primary"><?php _e('حفظ التعديلات', 'fashion-academy-lms'); ?></button>
        </form>
        <p><a href="?admin_page=modules" class="button"><?php _e('عودة إلى المواد', 'fashion-academy-lms'); ?></a></p>
        <?php
    }

    /**
     * Delete a module
     */
    private function admin_delete_module($module_id)
    {
        $module = get_post($module_id);
        if (!$module || $module->post_type !== 'module') {
            echo '<p style="color:red;">' . __('المادة غير موجودة', 'fashion-academy-lms') . '</p>';
            return;
        }

        // Before deleting, unassign lessons from this module
        $lessons = get_posts([
            'post_type'      => 'lesson',
            'posts_per_page' => -1,
            'meta_key'       => 'lesson_module_id',
            'meta_value'     => $module_id,
        ]);

        foreach ($lessons as $lesson) {
            update_post_meta($lesson->ID, 'lesson_module_id', '');
        }

        wp_delete_post($module_id, true);
        echo '<div class="notice notice-success"><p>' . __('تم حذف المادة بنجاح.', 'fashion-academy-lms') . '</p></div>';
    }

    // ========== STUDENTS PAGE ==========
    private function render_admin_students_page()
    {
        ?>
        <h3><?php _e('البحث عن الطلاب', 'fashion-academy-lms'); ?></h3>
        <form method="get">
            <input type="hidden" name="admin_page" value="students"/>
            <label for="student_search"><?php _e('البحث:', 'fashion-academy-lms'); ?></label>
            <input type="text" name="student_search" id="student_search"
                   value="<?php echo esc_attr($_GET['student_search'] ?? ''); ?>"/>
            <button type="submit" class="button"><?php _e('بحث', 'fashion-academy-lms'); ?></button>
        </form>
        <?php

        if (isset($_GET['view_student'])) {
            $this->render_admin_student_profile(intval($_GET['view_student']));
            return;
        }

        if (!empty($_GET['student_search'])) {
            $search = sanitize_text_field($_GET['student_search']);
            $args = [
                'role'           => 'student',
                'search'         => "*{$search}*",
                'search_columns' => ['user_login','user_email','display_name']
            ];
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
                        <th><?php _e('إدارة', 'fashion-academy-lms'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    foreach($users as $u) {
                        echo '<tr>';
                        echo '<td>' . esc_html($u->ID) . '</td>';
                        echo '<td>' . esc_html($u->display_name) . '</td>';
                        echo '<td>' . esc_html($u->user_email) . '</td>';
                        echo '<td>
                            <a href="?admin_page=students&view_student='. esc_attr($u->ID) .'" class="button">'
                            . __('عرض الملف', 'fashion-academy-lms') . '</a></td>';
                        echo '</tr>';
                    }
                    ?>
                    </tbody>
                </table>
                <?php
            }
        }
    }

    private function render_admin_student_profile($user_id)
    {
        $user_info = get_userdata($user_id);
        if (!$user_info) {
            echo '<p style="color:red;">' . __('المستخدم غير موجود', 'fashion-academy-lms') . '</p>';
            return;
        }

        if (isset($_POST['fa_update_student_profile']) && $_POST['fa_update_student_profile'] === 'yes') {
            check_admin_referer('fa_update_student_nonce', 'fa_update_student_nonce_field');

            $display_name = sanitize_text_field($_POST['display_name'] ?? '');
            $email        = sanitize_email($_POST['email'] ?? '');

            if (empty($display_name) || empty($email)) {
                echo '<p style="color:red;">' . __('يرجى تعبئة كافة الحقول.', 'fashion-academy-lms') . '</p>';
            } else {
                $res = wp_update_user([
                    'ID'           => $user_id,
                    'display_name' => $display_name,
                    'user_email'   => $email
                ]);
                if (is_wp_error($res)) {
                    echo '<p style="color:red;">' . $res->get_error_message() . '</p>';
                } else {
                    echo '<div class="notice notice-success"><p>' . __('تم تحديث بيانات الطالب بنجاح!', 'fashion-academy-lms') . '</p></div>';
                    $user_info = get_userdata($user_id);
                }
            }
        }
        ?>
        <h3><?php _e('ملف الطالب', 'fashion-academy-lms'); ?>: <?php echo esc_html($user_info->display_name); ?></h3>
        <form method="post">
            <?php wp_nonce_field('fa_update_student_nonce', 'fa_update_student_nonce_field'); ?>
            <input type="hidden" name="fa_update_student_profile" value="yes"/>

            <p>
                <label for="display_name"><?php _e('الاسم المعروض', 'fashion-academy-lms'); ?>:</label><br/>
                <input type="text" name="display_name" id="display_name" style="width:300px;"
                       value="<?php echo esc_attr($user_info->display_name); ?>"/>
            </p>
            <p>
                <label for="email"><?php _e('البريد الإلكتروني', 'fashion-academy-lms'); ?>:</label><br/>
                <input type="email" name="email" id="email" style="width:300px;"
                       value="<?php echo esc_attr($user_info->user_email); ?>"/>
            </p>

            <button type="submit" class="button button-primary"><?php _e('حفظ', 'fashion-academy-lms'); ?></button>
        </form>
        <p><a href="?admin_page=students" class="button"><?php _e('عودة للبحث', 'fashion-academy-lms'); ?></a></p>
        <?php
    }


    /**
     * 1) Render the homework form on the front end
     */
    public function render_homework_form($atts = [])
    {
        // Ensure user is logged in
        if (!is_user_logged_in()) {
            return '<p>' . __('You must be logged in to submit homework.', 'fashion-academy-lms') . '</p>';
        }

        $atts = shortcode_atts([
            'lesson_id' => 0,
        ], $atts);

        // If not set in $atts, fallback to $_GET
        $lesson_id = $atts['lesson_id'] ? $atts['lesson_id'] : (int) ($_GET['lesson_id'] ?? 0);

        // If still zero, fallback to get_the_ID() last
        if (!$lesson_id) {
            $lesson_id = get_the_ID();
        }

        // Now $lesson_id is correct. Next get course_id:
        $course_id = (int) get_post_meta($lesson_id, 'lesson_course_id', true);

        fa_plugin_log('Homework form logs => Lesson ID: ' . $lesson_id . ', Course ID: ' . $course_id);

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
        $notes = $existing_submission ? esc_textarea($existing_submission->notes) : '';

        // Build the form HTML with file preview and removal option
        ob_start();
        ?>
        <form method="post" enctype="multipart/form-data" class="fa-homework-form" id="fa-homework-form">
            <?php wp_nonce_field('fa_homework_submission', 'fa_homework_nonce'); ?>
            <input type="hidden" name="fa_action" value="submit_homework"/>
            <input type="hidden" name="lesson_id" value="<?php echo esc_attr($lesson_id); ?>"/>
            <input type="hidden" name="course_id" value="<?php echo esc_attr($course_id); ?>"/>

            <p>
                <label for="homework_files"><?php _e('Upload your homework (images, PDFs, etc.):', 'fashion-academy-lms'); ?></label><br>
                <input type="file" name="homework_files[]" id="homework_files" multiple="multiple"
                       accept=".jpg,.jpeg,.png,.pdf"/>
            </p>

            <div id="file_preview">
                <?php if (!empty($uploaded_files) && is_array($uploaded_files)) : ?>
                    <?php foreach ($uploaded_files as $index => $file_url) : ?>
                        <div class="fa-file-preview">
                            <span><?php echo esc_html(basename($file_url)); ?></span>
                            <button type="button" class="fa-remove-file" data-index="<?php echo esc_attr($index); ?>">
                                Remove
                            </button>
                            <input type="hidden" name="existing_files[]" value="<?php echo esc_attr($file_url); ?>"/>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <p>
                <label for="homework_notes"><?php _e('Any notes or comments:', 'fashion-academy-lms'); ?></label><br>
                <textarea name="homework_notes" id="homework_notes" rows="4"
                          cols="50"><?php echo esc_textarea($notes); ?></textarea>
            </p>

            <p>
                <input type="submit" value="<?php _e('Submit Homework', 'fashion-academy-lms'); ?>"/>
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

        if (isset($_GET['homework_submitted']) && $_GET['homework_submitted'] === 'true') {
            echo '<p class="fa-success-message">' . __('Your homework has been submitted successfully!', 'fashion-academy-lms') . '</p>';
        }

        return ob_get_clean();
    }

    /**
     * 2) Handle form submission (runs on 'init')
     */
    public function handle_homework_submission()
    {

        global $wpdb;
        $submission_table = $wpdb->prefix . 'homework_submissions';

        // 1) Check if user requested "retake"
        if (isset($_GET['retake_homework'])) {
            $submission_id = intval($_GET['retake_homework']);

            // Reset that submission
            $wpdb->update(
                $submission_table,
                [
                    'status'         => 'retake',
                    'grade'          => 0,
                    'uploaded_files' => '[]',
                ],
                ['id' => $submission_id],
                ['%s','%f','%s'],
                ['%d']
            );

            // Find which lesson that submission belongs to
            $lesson_id_for_retake = $wpdb->get_var($wpdb->prepare(
                "SELECT lesson_id FROM $submission_table WHERE id = %d",
                $submission_id
            ));

            // Now redirect back to that lesson page, removing the 'retake_homework' param
            // We'll do something like: ?lesson_id=XXX (assuming your Student Dashboard uses ?lesson_id= for display)
            $dashboard_url = add_query_arg(
                array(
                    'lesson_id' => $lesson_id_for_retake,
                    // maybe 'retake_done' => 1
                ),
                site_url('/student-dashboard')
            );

            wp_redirect(remove_query_arg('retake_homework', $dashboard_url));
            exit;

        }

        if (isset($_POST['fa_action']) && $_POST['fa_action'] === 'submit_homework') {

            // Log the $_FILES array for debugging
            fa_plugin_log("Homework Files: " . print_r($_FILES['homework_files'], true));

            // Verify nonce for security
            if (!isset($_POST['fa_homework_nonce']) || !wp_verify_nonce($_POST['fa_homework_nonce'], 'fa_homework_submission')) {
                wp_die(__('Security check failed.', 'fashion-academy-lms'));
            }

            // Ensure the user is logged in
            if (!is_user_logged_in()) return;

            $user_id = get_current_user_id();
            $lesson_id = isset($_POST['lesson_id']) ? (int)$_POST['lesson_id'] : 0;
            $course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
            $notes = isset($_POST['homework_notes']) ? sanitize_textarea_field($_POST['homework_notes']) : '';


            // If course_id is missing, forcibly fetch
            if ($lesson_id && !$course_id) {
                $maybe_course_id = (int) get_post_meta($lesson_id, 'lesson_course_id', true);
                if ($maybe_course_id > 0) {
                    $course_id = $maybe_course_id;
                }
            }

            // Validate lesson and course IDs
            if (!$lesson_id || !$course_id) {
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

            if (!empty($_FILES['homework_files']['name'][0])) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');

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
                    if (!in_array($_FILES['homework_files']['type'][$i], $allowed_types)) {
                        fa_plugin_log("Invalid file type for file: " . $_FILES['homework_files']['name'][$i]);
                        continue; // Skip invalid file types
                    }

                    // Validate file size
                    if ($_FILES['homework_files']['size'][$i] > $max_size) {
                        fa_plugin_log("File size exceeded for file: " . $_FILES['homework_files']['name'][$i]);
                        continue; // Skip large files
                    }

                    $file = array(
                        'name' => $_FILES['homework_files']['name'][$i],
                        'type' => $_FILES['homework_files']['type'][$i],
                        'tmp_name' => $_FILES['homework_files']['tmp_name'][$i],
                        'error' => $_FILES['homework_files']['error'][$i],
                        'size' => $_FILES['homework_files']['size'][$i],
                    );

                    $upload_overrides = array('test_form' => false);
                    $movefile = wp_handle_upload($file, $upload_overrides);

                    if ($movefile && !isset($movefile['error'])) {
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

            if ($existing_submission) {
                // Update the existing submission
                $update_result = $wpdb->update(
                    $submission_table,
                    array(
                        'submission_date' => current_time('mysql'),
                        'status' => 'pending',
                        'grade' => 0, // Reset grade
                        'uploaded_files' => $json_files,
                        'notes' => $notes,
                    ),
                    array('id' => $existing_submission->id),
                    array(
                        '%s',
                        '%s',
                        '%f',
                        '%s',
                        '%s',
                    ),
                    array('%d')
                );

                if (false === $update_result) {
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
                        'user_id' => $user_id,
                        'course_id' => $course_id,
                        'lesson_id' => $lesson_id,
                        'submission_date' => current_time('mysql'),
                        'status' => 'pending',
                        'grade' => 0, // default
                        'uploaded_files' => $json_files,
                        'notes' => $notes,
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

                if (false === $insert_result) {
                    fa_plugin_log("Failed to insert new submission for user ID $user_id, lesson ID $lesson_id");
                    wp_die(__('Failed to submit your homework. Please try again.', 'fashion-academy-lms'));
                }

                $submission_id = $wpdb->insert_id;
                fa_plugin_log("Inserted new submission ID: {$submission_id}");
            }

            // Optional: Log successful submission
            fa_plugin_log("Homework submission successful. Submission ID: $submission_id");

            $dashboard_url = add_query_arg(
                array(
                    'lesson_id'         => $lesson_id,
                    'homework_submitted'=> 'true'
                ),
                site_url('/student-dashboard')
            );
            wp_redirect($dashboard_url);
            exit;
        }
    }

    /**
     * Restrict access to lessons based on user's progress
     */
    public function restrict_lesson_access()
    {
        if (!is_singular('lesson')) {
            return; // Only restrict single lesson pages
        }

        if (!is_user_logged_in()) {
            // Redirect non-logged-in users to login page
            wp_redirect(wp_login_url(get_permalink()));
            exit;
        }

        global $post, $wpdb;
        $user_id = get_current_user_id();
        $lesson_id = $post->ID;

        // Fetch course ID from lesson meta
        $course_id = get_post_meta($lesson_id, 'lesson_course_id', true);

        if (!$course_id) {
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
            'post_type' => 'lesson',
            'posts_per_page' => -1,
            'meta_key' => 'lesson_order',
            'orderby' => 'meta_value_num',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => 'lesson_course_id',
                    'value' => $course_id,
                    'compare' => '='
                ),
                array(
                    'key' => 'lesson_order',
                    'value' => intval($current_order) - 1,
                    'compare' => '<=',
                    'type' => 'NUMERIC'
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

            if ($progress !== 'passed') {
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
    public function display_restricted_lesson_notice()
    {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        if (get_transient('fa_restricted_lesson_notice_' . $user_id)) {
            echo '<div class="notice notice-error is-dismissible fa-restricted-lesson-notice">
                    <p>' . __('You must complete the previous lessons to access this one.', 'fashion-academy-lms') . '</p>
                  </div>';
            delete_transient('fa_restricted_lesson_notice_' . $user_id);
        }
    }

}

?>