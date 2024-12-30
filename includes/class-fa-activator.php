<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FA_Activator {

    public static function activate() {
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
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Existing table: course_progress
        $table_progress = $wpdb->prefix . 'course_progress';
        $sql_progress = "CREATE TABLE IF NOT EXISTS $table_progress (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            course_id BIGINT(20) UNSIGNED NOT NULL,
            lesson_id BIGINT(20) UNSIGNED NOT NULL,
            progress_status VARCHAR(20) DEFAULT 'incomplete' NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Revised table: chat_messages
        $table_chat = $wpdb->prefix . 'chat_messages';
        $sql_chat = "CREATE TABLE IF NOT EXISTS $table_chat (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            lesson_id BIGINT(20) UNSIGNED NOT NULL,
            sender_id BIGINT(20) UNSIGNED NOT NULL,
            message TEXT NOT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            read_status BOOLEAN DEFAULT FALSE NOT NULL,
            attachment_url VARCHAR(255) NULL,
            PRIMARY KEY (id),
            INDEX (lesson_id),
            INDEX (sender_id)
        ) $charset_collate;";

        // Load the dbDelta function
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // Run dbDelta for each table
        dbDelta( $sql_homework );
        dbDelta( $sql_progress );
        dbDelta( $sql_chat );
    }
}
?>
