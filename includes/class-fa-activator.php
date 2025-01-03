<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FA_Activator {

    public static function activate() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Existing table: homework_submissions
        $table_homework = $wpdb->prefix . 'homework_submissions';
        $sql_homework = "CREATE TABLE IF NOT EXISTS $table_homework (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            course_id BIGINT(20) UNSIGNED NOT NULL,
            lesson_id BIGINT(20) UNSIGNED NOT NULL,
            submission_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            status VARCHAR(20) DEFAULT 'pending' NOT NULL,
            grade FLOAT DEFAULT 0 NOT NULL,
            uploaded_files TEXT,
            instructor_files TEXT,
            notes TEXT,
            admin_notes TEXT,
            PRIMARY KEY (id),
            INDEX (user_id),
            INDEX (lesson_id)
        ) $charset_collate;";

        // Existing table: course_progress
        $table_progress = $wpdb->prefix . 'course_progress';
        $sql_progress = "CREATE TABLE IF NOT EXISTS $table_progress (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            course_id BIGINT(20) UNSIGNED NOT NULL,
            lesson_id BIGINT(20) UNSIGNED NOT NULL,
            progress_status VARCHAR(20) DEFAULT 'incomplete' NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_lesson (user_id, lesson_id),
            INDEX (user_id),
            INDEX (lesson_id)
        ) $charset_collate;";

        // New table: course_module_payments
        $table_module_payments = $wpdb->prefix . 'course_module_payments';
        $sql_module_payments = "CREATE TABLE IF NOT EXISTS $table_module_payments (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            module_id BIGINT(20) UNSIGNED NOT NULL,
            payment_status VARCHAR(20) DEFAULT 'unpaid' NOT NULL,
            payment_date DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_module (user_id, module_id),
            INDEX (user_id),
            INDEX (module_id)
        ) $charset_collate;";

        // Revised table: chat_messages
        $table_chat = $wpdb->prefix . 'chat_messages';
        $sql_chat = "CREATE TABLE IF NOT EXISTS $table_chat (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            sender_id BIGINT(20) UNSIGNED NOT NULL,
            message TEXT NOT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            read_status BOOLEAN DEFAULT FALSE NOT NULL,
            attachment_url VARCHAR(255) NULL,
            PRIMARY KEY (id),
            INDEX (user_id),
            INDEX (sender_id),
            INDEX (timestamp)
        ) $charset_collate;";


        // Set admin user ID
        $admin_users = get_users(array(
            'role'    => 'administrator',
            'number'  => 1,
            'orderby' => 'ID',
            'order'   => 'ASC'
        ));

        if (!empty($admin_users)) {
            update_option('fa_admin_user_id', $admin_users[0]->ID);
            fa_plugin_log('FA Plugin Activation: Admin User ID set to ' . $admin_users[0]->ID);
        } else {
            // Handle cases where no admin exists
            fa_plugin_log('FA Plugin Activation: No administrator found.');
        }

        // Run dbDelta for tables
        dbDelta( $sql_homework );
        dbDelta( $sql_progress );
        dbDelta( $sql_module_payments );
        dbDelta( $sql_chat );

        // Initialize admin user ID if not set
        if (!get_option('fa_admin_user_id')) {
            if ($admin_users) {
                update_option('fa_admin_user_id', $admin_users[0]->ID);
                fa_plugin_log('FA Plugin Activation: Admin User ID set to ' . $admin_users[0]->ID);
            }
        }
    }

}
