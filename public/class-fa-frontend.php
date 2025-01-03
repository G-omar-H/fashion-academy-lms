<?php
if (!defined('ABSPATH')) exit;

class FA_Frontend
{
    // Inside class FA_Frontend
    public function __construct()
    {
        // Shortcodes
        add_shortcode('fa_homework_form', array($this, 'render_homework_form'));
        add_shortcode('fa_custom_register', array($this, 'render_registration_form'));
        add_shortcode('fa_custom_login', array($this, 'render_login_form'));
        add_shortcode('fa_student_dashboard', array($this, 'render_student_dashboard'));
        add_shortcode('fa_admin_dashboard', array($this, 'render_admin_dashboard'));
        add_shortcode('fa_student_chat', array($this, 'render_student_chat'));

        // Form Submissions
        add_action('init', array($this, 'process_registration_form'));
        add_action('init', array($this, 'process_login_form'));
        add_action('init', array($this, 'handle_homework_submission'));
        add_action('template_redirect', array($this, 'restrict_lesson_access'));

        // Enqueue Scripts and Styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // AJAX Actions for Chat
        add_action('wp_ajax_fa_send_chat_message', array($this, 'fa_send_chat_message'));
        add_action('wp_ajax_fa_fetch_chat_messages', array($this, 'fa_fetch_chat_messages'));
        add_action('wp_ajax_fa_fetch_unread_count', array($this, 'fa_fetch_unread_count'));
    }


    public function enqueue_assets()
    {
        // Enqueue the main frontend stylesheet
        wp_enqueue_style(
            'fa-frontend-style',
            plugin_dir_url(__FILE__) . '../assets/css/frontend.css',
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

        // Enqueue the frontend JavaScript
        wp_enqueue_script(
            'fa-frontend-script',
            plugin_dir_url(__FILE__) . '../assets/js/frontend.js',
            array('jquery'),
            '1.2.0',
            true
        );

        // Enqueue Chat CSS
        wp_enqueue_style(
            'fa-chat-style',
            plugin_dir_url(__FILE__) . '../assets/css/chat.css',
            array(),
            '1.0.0',
            'all'
        );

        // Enqueue Chat JavaScript
        wp_enqueue_script(
            'fa-chat-script',
            plugin_dir_url(__FILE__) . '../assets/js/chat.js',
            array('jquery'),
            '1.0.0',
            true
        );

        // Localize script with chat-specific data
        wp_localize_script('fa-chat-script', 'faChat', array(
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('fa_chat_nonce'),
            'currentUserId'  => get_current_user_id(),
            'adminUserId'    => get_option('fa_admin_user_id'),
            'errorMessage'   => __('حدث خطأ أثناء الاتصال بالدردشة.', 'fashion-academy-lms')
        ));

        // Enqueue Admin Chat CSS and JS if on admin chat page
        if (is_admin() && isset($_GET['admin_page']) && $_GET['admin_page'] === 'chats') {
            wp_enqueue_style(
                'fa-admin-chat-style',
                plugin_dir_url(__FILE__) . '../assets/css/admin-chat.css',
                array(),
                '1.0.0',
                'all'
            );

            wp_enqueue_script(
                'fa-admin-chat-script',
                plugin_dir_url(__FILE__) . '../assets/js/admin-chat.js',
                array('jquery'),
                '1.0.0',
                true
            );

            wp_localize_script('fa-admin-chat-script', 'faChat', array(
                'ajaxUrl'        => admin_url('admin-ajax.php'),
                'nonce'          => wp_create_nonce('fa_chat_nonce'),
                'currentUserId'  => get_current_user_id(),
                'adminUserId'    => get_option('fa_admin_user_id')
            ));
        }

        // Localize frontend script with general data
        $localized_data = $this->get_translated_script_data();
        wp_localize_script('fa-frontend-script', 'faLMS', $localized_data);
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
            <p>
                <label for="reg_whatsapp"><?php _e('رقم واتساب', 'fashion-academy-lms'); ?></label><br/>
                <input type="tel" name="reg_whatsapp" id="reg_whatsapp" required
                       pattern="[0-9]+" title="<?php esc_attr_e('يرجى إدخال رقم صحيح بدون أي أحرف.', 'fashion-academy-lms'); ?>"/>
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

    public function process_registration_form()
    {
        if (isset($_POST['fa_registration_action']) && $_POST['fa_registration_action'] === 'fa_register_user') {
            // Verify nonce for security
            if (!isset($_POST['fa_register_nonce_field']) ||
                !wp_verify_nonce($_POST['fa_register_nonce_field'], 'fa_register_nonce')) {
                wp_die(__('فشل التحقق الأمني', 'fashion-academy-lms'));
            }

            // Sanitize input fields
            $name     = sanitize_text_field($_POST['reg_name'] ?? '');
            $email    = sanitize_email($_POST['reg_email'] ?? '');
            $password = sanitize_text_field($_POST['reg_password'] ?? '');
            $whatsapp = sanitize_text_field($_POST['reg_whatsapp'] ?? '');

            // Check if all required fields are filled
            if (empty($name) || empty($email) || empty($password) || empty($whatsapp)) {
                wp_die(__('يجب تعبئة كافة الحقول المطلوبة', 'fashion-academy-lms'));
            }

            // Validate WhatsApp number
            // Must match an international format: +1234567890
            if (!preg_match('/^\+?[1-9][0-9]{9,14}$/', $whatsapp)) {
                wp_die(__('يرجى إدخال رقم واتساب صالح بصيغة دولية (مثل: +1234567890)', 'fashion-academy-lms'));
            }

            // Check if username or email exists
            if (username_exists($name) || email_exists($email)) {
                wp_die(__('اسم المستخدم أو البريد الإلكتروني مستخدم مسبقًا', 'fashion-academy-lms'));
            }

            // Attempt to create a new user
            $user_id = wp_create_user($name, $password, $email);
            if (is_wp_error($user_id)) {
                wp_die($user_id->get_error_message());
            }

            // Save WhatsApp number as user meta
            update_user_meta($user_id, 'whatsapp_number', $whatsapp);

            // Assign the 'student' role to the new user
            $user = new WP_User($user_id);
            $user->set_role('student');

            // Initialize user progress
            $this->initialize_user_progress($user_id);

            // Automatically log in the new user
            $this->auto_login_user($name, $password);

            // Redirect to the student dashboard
            wp_redirect(site_url('/student-dashboard'));
            exit;
        }
    }

    /**
     * Initialize user progress by unlocking the first lesson and setting module payments.
     *
     * @param int $user_id The ID of the newly registered user.
     */
    private function initialize_user_progress($user_id)
    {
        global $wpdb;

        // Assuming there's only one course
        $courses = get_posts([
            'post_type'      => 'course',
            'posts_per_page' => 1,
            'post_status'    => 'publish'
        ]);

        if (empty($courses)) {
            // No courses found, cannot initialize progress
            fa_plugin_log('No courses found to initialize user progress.');
            return;
        }

        $course_id = $courses[0]->ID;

        // Get all modules in the course ordered by 'module_order'
        $modules = get_posts([
            'post_type'      => 'module',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'meta_value_num',
            'meta_key'       => 'module_order',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'     => 'module_course_id',
                    'value'   => $course_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC'
                ]
            ]
        ]);

        // Identify the first module
        $first_module_id = 0;
        if (!empty($modules)) {
            $first_module = $modules[0];
            $first_module_id = $first_module->ID;
        }

        // Initialize module payments: first module 'paid', others 'unpaid'
        foreach ($modules as $index => $module) {
            $module_id = $module->ID;
            $existing_payment = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}course_module_payments WHERE user_id=%d AND module_id=%d",
                $user_id,
                $module_id
            ));

            if (!$existing_payment) {
                $payment_status = ($module_id === $first_module_id) ? 'paid' : 'unpaid';
                $payment_date = ($payment_status === 'paid') ? current_time('mysql') : null;

                $wpdb->insert(
                    "{$wpdb->prefix}course_module_payments",
                    [
                        'user_id'         => $user_id,
                        'module_id'       => $module_id,
                        'payment_status'  => $payment_status,
                        'payment_date'    => $payment_date
                    ],
                    ['%d','%d','%s','%s']
                );
            }
        }

        // Get the first lesson based on lesson_order
        $first_lesson = get_posts([
            'post_type'      => 'lesson',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_key'       => 'lesson_order',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'     => 'lesson_course_id',
                    'value'   => $course_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC'
                ]
            ]
        ]);

        if (empty($first_lesson)) {
            fa_plugin_log('No lessons found for the course to initialize user progress.');
            return;
        }

        $first_lesson_id = $first_lesson[0]->ID;

        // Insert progress for the first lesson as 'incomplete' (available to access)
        $progress_table = $wpdb->prefix . 'course_progress';
        $existing_progress = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $progress_table WHERE user_id=%d AND lesson_id=%d",
            $user_id,
            $first_lesson_id
        ));

        if (!$existing_progress) {
            $wpdb->insert(
                $progress_table,
                [
                    'user_id'         => $user_id,
                    'course_id'       => $course_id,
                    'lesson_id'       => $first_lesson_id,
                    'progress_status' => 'incomplete'
                ],
                ['%d','%d','%d','%s']
            );
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
    public function render_student_dashboard()
    {
        global $wpdb;

        if (!is_user_logged_in()) {
            return '<p>' . __('الرجاء تسجيل الدخول', 'fashion-academy-lms') . '</p>';
        }

        $user_id = get_current_user_id();

        // Get user information for greeting
        $current_user = wp_get_current_user();
        $user_name = $current_user->display_name;

        // Determine the current lesson
        $current_lesson = $this->get_current_lesson_for_user($user_id);

        if ($current_lesson) {
            // If a specific lesson is not selected via GET, redirect to current lesson
            if (!isset($_GET['lesson_id'])) {
                wp_redirect('?lesson_id=' . $current_lesson->ID);
                exit;
            }

            $current_lesson_id = intval($_GET['lesson_id']);
        } else {
            // No progress found, possibly new user without initialized progress
            return '<p>' . __('لا توجد دروس متاحة.', 'fashion-academy-lms') . '</p>';
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

        // Determine the module ID of the currently selected lesson
        $current_lesson_id = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;
        $selected_module_id = 0;

        if ($current_lesson_id) {
            $selected_module_id = get_post_meta($current_lesson_id, 'lesson_module_id', true);
        }

        // Identify the first module
        $first_module_id = 0;
        if (!empty($modules)) {
            $first_module = $modules[0];
            $first_module_id = $first_module->ID;
        }

        // Fetch grades for lessons
        // Assuming there's a table that stores grades: homework_submissions (user_id, lesson_id, grade)
        $grades = $wpdb->get_results($wpdb->prepare(
            "SELECT lesson_id, grade FROM {$wpdb->prefix}homework_submissions WHERE user_id = %d",
            $user_id
        ), OBJECT_K); // KEY BY lesson_id

        ob_start(); ?>
        <div class="fa-student-dashboard-container">
            <div class="fa-student-dashboard-layout">
                <aside class="fa-lessons-sidebar" aria-label="<?php _e('قائمة الدروس', 'fashion-academy-lms'); ?>">
                    <div class="fa-dashboard-greeting">
                        <h2><?php echo sprintf(__('مرحباً، %s!', 'fashion-academy-lms'), esc_html($user_name)); ?></h2>
                    </div>
                    <h3><?php _e('المحتوى التعليمي', 'fashion-academy-lms'); ?></h3>
                    <ul class="fa-modules-list">
                        <?php
                        // Iterate through each module
                        foreach ($modules as $module) {
                            // Determine if this module should be expanded
                            $is_expanded = ($module->ID == $selected_module_id);
                            $aria_expanded = $is_expanded ? 'true' : 'false';

                            // Determine if the module is the first module
                            $is_first_module = ($module->ID == $first_module_id);

                            // Check payment status for the module (skip for first module)
                            if ($is_first_module) {
                                $module_paid = true;
                            } else {
                                $payment = $wpdb->get_row($wpdb->prepare(
                                    "SELECT payment_status FROM {$wpdb->prefix}course_module_payments WHERE user_id=%d AND module_id=%d",
                                    $user_id,
                                    $module->ID
                                ));
                                $module_paid = $payment && $payment->payment_status === 'paid';
                            }

                            // Assign classes based on payment status
                            $module_item_classes = 'fa-module-item';
                            if (!$module_paid) {
                                $module_item_classes .= ' fa-module-locked';
                            }

                            echo '<li class="' . esc_attr($module_item_classes) . '">';
                            // Module Toggle Button with Lock/Unlock Icon
                            echo '<button type="button" class="fa-module-toggle" aria-expanded="' . esc_attr($aria_expanded) . '" aria-controls="module-' . esc_attr($module->ID) . '">';
                            echo '<span class="fa-module-title">' . esc_html($module->post_title) . '</span>';

                            // Icon indicating lock/unlock status
                            if ($module_paid) {
                                // Unlocked module: show '-' if expanded, '+' if collapsed
                                $initial_icon_class = $is_expanded ? 'fas fa-minus' : 'fas fa-plus';
                                echo '<span class="fa-toggle-icon"><i class="' . esc_attr($initial_icon_class) . '" title="' . __('مدفوع', 'fashion-academy-lms') . '"></i></span>';
                            } else {
                                // Locked module: always show lock icon with badge
                                echo '<span class="fa-toggle-icon"><i class="fas fa-lock" title="' . __('غير مدفوع', 'fashion-academy-lms') . '"></i></span>';
                                echo '<span class="fa-module-badge">' . __('مغلق', 'fashion-academy-lms') . '</span>';
                            }
                            echo '</button>';

                            // Lessons Sublist
                            echo '<ul id="module-' . esc_attr($module->ID) . '" class="fa-lessons-sublist" ' . ($is_expanded ? '' : 'hidden') . '>';

                            if (isset($lessons_by_module[$module->ID])) {
                                foreach ($lessons_by_module[$module->ID] as $lesson) {
                                    $lesson_order = get_post_meta($lesson->ID, 'lesson_order', true);
                                    $locked = $this->is_lesson_locked_for_current_user($lesson->ID);
                                    $is_active = ($lesson->ID == $current_lesson_id);
                                    $lesson_grade = isset($grades[$lesson->ID]->grade) ? $grades[$lesson->ID]->grade : null;
                                    $is_passed = ($lesson_grade !== null && $lesson_grade >= 75);
                                    $is_outstanding = ($lesson_grade !== null && $lesson_grade >= 90);
                                    $is_failed = ($lesson_grade !== null && $lesson_grade < 75); // New condition

                                    // Determine the grade class based on the grade
                                    if ($is_outstanding) {
                                        $grade_class = 'fa-grade-outstanding';
                                        $icon_class = 'fas fa-star';
                                        $icon_title = __('ممتاز', 'fashion-academy-lms');
                                    } elseif ($is_passed) {
                                        $grade_class = 'fa-grade-passed';
                                        $icon_class = 'fas fa-check-circle';
                                        $icon_title = __('تم التجاوز', 'fashion-academy-lms');
                                    } elseif ($is_failed) {
                                        $grade_class = 'fa-grade-failed';
                                        $icon_class = 'fas fa-exclamation-triangle';
                                        $icon_title = __('فشل', 'fashion-academy-lms');
                                    } else {
                                        $grade_class = '';
                                        $icon_class = '';
                                        $icon_title = '';
                                    }

                                    echo '<li class="fa-lesson-item">';
                                    if (!$locked) {
                                        $active_class = $is_active ? ' active-lesson' : '';
                                        echo '<a href="?lesson_id=' . esc_attr($lesson->ID) . '" class="' . esc_attr($active_class) . '">';
                                        // **New Layout: Grade Icon and Percentage to the Left**
                                        if ($lesson_grade !== null) {
                                            echo '<span class="fa-grade-info ' . esc_attr($grade_class) . '">';
                                            if ($icon_class) {
                                                echo '<i class="' . esc_attr($icon_class) . ' fa-grade-icon" title="' . esc_attr($icon_title) . '"></i> ';
                                            }
                                            echo intval($lesson_grade) . '%';
                                            echo '</span> ';
                                        }
                                        // Lesson Title without order number
                                        echo esc_html($lesson->post_title);
                                        echo '</a>';
                                    } else {
                                        echo '<span class="fa-locked-lesson">';
                                        echo '<i class="fas fa-lock"></i> ' . esc_html($lesson->post_title);
                                        echo '</span>';
                                    }
                                    echo '</li>';
                                }
                            } else {
                                echo '<li>' . __('لا توجد دروس في هذه المادة.', 'fashion-academy-lms') . '</li>';
                            }

                            echo '</ul></li>';
                        }

                        // List unassigned lessons under a separate heading as a module
                        if (!empty($unassigned_lessons)) {
                            // Determine if the unassigned module should be expanded
                            $is_expanded = false; // By default, collapsed
                            if ($current_lesson_id) {
                                foreach ($unassigned_lessons as $lesson) {
                                    if ($lesson->ID == $current_lesson_id) {
                                        $is_expanded = true;
                                        break;
                                    }
                                }
                            }
                            $aria_expanded = $is_expanded ? 'true' : 'false';
                            $toggle_icon_class = $is_expanded ? 'fas fa-minus' : 'fas fa-plus';
                            $sublist_hidden = $is_expanded ? '' : 'hidden';

                            // Since unassigned lessons are always unlocked, no need to check payment
                            echo '<li class="fa-module-item">';
                            echo '<button type="button" class="fa-module-toggle" aria-expanded="' . esc_attr($aria_expanded) . '" aria-controls="module-unassigned">';
                            echo '<span class="fa-module-title">' . __('دروس بدون مادة', 'fashion-academy-lms') . '</span>';
                            echo '<span class="fa-toggle-icon"><i class="' . esc_attr($toggle_icon_class) . '" title="' . __('دروس بدون مادة', 'fashion-academy-lms') . '"></i></span>'; // Consistent Font Awesome icon
                            echo '</button>';

                            echo '<ul id="module-unassigned" class="fa-lessons-sublist" ' . ($sublist_hidden ? 'hidden' : '') . '>';

                            foreach ($unassigned_lessons as $lesson) {
                                $lesson_order = get_post_meta($lesson->ID, 'lesson_order', true);
                                $locked = $this->is_lesson_locked_for_current_user($lesson->ID);
                                $is_active = ($lesson->ID == $current_lesson_id);
                                $lesson_grade = isset($grades[$lesson->ID]->grade) ? $grades[$lesson->ID]->grade : null;
                                $is_passed = ($lesson_grade !== null && $lesson_grade >= 75);
                                $is_outstanding = ($lesson_grade !== null && $lesson_grade >= 90);
                                $is_failed = ($lesson_grade !== null && $lesson_grade < 75); // New condition

                                // Determine the grade class based on the grade
                                if ($is_outstanding) {
                                    $grade_class = 'fa-grade-outstanding';
                                    $icon_class = 'fas fa-star';
                                    $icon_title = __('ممتاز', 'fashion-academy-lms');
                                } elseif ($is_passed) {
                                    $grade_class = 'fa-grade-passed';
                                    $icon_class = 'fas fa-check-circle';
                                    $icon_title = __('تم التجاوز', 'fashion-academy-lms');
                                } elseif ($is_failed) {
                                    $grade_class = 'fa-grade-failed';
                                    $icon_class = 'fas fa-exclamation-triangle';
                                    $icon_title = __('فشل', 'fashion-academy-lms');
                                } else {
                                    $grade_class = '';
                                    $icon_class = '';
                                    $icon_title = '';
                                }

                                echo '<li class="fa-lesson-item">';
                                if (!$locked) {
                                    $active_class = $is_active ? ' active-lesson' : '';
                                    echo '<a href="?lesson_id=' . esc_attr($lesson->ID) . '" class="' . esc_attr($active_class) . '">';
                                    // **New Layout: Grade Icon and Percentage to the Left**
                                    if ($lesson_grade !== null) {
                                        echo '<span class="fa-grade-info ' . esc_attr($grade_class) . '">';
                                        if ($icon_class) {
                                            echo '<i class="' . esc_attr($icon_class) . ' fa-grade-icon" title="' . esc_attr($icon_title) . '"></i> ';
                                        }
                                        echo intval($lesson_grade) . '%';
                                        echo '</span> ';
                                    }
                                    // Lesson Title without order number
                                    echo esc_html($lesson->post_title);
                                    echo '</a>';
                                } else {
                                    echo '<span class="fa-locked-lesson">';
                                    echo '<i class="fas fa-lock"></i> ' . esc_html($lesson->post_title);
                                    echo '</span>';
                                }
                                echo '</li>';
                            }

                            echo '</ul></li>';
                        }
                        ?>
                    </ul>
                </aside>

                <main class="fa-lesson-content">
                    <?php
                    if ($current_lesson_id) {
                        if ($this->is_lesson_locked_for_current_user($current_lesson_id)) {
                            echo '<div class="fa-payment-notice">
                            <i class="fas fa-exclamation-triangle fa-warning-icon"></i>
                            <p>' . __('هذا الدرس مغلق حالياً. الرجاء إكمال الدروس السابقة أو دفع رسوم الوحدة.', 'fashion-academy-lms') . '</p>
                          </div>';
                        } else {
                            $this->render_lesson_details($current_lesson_id);
                        }
                    } else {
                        echo '<p>' . __('مرحباً! اختر أحد الدروس من القائمة على اليسار.', 'fashion-academy-lms') . '</p>';
                    }
                    ?>
                </main>
            </div>
        </div>
        <?php
        echo do_shortcode('[fa_student_chat]');
        return ob_get_clean();
    }




    /**
     * Get the current lesson for the user.
     *
     * @param int $user_id The ID of the user.
     * @return WP_Post|false The current lesson post or false if none found.
     */
    private function get_current_lesson_for_user($user_id)
    {
        global $wpdb;

        // Assuming there's only one course
        $courses = get_posts([
            'post_type'      => 'course',
            'posts_per_page' => 1,
            'post_status'    => 'publish'
        ]);

        if (empty($courses)) {
            return false;
        }

        $course_id = $courses[0]->ID;

        // Get all lessons in order
        $lessons = get_posts([
            'post_type'      => 'lesson',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'meta_value_num',
            'meta_key'       => 'lesson_order',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'     => 'lesson_course_id',
                    'value'   => $course_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC'
                ]
            ]
        ]);

        if (empty($lessons)) {
            return false;
        }

        // Iterate through lessons to find the first incomplete one
        foreach ($lessons as $lesson) {
            $progress = $wpdb->get_var($wpdb->prepare(
                "SELECT progress_status FROM {$wpdb->prefix}course_progress WHERE user_id = %d AND lesson_id = %d",
                $user_id,
                $lesson->ID
            ));

            if ($progress === 'incomplete') {
                return $lesson;
            } elseif ($progress === 'passed') {
                continue;
            } else {
                // If no progress entry, treat as locked
                continue;
            }
        }

        // If all lessons are passed, return the last lesson
        return end($lessons);
    }


    // If user hasn't paid or hasn't passed a prior lesson, return true. (Placeholder)
    /**
     * Check if a lesson is locked for the current user.
     *
     * @param int $lesson_id The ID of the lesson to check.
     * @return bool True if the lesson is locked, false otherwise.
     */
    private function is_lesson_locked_for_current_user($lesson_id)
    {
        if (!is_user_logged_in()) {
            return true; // Non-logged-in users cannot access
        }

        $user_id = get_current_user_id();

        // Get the course ID from the lesson
        $course_id = get_post_meta($lesson_id, 'lesson_course_id', true);
        if (!$course_id) {
            return false; // If no course is associated, allow access
        }

        // Get the module ID from the lesson
        $module_id = get_post_meta($lesson_id, 'lesson_module_id', true);
        if ($module_id) {
            // Identify the first module in the course
            $modules = get_posts([
                'post_type'      => 'module',
                'posts_per_page' => 1,
                'orderby'        => 'meta_value_num',
                'meta_key'       => 'module_order',
                'order'          => 'ASC',
                'meta_query'     => [
                    [
                        'key'     => 'module_course_id',
                        'value'   => $course_id,
                        'compare' => '=',
                        'type'    => 'NUMERIC'
                    ]
                ]
            ]);
            $first_module_id = !empty($modules) ? $modules[0]->ID : 0;

            // If the module is the first module, it's always paid
            if ($module_id == $first_module_id) {
                // Proceed to check lesson progression
            } else {
                // Check if the module is paid
                global $wpdb;
                $payment = $wpdb->get_row($wpdb->prepare(
                    "SELECT payment_status FROM {$wpdb->prefix}course_module_payments WHERE user_id=%d AND module_id=%d",
                    $user_id,
                    $module_id
                ));
                if (!$payment || $payment->payment_status !== 'paid') {
                    return true; // If module not paid, lock the lesson
                }
            }
        }

        // Get the current lesson's order
        $current_order = get_post_meta($lesson_id, 'lesson_order', true);
        if (!$current_order) {
            return false; // If no order is set, allow access
        }

        global $wpdb;
        $progress_table = $wpdb->prefix . 'course_progress';

        // Fetch all lessons in the course with order less than current_order
        $required_lessons = get_posts(array(
            'post_type'      => 'lesson',
            'posts_per_page' => -1,
            'meta_key'       => 'lesson_order',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'     => 'lesson_course_id',
                    'value'   => $course_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC'
                ],
                [
                    'key'     => 'lesson_order',
                    'value'   => intval($current_order) - 1,
                    'compare' => '<=',
                    'type'    => 'NUMERIC'
                ]
            ]
        ));

        // Check if all required lessons are marked as 'passed' in course_progress
        foreach ($required_lessons as $lesson) {
            $lesson_order = get_post_meta($lesson->ID, 'lesson_order', true);
            if ($lesson_order >= $current_order) {
                continue; // Skip lessons at or beyond the current lesson
            }

            // Check if the user has passed this lesson
            $passed = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT progress_status FROM {$wpdb->prefix}course_progress WHERE user_id = %d AND lesson_id = %d",
                    $user_id,
                    $lesson->ID
                )
            );

            if ($passed !== 'passed') {
                return true; // If any previous lesson is not passed, lock the current lesson
            }
        }

        return false; // All previous lessons are passed, unlock the current lesson
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
            echo '<div class="fa-lesson-video">';
            echo '<video width="600" controls>';
            echo '<source src="' . esc_url($video_url) . '" type="video/mp4">';
            _e('متصفحك لا يدعم فيديو.', 'fashion-academy-lms');
            echo '</video>';
            echo '</div>';
        }

        // Fetch the submission
        $submission = $this->get_current_submission_for_user(get_current_user_id(), $lesson_id);

        echo '<div class="fa-homework-header">';
        if (!$submission || $submission->status === 'retake') {
            echo '<h2 class="fa-homework-title">' . __('واجبتك الإبداعية:', 'fashion-academy-lms') . '</h2>';
            echo '<p class="fa-homework-desc">'
                . __('أنتهيت من الدرس! الآن، أظهر لنا لمساتك الفنية من خلال الواجب المنزلي التالي. قم بمراجعة الدرس وابدأ بالإبداع.', 'fashion-academy-lms')
                . '</p>';
            echo '</div>';
        } else {
            echo '<h2>' . __('نتيجة تقييم الواجب:', 'fashion-academy-lms') . '</h2>';
        }

        // 1) If no submission or submission is 'retake', show the form
        if (!$submission || $submission->status === 'retake') {
            // Show the form container with an ID for JS
            echo '<div id="fa-homework-container">';
            echo do_shortcode('[fa_homework_form lesson_id="' . $lesson_id . '"]');
            echo '</div>';
            // Spinner is handled via external JS on form submission
        }
        // 2) If submission is "pending," remove the form, show spinner/logo
        elseif ($submission->status === 'pending') {
            // Get the academy logo URL from WP media
            $logo_id = get_option('fa_academy_logo_id'); // Ensure this option is set with the logo's attachment ID
            if ($logo_id) {
                $logo_url = wp_get_attachment_image_url($logo_id, 'thumbnail'); // Adjust size as needed
            } else {
                // Fallback if logo not set
                $logo_url = 'http://fashion-academy.local/wp-content/uploads/2024/12/minilogof.png'; // Placeholder image
            }

            echo '<div class="fa-spinner-section">';
            echo '<img src="' . esc_url($logo_url) . '" alt="' . __('Logo', 'fashion-academy-lms') . '" class="fa-academy-logo-spinner">';
            echo '<p class="fa-waiting-msg">'
                . __('تم إرسال الواجب! استرخي قليلاً بينما نقوم بتقييم إبداعك...', 'fashion-academy-lms') . '</p>';
            echo '</div>';

            // Localize dynamic data for JavaScript
            wp_localize_script('fa-frontend-script', 'faLMS', array_merge(
                $this->get_translated_script_data(),
                array(
                    'currentStatus' => 'pending',
                    'submissionId'  => (int) $submission->id,
                )
            ));
        }
        // 3) If submission is "graded" or "passed," show results
        else {
            echo '<div class="fa-homework-results">';

            if ($submission->status === 'graded') {
                echo '<p>' . sprintf(__('لقد تم تقييم واجبك! درجتك: <strong>%s%%</strong>', 'fashion-academy-lms'), $submission->grade) . '</p>';
                if ($submission->grade >= 90) {
                    echo '<p class="fa-outstanding-msg">' . __('🌟 ممتاز! استمر في التألق.', 'fashion-academy-lms') . '</p>';
                }
                if ($submission->grade < 75) {
                    echo '<p class="fa-fail-msg">' . __('⚠️ لم يتم تجاوز الواجب. جرب مرة أخرى لتحقيق النجاح.', 'fashion-academy-lms') . '</p>';
                }
            } elseif ($submission->status === 'passed') {
                echo '<p>' . sprintf(__('🎉 أحسنت! لقد تجاوزت هذا الواجب بنجاح. درجتك: <strong>%s%%</strong>', 'fashion-academy-lms'), $submission->grade) . '</p>';
                if ($submission->grade >= 90) {
                    echo '<p class="fa-outstanding-msg">' . __('🌟 رائع! أنت مبدع حقًا.', 'fashion-academy-lms') . '</p>';
                }
            }

            // Display instructor feedback files if any
            $instructor_files = json_decode($submission->instructor_files, true);
            if (!empty($instructor_files)) {
                echo '<h4>' . __('ملاحظات الأستاذ والتعليقات:', 'fashion-academy-lms') . '</h4>';
                echo '<div class="fa-gallery">';
                foreach ($instructor_files as $ifile) {
                    echo '<a href="' . esc_url($ifile) . '" class="fa-gallery-item" target="_blank">';
                    echo '<img src="' . esc_url($ifile) . '" alt="' . __('ملاحظات الأستاذ', 'fashion-academy-lms') . '">';
                    echo '</a>';
                }
                echo '</div>';
            }

            // Display student submitted files as gallery if any
            $student_files = json_decode($submission->uploaded_files, true);
            if (!empty($student_files)) {
                echo '<h4>' . __('محتويات إبداعك:', 'fashion-academy-lms') . '</h4>';
                echo '<div class="fa-gallery">';
                foreach ($student_files as $sfile) {
                    echo '<a href="' . esc_url($sfile) . '" class="fa-gallery-item" target="_blank">';
                    echo '<img src="' . esc_url($sfile) . '" alt="' . __('إبداعك المرسل', 'fashion-academy-lms') . '">';
                    echo '</a>';
                }
                echo '</div>';
            }

            // Display Admin Notes if any
            $admin_notes = !empty($submission->admin_notes) ? $submission->admin_notes : '';
            if (!empty($admin_notes)) {
                echo '<h4>' . __('ملاحظات إضافية:', 'fashion-academy-lms') . '</h4>';
                echo '<p>' . esc_html($admin_notes) . '</p>';
            }

            // Conditional Buttons based on grade and status
            if ($submission->status === 'graded') {
                if ($submission->grade < 75) {
                    // Show Retake Homework Button
                    echo '<button class="fa-retake-button" onclick="retakeHomework(' . intval($submission->id) . ')">'
                        . __('✨ أعد المحاولة مع لمسة جديدة!', 'fashion-academy-lms') . '</button>';
                } else {
                    // Show Navigate to Next Lesson Button
                    $next_lesson_id = $this->get_next_lesson_id($lesson_id);
                    if ($next_lesson_id) {
                        $next_lesson = get_post($next_lesson_id);
                        if ($next_lesson) {
                            // Get the student dashboard page by slug
                            $student_dashboard_page = get_page_by_path('student-dashboard'); // Replace 'student-dashboard' with your actual slug
                            if ($student_dashboard_page) {
                                $student_dashboard_url = get_permalink($student_dashboard_page->ID);
                                $next_lesson_url = add_query_arg('lesson_id', $next_lesson_id, $student_dashboard_url);
                                echo '<a href="' . esc_url($next_lesson_url) . '" class="button-primary fa-next-lesson-button">'
                                    . __('🌈 استكشف الدرس التالي: ' . esc_html($next_lesson->post_title), 'fashion-academy-lms') . '</a>';
                            } else {
                                echo '<p>' . __('صفحة لوحة التحكم للطلاب غير موجودة.', 'fashion-academy-lms') . '</p>';
                            }
                        }
                    }
                }
            } elseif ($submission->status === 'passed') {
                // Show Navigate to Next Lesson Button
                $next_lesson_id = $this->get_next_lesson_id($lesson_id);
                if ($next_lesson_id) {
                    $next_lesson = get_post($next_lesson_id);
                    if ($next_lesson) {
                        // Get the student dashboard page by slug
                        $student_dashboard_page = get_page_by_path('student-dashboard'); // Replace 'student-dashboard' with your actual slug
                        if ($student_dashboard_page) {
                            $student_dashboard_url = get_permalink($student_dashboard_page->ID);
                            $next_lesson_url = add_query_arg('lesson_id', $next_lesson_id, $student_dashboard_url);
                            echo '<a href="' . esc_url($next_lesson_url) . '" class="button-primary fa-next-lesson-button">'
                                . __('🌟 استعد للدرس التالي: ' . esc_html($next_lesson->post_title), 'fashion-academy-lms') . '</a>';
                        } else {
                            echo '<p>' . __('صفحة لوحة التحكم للطلاب غير موجودة.', 'fashion-academy-lms') . '</p>';
                        }
                    }
                }
            }

            echo '</div>';

            // Localize retake confirmation message and next lesson info
            wp_localize_script('fa-frontend-script', 'faLMS', array_merge(
                $this->get_translated_script_data(),
                array(
                    'retakeConfirm' => __('هل أنت متأكد من إعادة الواجب؟', 'fashion-academy-lms'),
                    'nextLessonUrl' => isset($next_lesson_id) ? add_query_arg('lesson_id', $next_lesson_id, get_permalink(get_page_by_path('student-dashboard')->ID)) : '',
                    'nextLessonTitle' => isset($next_lesson) ? esc_html($next_lesson->post_title) : '',
                )
            ));

            $user_id = get_current_user_id();

            // If submission is 'passed', unlock the next lesson
            if ($submission->status === 'passed') {
                $this->mark_lesson_as_passed($user_id, $lesson_id);
                $this->unlock_next_lesson($user_id, $lesson_id);
            }
        }
    }



    /**
     * Retrieves the ID of the next lesson in the course based on the current lesson.
     *
     * @param int $current_lesson_id The ID of the current lesson.
     * @return int|null The ID of the next lesson or null if there is no subsequent lesson.
     */
    function get_next_lesson_ID($current_lesson_id) {
        // Ensure the current lesson exists and is of post type 'lesson'
        $current_lesson = get_post($current_lesson_id);
        if (!$current_lesson || $current_lesson->post_type !== 'lesson') {
            return null;
        }

        // Retrieve the current lesson's order and associated module ID
        $current_order = get_post_meta($current_lesson_id, 'lesson_order', true);
        $current_module_id = get_post_meta($current_lesson_id, 'lesson_module_id', true);

        // Validate that the current lesson is assigned to a module
        if (!$current_module_id) {
            // If the lesson isn't assigned to any module, consider global ordering
            $args = array(
                'post_type'      => 'lesson',
                'posts_per_page' => 1,
                'meta_key'       => 'lesson_order',
                'orderby'        => 'meta_value_num',
                'order'          => 'ASC',
                'meta_query'     => array(
                    array(
                        'key'     => 'lesson_order',
                        'value'   => $current_order,
                        'compare' => '>',
                        'type'    => 'NUMERIC',
                    )
                )
            );

            $next_lessons = get_posts($args);
            if (!empty($next_lessons)) {
                return $next_lessons[0]->ID;
            } else {
                return null; // No next lesson available
            }
        }

        // Step 1: Attempt to find the next lesson within the same module
        $args = array(
            'post_type'      => 'lesson',
            'posts_per_page' => 1,
            'meta_key'       => 'lesson_order',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => 'lesson_module_id',
                    'value'   => $current_module_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
                array(
                    'key'     => 'lesson_order',
                    'value'   => $current_order,
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                )
            )
        );

        $next_lessons = get_posts($args);
        if (!empty($next_lessons)) {
            return $next_lessons[0]->ID;
        }

        // Step 2: If no next lesson in the current module, find the next module
        // Retrieve the current module's order
        $current_module_order = get_post_meta($current_module_id, 'module_order', true);

        // Query for the next module based on 'module_order'
        $args = array(
            'post_type'      => 'module',
            'posts_per_page' => 1,
            'meta_key'       => 'module_order',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'meta_query'     => array(
                array(
                    'key'     => 'module_order',
                    'value'   => $current_module_order,
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                )
            )
        );

        $next_modules = get_posts($args);
        if (!empty($next_modules)) {
            $next_module = $next_modules[0];
            $next_module_id = $next_module->ID;

            // Retrieve the first lesson of the next module
            $args = array(
                'post_type'      => 'lesson',
                'posts_per_page' => 1,
                'meta_key'       => 'lesson_order',
                'orderby'        => 'meta_value_num',
                'order'          => 'ASC',
                'meta_query'     => array(
                    array(
                        'key'     => 'lesson_module_id',
                        'value'   => $next_module_id,
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    )
                )
            );

            $next_lessons = get_posts($args);
            if (!empty($next_lessons)) {
                return $next_lessons[0]->ID;
            }
        }

        // Step 3: No subsequent lesson found
        return null;
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


    public function render_student_chat()
    {
        if (!is_user_logged_in() ) {
            return ''; // Only students can see the chat
        }

        // Get the admin/institutor's image URL
        $admin_image_url = 'http://fashion-academy.local/wp-content/uploads/2024/10/Sans_titre_214_202410251931321.png';

        ob_start(); ?>
        <div id="fa-chat-popup" class="fa-chat-popup">
            <div class="fa-chat-header">
                <span class="fa-chat-title"><?php _e('دردشة الدعم', 'fashion-academy-lms'); ?></span>
                <button id="fa-chat-close" class="fa-chat-close">&times;</button>
            </div>
            <div id="fa-chat-messages" class="fa-chat-messages">
                <!-- Example of a chat message from admin -->
                <div class="fa-chat-message fa-chat-message-received">
                    <img src="<?php echo esc_url($admin_image_url); ?>" alt="<?php _e('Admin', 'fashion-academy-lms'); ?>" class="fa-chat-avatar">
                    <div class="fa-chat-content">
                        <?php _e('مرحبا! كيف يمكنني مساعدتك اليوم؟', 'fashion-academy-lms'); ?>
                    </div>
                </div>
                <!-- Student messages will have a different class -->
            </div>
            <form id="fa-chat-form" class="fa-chat-form">
                <input type="text" id="fa-chat-input" class="fa-chat-input" placeholder="<?php _e('اكتب رسالتك هنا...', 'fashion-academy-lms'); ?>" required />
                <button type="submit" class="fa-chat-send"><i class="fas fa-paper-plane"></i></button>
            </form>
        </div>
        <button id="fa-chat-toggle" class="fa-chat-toggle"><i class="fas fa-comments"></i></button>
        <?php
        return ob_get_clean();
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

            <ul class="fa-admin-nav">
                <li><a href="?admin_page=homeworks" class="<?php echo is_active_tab('homeworks') ? 'active-tab' : ''; ?>">
                        <?php _e('إدارة الواجبات', 'fashion-academy-lms'); ?></a></li>
                <li><a href="?admin_page=lessons" class="<?php echo is_active_tab('lessons') ? 'active-tab' : ''; ?>">
                        <?php _e('إدارة الدروس', 'fashion-academy-lms'); ?></a></li>
                <li><a href="?admin_page=modules" class="<?php echo is_active_tab('modules') ? 'active-tab' : ''; ?>">
                        <?php _e('إدارة المواد', 'fashion-academy-lms'); ?></a></li>
                <li><a href="?admin_page=students" class="<?php echo is_active_tab('students') ? 'active-tab' : ''; ?>">
                        <?php _e('البحث عن الطلاب', 'fashion-academy-lms'); ?></a></li>
                <li><a href="?admin_page=chats" class="<?php echo is_active_tab('chats') ? 'active-tab' : ''; ?>">
                        <?php _e('إدارة الدردشة', 'fashion-academy-lms'); ?></a></li>
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
                    case 'chats':
                        $this->render_admin_chats_page();
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
            <button type="submit" class="button button-primary"><?php _e('تصفية', 'fashion-academy-lms'); ?></button>
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
                <!-- Removed ID Column -->
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
                    <!-- Removed ID Data Cell -->
                    <td><?php echo esc_html($user_name); ?></td>
                    <td><?php echo esc_html($lessonName); ?></td>
                    <td><?php echo esc_html($submission->status); ?></td>
                    <td><?php echo esc_html($submission->grade); ?></td>
                    <td><?php echo esc_html($submission->submission_date); ?></td>
                    <td>
                        <a href="?admin_page=homeworks&view_submission=<?php echo esc_attr($submission->id); ?>"
                           class="button button-inline button-primary">
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

        // If form submitted to grade + attach instructor files + add notes
        if (isset($_POST['fa_grade_submission']) && $_POST['fa_grade_submission'] === 'true') {
            // Verify nonce for security
            if (!isset($_POST['fa_grade_submission_nonce_field']) ||
                !wp_verify_nonce($_POST['fa_grade_submission_nonce_field'], 'fa_grade_submission_nonce')) {
                wp_die(__('فشل التحقق الأمني', 'fashion-academy-lms'));
            }

            $new_grade = floatval($_POST['grade']);
            $passing_grade = 75;
            $new_status = ($new_grade >= $passing_grade) ? 'passed' : 'graded';

            // 1) Handle instructor files removal
            $files_to_remove = isset($_POST['remove_instructor_files']) ? $_POST['remove_instructor_files'] : array();
            $current_instructor_files = !empty($submission->instructor_files) ? json_decode($submission->instructor_files, true) : [];
            if (!is_array($current_instructor_files)) {
                $current_instructor_files = array();
            }

            // Remove selected files
            if (!empty($files_to_remove)) {
                $current_instructor_files = array_diff($current_instructor_files, $files_to_remove);
            }

            // 2) Handle new instructor files uploads
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

            // Merge with existing instructor_files
            $all_ifiles = array_merge($current_instructor_files, $instructor_files);
            $json_ifiles = wp_json_encode($all_ifiles);

            // 3) Handle admin notes
            $admin_notes = isset($_POST['admin_notes']) ? sanitize_textarea_field($_POST['admin_notes']) : '';

            // 4) Update submission
            $res = $wpdb->update(
                $submission_table,
                [
                    'grade'            => $new_grade,
                    'status'           => $new_status,
                    'instructor_files' => $json_ifiles,
                    'admin_notes'      => $admin_notes
                ],
                ['id'=>$submission_id],
                ['%f','%s','%s','%s'],
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
                echo '<div class="notice notice-success"><p>' . __('تم حفظ التقييم وملفات المعلم والملاحظات!', 'fashion-academy-lms') . '</p></div>';

                // Refresh submission object
                $submission = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $submission_table WHERE id=%d", $submission_id
                ));
            }
        }

        // Now display the submission details, including any instructor_files and admin_notes
        $uploaded_files = json_decode($submission->uploaded_files, true);
        $notes = $submission->notes;
        $instructor_files = !empty($submission->instructor_files) ? json_decode($submission->instructor_files, true) : [];
        $admin_notes = $submission->admin_notes;

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

        // Show existing instructor_files if any, with remove options
        if (!empty($instructor_files)) {
            echo '<h5>' . __('ملفات المدرس المرفقة سابقاً:', 'fashion-academy-lms') . '</h5>';
            echo '<ul>';
            foreach ($instructor_files as $ifile_url) {
                echo '<li>';
                echo '<a href="' . esc_url($ifile_url) . '" target="_blank">' . esc_html(basename($ifile_url)) . '</a>';
                echo ' <label><input type="checkbox" name="remove_instructor_files[]" value="' . esc_attr($ifile_url) . '"> ' . __('إزالة', 'fashion-academy-lms') . '</label>';
                echo '</li>';
            }
            echo '</ul>';
        } else {
            echo '<p>' . __('لا توجد ملفات مدرسية مرفقة سابقاً.', 'fashion-academy-lms') . '</p>';
        }

        // Grading form with new input for instructor_files and admin_notes
        ?>
        <h4><?php _e('إضافة / تعديل ملفات المدرس والملاحظات', 'fashion-academy-lms'); ?></h4>
        <form method="post" enctype="multipart/form-data" id="fa-admin-homework-form">
            <?php wp_nonce_field('fa_grade_submission_nonce', 'fa_grade_submission_nonce_field'); ?>
            <input type="hidden" name="fa_grade_submission" value="true"/>

            <p>
                <label for="grade"><?php _e('التقييم (%):', 'fashion-academy-lms'); ?></label>
                <input type="number" name="grade" id="grade" step="1" min="0" max="100"
                       value="<?php echo esc_attr($submission->grade); ?>" required>
            </p>

            <p>
                <label for="instructor_files"><?php _e('ارفع ملفات المدرس (صور، PDF، إلخ):', 'fashion-academy-lms'); ?></label><br/>
                <input type="file" name="instructor_files[]" id="instructor_files" multiple accept=".jpg,.jpeg,.png,.pdf"/>
            </p>

            <div id="admin_file_preview"></div>

            <p>
                <label for="admin_notes"><?php _e('ملاحظات المدرس (اختياري):', 'fashion-academy-lms'); ?></label><br/>
                <textarea name="admin_notes" id="admin_notes" rows="4" cols="50" placeholder="<?php _e('أضف ملاحظاتك هنا...', 'fashion-academy-lms'); ?>"><?php echo esc_textarea($admin_notes); ?></textarea>
            </p>

            <button type="submit" class="button button-primary"><?php _e('حفظ التقييم', 'fashion-academy-lms'); ?></button>
        </form>
        <p><a href="?admin_page=homeworks" class="button"><?php _e('عودة إلى الواجبات', 'fashion-academy-lms'); ?></a></p>
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

    /**
     * Unlock the next lesson for the user.
     *
     * @param int $user_id The ID of the user.
     * @param int $current_lesson_id The ID of the current lesson.
     */
    private function unlock_next_lesson($user_id, $current_lesson_id)
    {
        global $wpdb;
        $progress_table = $wpdb->prefix . 'course_progress';

        // Fetch course ID and current lesson order
        $course_id     = get_post_meta($current_lesson_id, 'lesson_course_id', true);
        $current_order = get_post_meta($current_lesson_id, 'lesson_order', true);

        if (!$course_id || !$current_order) return;

        // Identify the first module
        $first_module = get_posts([
            'post_type'      => 'module',
            'posts_per_page' => 1,
            'orderby'        => 'meta_value_num',
            'meta_key'       => 'module_order',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'     => 'module_course_id',
                    'value'   => $course_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC'
                ]
            ]
        ]);
        $first_module_id = !empty($first_module) ? $first_module[0]->ID : 0;

        // Get the next lesson based on lesson_order
        $next_lesson = get_posts([
            'post_type'      => 'lesson',
            'posts_per_page' => 1,
            'meta_key'       => 'lesson_order',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'     => 'lesson_course_id',
                    'value'   => $course_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC'
                ],
                [
                    'key'     => 'lesson_order',
                    'value'   => intval($current_order) + 1,
                    'compare' => '=',
                    'type'    => 'NUMERIC'
                ]
            ]
        ]);

        if (empty($next_lesson)) return; // No next lesson found

        $next_lesson_id = $next_lesson[0]->ID;

        // Get the module ID of the next lesson
        $next_module_id = get_post_meta($next_lesson_id, 'lesson_module_id', true);

        // If the next lesson is in the first module, do not need to check payment
        if ($next_module_id == $first_module_id) {
            // Proceed to unlock without checking payment
        } else {
            // Check if the module is paid
            $payment = $wpdb->get_row($wpdb->prepare(
                "SELECT payment_status FROM {$wpdb->prefix}course_module_payments WHERE user_id=%d AND module_id=%d",
                $user_id,
                $next_module_id
            ));
            if (!$payment || $payment->payment_status !== 'paid') {
                return; // Do not unlock if the module is not paid
            }
        }

        // Check if the user already has progress for the next lesson
        $existing_progress = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $progress_table WHERE user_id=%d AND lesson_id=%d",
            $user_id,
            $next_lesson_id
        ));

        if ($existing_progress) {
            // Update progress_status to 'incomplete' if not already set
            if ($existing_progress->progress_status !== 'incomplete') {
                $wpdb->update(
                    $progress_table,
                    ['progress_status' => 'incomplete'],
                    ['id' => $existing_progress->id],
                    ['%s'],
                    ['%d']
                );
            }
        } else {
            // Insert a new progress entry as 'incomplete'
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
                <!-- Removed ID Column -->
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
                // Removed ID Data Cell
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
                <!-- Removed ID Column -->
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
                // Removed ID Data Cell
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

        // Determine if a search query exists
        $search = sanitize_text_field($_GET['student_search'] ?? '');
        $args = [
            'role' => 'student',
        ];

        // If search is performed, customize the query
        if (!empty($search)) {
            $args['search'] = "*{$search}*";
            $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        // Pagination setup
        $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $args['number'] = 10; // Number of students per page
        $args['paged'] = $paged;

        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();

        if (!$users) {
            echo '<p>' . __('لا يوجد طلاب مطابقين.', 'fashion-academy-lms') . '</p>';
        } else {
            ?>
            <table class="widefat" style="margin-top:15px;">
                <thead>
                <tr>
                    <th><?php _e('اسم المستخدم', 'fashion-academy-lms'); ?></th>
                    <th><?php _e('البريد الإلكتروني', 'fashion-academy-lms'); ?></th>
                    <th><?php _e('رقم الواتساب', 'fashion-academy-lms'); ?></th>
                    <th><?php _e('إدارة', 'fashion-academy-lms'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php
                foreach ($users as $user) {
                    // Retrieve the WhatsApp number from user meta
                    $whatsapp_number = get_user_meta($user->ID, 'whatsapp_number', true);

                    echo '<tr>';
                    echo '<td>' . esc_html($user->display_name) . '</td>';
                    echo '<td>' . esc_html($user->user_email) . '</td>';
                    echo '<td>' . esc_html($whatsapp_number ?: __('غير متوفر', 'fashion-academy-lms')) . '</td>';
                    echo '<td>
                <a href="?admin_page=students&view_student='. esc_attr($user->ID) .'" class="button">'
                        . __('عرض الملف', 'fashion-academy-lms') . '</a></td>';
                    echo '</tr>';
                }
                ?>
                </tbody>
            </table>

            <?php
            // Add pagination for results
            $total_pages = ceil($user_query->get_total() / $args['number']);
            if ($total_pages > 1) {
                echo '<div class="tablenav"><div class="tablenav-pages">';
                echo paginate_links([
                    'base'    => add_query_arg('paged', '%#%'),
                    'format'  => '',
                    'current' => $paged,
                    'total'   => $total_pages,
                ]);
                echo '</div></div>';
            }
        }
    }

    private function render_admin_student_profile($user_id)
    {
        global $wpdb;

        $user_info = get_userdata($user_id);
        if (!$user_info) {
            echo '<p style="color:red;">' . __('المستخدم غير موجود', 'fashion-academy-lms') . '</p>';
            return;
        }

        // Retrieve the WhatsApp number
        $whatsapp_number = get_user_meta($user_id, 'whatsapp_number', true);

        // Handle profile updates
        if (isset($_POST['fa_update_student_profile']) && $_POST['fa_update_student_profile'] === 'yes') {
            check_admin_referer('fa_update_student_nonce', 'fa_update_student_nonce_field');

            $display_name   = sanitize_text_field($_POST['display_name']);
            $email          = sanitize_email($_POST['email']);
            $new_whatsapp   = sanitize_text_field($_POST['whatsapp']);

            // Validate and update user data
            wp_update_user([
                'ID'           => $user_id,
                'display_name' => $display_name,
                'user_email'   => $email,
            ]);

            // Update WhatsApp number
            update_user_meta($user_id, 'whatsapp_number', $new_whatsapp);

            echo '<div class="notice notice-success"><p>' . __('تم تحديث بيانات الطالب بنجاح!', 'fashion-academy-lms') . '</p>';
        }

        ?>
        <hr>
        <h4><?php _e('تفاصيل الطالب', 'fashion-academy-lms'); ?></h4>
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
            <p>
                <label for="whatsapp"><?php _e('رقم الواتساب', 'fashion-academy-lms'); ?>:</label><br/>
                <input type="text" name="whatsapp" id="whatsapp" style="width:300px;"
                       value="<?php echo esc_attr($whatsapp_number); ?>"/>
            </p>

            <button type="submit" class="button button-primary"><?php _e('حفظ', 'fashion-academy-lms'); ?></button>
        </form>
        <?php
    }

    private function render_admin_chats_page()
    {
        global $wpdb;
        $chat_table = $wpdb->prefix . 'chat_messages';

        // Handle sending a new message
        if (isset($_POST['fa_admin_send_message']) && $_POST['fa_admin_send_message'] === 'send_message') {
            check_admin_referer('fa_admin_chat_nonce', 'fa_admin_chat_nonce_field');

            $recipient_id = intval($_POST['recipient_id'] ?? 0);
            $message = sanitize_textarea_field($_POST['admin_chat_message'] ?? '');

            if ($recipient_id > 0 && !empty($message)) {
                $admin_id = get_option('fa_admin_user_id');
                if (!$admin_id) {
                    echo '<p style="color:red;">' . __('لم يتم تعيين معرف المشرف.', 'fashion-academy-lms') . '</p>';
                } else {
                    $wpdb->insert(
                        $chat_table,
                        array(
                            'user_id'      => $recipient_id,
                            'sender_id'    => $admin_id,
                            'message'      => $message,
                            'timestamp'    => current_time('mysql'),
                            'read_status'  => 0,
                            'attachment_url' => ''
                        ),
                        array(
                            '%d',
                            '%d',
                            '%s',
                            '%s',
                            '%d',
                            '%s'
                        )
                    );
                    echo '<div class="notice notice-success"><p>' . __('تم إرسال الرسالة بنجاح!', 'fashion-academy-lms') . '</p></div>';
                }
            } else {
                echo '<p style="color:red;">' . __('يرجى تحديد مستلم وكتابة رسالة.', 'fashion-academy-lms') . '</p>';
            }
        }

        // Fetch all students
        $students = get_users(array(
            'role' => 'student',
            'orderby' => 'display_name',
            'order' => 'ASC'
        ));

        if (!$students) {
            echo '<p>' . __('لا يوجد طلاب لإدارة الدردشة معهم.', 'fashion-academy-lms') . '</p>';
            return;
        }

        // Fetch latest messages per student
        $conversations = array();
        foreach ($students as $student) {
            $latest_message = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $chat_table WHERE user_id = %d ORDER BY timestamp DESC LIMIT 1",
                $student->ID
            ));
            if ($latest_message) {
                $conversations[] = $latest_message;
            }
        }

        ?>
        <h3><?php _e('إدارة الدردشة مع الطلاب', 'fashion-academy-lms'); ?></h3>

        <table class="widefat">
            <thead>
            <tr>
                <th><?php _e('الطالب', 'fashion-academy-lms'); ?></th>
                <th><?php _e('آخر رسالة', 'fashion-academy-lms'); ?></th>
                <th><?php _e('التاريخ', 'fashion-academy-lms'); ?></th>
                <th><?php _e('الحالة', 'fashion-academy-lms'); ?></th>
                <th><?php _e('إجراءات', 'fashion-academy-lms'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($conversations as $message): ?>
                <?php
                $student = get_userdata($message->user_id);
                if (!$student) continue;

                // Determine if the latest message is unread by admin
                $is_unread = ($message->sender_id != get_option('fa_admin_user_id')) && !$message->read_status;
                ?>
                <tr class="<?php echo $is_unread ? 'fa-unread-message' : ''; ?>">
                    <td><?php echo esc_html($student->display_name); ?></td>
                    <td><?php echo esc_html(wp_trim_words($message->message, 10, '...')); ?></td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($message->timestamp))); ?></td>
                    <td><?php echo $is_unread ? __('غير مقروء', 'fashion-academy-lms') : __('مقروء', 'fashion-academy-lms'); ?></td>
                    <td>
                        <a href="?admin_page=chats&chat_with=<?php echo esc_attr($student->ID); ?>" class="button button-primary">
                            <i class="fas fa-comments"></i> <?php _e('دردشة', 'fashion-academy-lms'); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php

        // If viewing a specific chat
        if (isset($_GET['chat_with'])) {
            $this->render_admin_chat_interface(intval($_GET['chat_with']));
        }
    }

    private function render_admin_chat_interface($student_id)
    {
        global $wpdb;
        $chat_table = $wpdb->prefix . 'chat_messages';
        $admin_id = get_option('fa_admin_user_id');
        $student = get_userdata($student_id);

        if (!$student) {
            echo '<p style="color:red;">' . __('الطالب غير موجود.', 'fashion-academy-lms') . '</p>';
            return;
        }

        // Mark all unread messages from student as read
        $wpdb->update(
            $chat_table,
            array('read_status' => 1),
            array(
                'user_id'   => $student_id,
                'sender_id' => $student_id,
                'read_status' => 0
            ),
            array('%d'),
            array('%d', '%d', '%d')
        );

        // Fetch all messages between admin and student
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $chat_table 
             WHERE user_id = %d AND (sender_id = %d OR sender_id = %d) 
             ORDER BY timestamp ASC",
            $student_id,
            $admin_id,
            $student_id
        ));

        ?>
        <h4><?php echo sprintf(__('دردشة مع %s', 'fashion-academy-lms'), esc_html($student->display_name)); ?></h4>
        <div id="fa-admin-chat-box" class="fa-admin-chat-box">
            <div id="fa-admin-chat-messages" class="fa-admin-chat-messages">
                <?php foreach ($messages as $msg): ?>
                    <?php if ($msg->sender_id == $admin_id): ?>
                        <div class="fa-admin-message fa-admin-sent">
                            <span><?php echo esc_html($msg->message); ?></span>
                            <span class="fa-chat-timestamp"><?php echo esc_html(date_i18n(get_option('time_format'), strtotime($msg->timestamp))); ?></span>
                        </div>
                    <?php else: ?>
                        <div class="fa-admin-message fa-admin-received">
                            <span><?php echo esc_html($msg->message); ?></span>
                            <span class="fa-chat-timestamp"><?php echo esc_html(date_i18n(get_option('time_format'), strtotime($msg->timestamp))); ?></span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <form id="fa-admin-chat-form" class="fa-admin-chat-form" data-recipient="<?php echo esc_attr($student_id); ?>">
                <?php wp_nonce_field('fa_admin_send_chat_nonce', 'fa_admin_send_chat_nonce_field'); ?>
                <input type="text" id="fa-admin-chat-input" class="fa-admin-chat-input" placeholder="<?php _e('اكتب رسالتك هنا...', 'fashion-academy-lms'); ?>" required />
                <button type="submit" class="fa-admin-chat-send"><i class="fas fa-paper-plane"></i></button>
            </form>
        </div>
        <?php
    }


    public function render_homework_form($atts = [])
    {
        // Ensure user is logged in
        if (!is_user_logged_in()) {
            return '<p>' . __('يجب أن تكون مسجلاً للدخول لتقديم الواجب.', 'fashion-academy-lms') . '</p>';
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
                <label for="homework_files"><?php _e('ارفع إبداعك الفني (صور، ملفات PDF، وغيرها):', 'fashion-academy-lms'); ?></label><br>
                <input type="file" name="homework_files[]" id="homework_files" multiple="multiple"
                       accept=".jpg,.jpeg,.png,.pdf"/>
            </p>

            <div id="file_preview">
                <?php if (!empty($uploaded_files) && is_array($uploaded_files)) : ?>
                    <?php foreach ($uploaded_files as $index => $file_url) : ?>
                        <div class="fa-file-preview">
                            <span><?php echo esc_html(basename($file_url)); ?></span>
                            <button type="button" class="fa-remove-file" data-index="<?php echo esc_attr($index); ?>">
                                <?php _e('❌ إزالة', 'fashion-academy-lms'); ?>
                            </button>
                            <input type="hidden" name="existing_files[]" value="<?php echo esc_attr($file_url); ?>"/>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <p>
                <label for="lesson_difficulties"><?php _e('هل واجهت أي صعوبات أثناء الدرس؟', 'fashion-academy-lms'); ?></label><br>
                <textarea name="lesson_difficulties" id="lesson_difficulties" rows="4"
                          placeholder="<?php _e('شاركنا ملاحظاتك لتحسين تجربتك.', 'fashion-academy-lms'); ?>"></textarea>
            </p>


                <button type="submit" class="fa-submit-button">
                    <?php _e('✨ قدم واجبك بكل أناقة!', 'fashion-academy-lms'); ?>
                </button>

        </form>
        <?php

        // Localize dynamic data for JavaScript
        wp_localize_script('fa-frontend-script', 'faLMS', array_merge(
            $this->get_translated_script_data(),
            array(
                'removeButtonText' => __('❌ إزالة', 'fashion-academy-lms'), // Localized text for 'Remove' button with emoji
            )
        ));

        if (isset($_GET['homework_submitted']) && $_GET['homework_submitted'] === 'true') {
            echo '<p class="fa-success-message">' . __('🎉 تم تقديم واجبك بنجاح! استمر في التألق.', 'fashion-academy-lms') . '</p>';
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
    // Inside the FA_Frontend class

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
            'post_type'      => 'lesson',
            'posts_per_page' => -1,
            'meta_key'       => 'lesson_order',
            'orderby'        => 'meta_value_num',
            'order'          => 'ASC',
            'meta_query'     => [
                [
                    'key'     => 'lesson_course_id',
                    'value'   => $course_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC'
                ],
                [
                    'key'     => 'lesson_order',
                    'value'   => intval($current_order) - 1,
                    'compare' => '<=',
                    'type'    => 'NUMERIC'
                ]
            ]
        ));

        // Check if all required lessons are marked as 'passed' in course_progress
        foreach ($required_lessons as $lesson) {
            $lesson_order = get_post_meta($lesson->ID, 'lesson_order', true);
            if ($lesson_order >= $current_order) {
                continue; // Skip lessons at or beyond the current lesson
            }

            // Check if the user has passed this lesson
            $passed = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT progress_status FROM {$wpdb->prefix}course_progress WHERE user_id = %d AND lesson_id = %d",
                    $user_id,
                    $lesson->ID
                )
            );

            if ($passed !== 'passed') {
                // If any required lesson is not passed, restrict access
                // Set a transient to display a notice after redirection
                set_transient('fa_restricted_lesson_notice_' . $user_id, true, 30);
                wp_redirect(site_url('/student-dashboard')); // Redirect to student dashboard
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
                <p>' . __('لا يمكنك الوصول إلى هذا الدرس بعد. يرجى إكمال الدروس السابقة.', 'fashion-academy-lms') . '</p>
              </div>';
            delete_transient('fa_restricted_lesson_notice_' . $user_id);
        }
    }

    /* ------------------------------------------------------------------------ */
    /* (6) Chat AJAX Handlers
    /* ------------------------------------------------------------------------ */

    public function fa_send_chat_message()
    {
        fa_plugin_log('fa_send_chat_message AJAX handler triggered.');

        check_ajax_referer('fa_chat_nonce', 'nonce');

        if (!is_user_logged_in()) {
            fa_plugin_log('User not logged in.');
            wp_send_json_error(__('غير مصرح لك بأداء هذا الإجراء.', 'fashion-academy-lms'));
        }

        $user_id = get_current_user_id();
        $admin_id = get_option('fa_admin_user_id');
        $recipient_id = isset($_POST['recipient_id']) ? intval($_POST['recipient_id']) : 0;
        $message = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';

        fa_plugin_log("User ID: $user_id, Admin ID: $admin_id, Recipient ID: $recipient_id, Message: $message");

        if (empty($message) || $recipient_id <= 0) {
            fa_plugin_log('Invalid message or recipient.');
            wp_send_json_error(__('الرسالة أو المستلم غير صالح.', 'fashion-academy-lms'));
        }

        // Determine sender and recipient
        if (current_user_can('manage_options')) {
            // Admin sending message to student
            $sender_id = $admin_id;
            $recipient_user_id = $recipient_id;
        } else {
            // Student sending message to admin
            $sender_id = $user_id;
            $recipient_user_id = $admin_id;
        }

        global $wpdb;
        $chat_table = $wpdb->prefix . 'chat_messages';

        $inserted = $wpdb->insert(
            $chat_table,
            array(
                'user_id'       => $recipient_user_id, // Conversation tied to the student
                'sender_id'     => $sender_id,
                'message'       => $message,
                'timestamp'     => current_time('mysql'),
                'read_status'   => 0,
                'attachment_url'=> ''
            ),
            array(
                '%d',
                '%d',
                '%s',
                '%s',
                '%d',
                '%s'
            )
        );

        if ($wpdb->insert_id) {
            fa_plugin_log('Message inserted successfully with ID: ' . $wpdb->insert_id);
            wp_send_json_success($message); // Return the actual message content
        } else {
            fa_plugin_log('Failed to insert message.');
            wp_send_json_error(__('فشل في إرسال الرسالة.', 'fashion-academy-lms'));
        }

        wp_die();
    }


    public function fa_fetch_chat_messages()
    {
        fa_plugin_log('fa_fetch_chat_messages AJAX handler triggered.');

        check_ajax_referer('fa_chat_nonce', 'nonce');

        if (!is_user_logged_in()) {
            fa_plugin_log('User not logged in.');
            wp_send_json_error(__('غير مصرح لك بأداء هذا الإجراء.', 'fashion-academy-lms'));
        }

        $user_id = get_current_user_id();
        $admin_id = get_option('fa_admin_user_id');
        $recipient_id = isset($_POST['recipient_id']) ? intval($_POST['recipient_id']) : 0;
        $last_timestamp = isset($_POST['last_timestamp']) ? sanitize_text_field($_POST['last_timestamp']) : '';

        fa_plugin_log("User ID: $user_id, Admin ID: $admin_id, Recipient ID: $recipient_id, Last Timestamp: $last_timestamp");

        if ($recipient_id <= 0) {
            fa_plugin_log('Invalid recipient ID.');
            wp_send_json_error(__('المستلم غير صالح.', 'fashion-academy-lms'));
        }

        // Determine conversation based on user roles
        if (current_user_can('manage_options')) {
            // Admin fetching messages with a specific student
            $conversation_user_id = $recipient_id;
        } else {
            // Student fetching messages with admin
            $conversation_user_id = $admin_id;
        }

        global $wpdb;
        $chat_table = $wpdb->prefix . 'chat_messages';

        $query = $wpdb->prepare(
            "SELECT * FROM $chat_table 
         WHERE user_id = %d 
         AND (sender_id = %d OR sender_id = %d) 
         ORDER BY timestamp ASC",
            $conversation_user_id,
            $conversation_user_id,
            get_current_user_id()
        );

        if (!empty($last_timestamp)) {
            $query .= $wpdb->prepare(" AND timestamp > %s", $last_timestamp);
        }

        fa_plugin_log("Executing Query: $query");

        $messages = $wpdb->get_results($query);

        if ($messages) {
            fa_plugin_log('Messages retrieved successfully.');
            wp_send_json_success($messages);
        } else {
            fa_plugin_log('No new messages found.');
            wp_send_json_success(array()); // No new messages
        }
    }



    /**
     * AJAX handler to fetch unread message counts.
     */
    public function fa_fetch_unread_count()
    {
        fa_plugin_log('fa_fetch_unread_count AJAX handler triggered.');

        check_ajax_referer('fa_chat_nonce', 'nonce');

        if (!is_user_logged_in()) {
            fa_plugin_log('User not logged in.');
            wp_send_json_error(__('غير مصرح لك بأداء هذا الإجراء.', 'fashion-academy-lms'));
        }

        $user_id = get_current_user_id();
        $admin_id = get_option('fa_admin_user_id');

        if (current_user_can('manage_options')) {
            // Admin: count unread messages from all students
            global $wpdb;
            $chat_table = $wpdb->prefix . 'chat_messages';
            $unread = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $chat_table 
                 WHERE user_id = %d 
                 AND sender_id != %d 
                 AND read_status = 0",
                $user_id,
                $admin_id
            ));
        } else {
            // Student: count unread messages from admin
            global $wpdb;
            $chat_table = $wpdb->prefix . 'chat_messages';
            $unread = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $chat_table 
                 WHERE user_id = %d 
                 AND sender_id = %d 
                 AND read_status = 0",
                $user_id,
                $admin_id
            ));
        }

        wp_send_json_success(intval($unread));

        wp_die();
    }


    /**
     * Helper function to get localized data without overwriting existing 'faLMS' data.
     * This ensures that multiple calls to wp_localize_script don't overwrite previous data.
     */
    private function get_translated_script_data()
    {
        return array(
            'ajaxUrl'           => admin_url('admin-ajax.php'),
            'retakeConfirm'     => __('هل أنت متأكد من إعادة الواجب؟', 'fashion-academy-lms'),
            'submitConfirm'     => __('جاري إرسال الواجب...', 'fashion-academy-lms'),
            'spinnerHTML'       => '<img decoding="async" src="' . esc_url(plugin_dir_url(__FILE__) . 'assets/img/spinner.gif') . '" ' .
                'class="fa-spinner-img" alt="Spinner">' .
                '<p class="fa-waiting-msg">' . esc_js(__('جاري إرسال الواجب...', 'fashion-academy-lms')) . '</p>',
            'pollInterval'      => 15000, // 15 seconds
            'removeButtonText'  => __('إزالة', 'fashion-academy-lms'), // Localized text for 'Remove' button
        );
    }

}