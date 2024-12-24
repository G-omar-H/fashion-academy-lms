<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FA_Activator {

    public static function activate() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'homework_submissions';
        $charset_collate = $wpdb->get_charset_collate();

        // Prepare the SQL statement
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            course_id BIGINT(20) UNSIGNED NOT NULL,
            lesson_id BIGINT(20) UNSIGNED NOT NULL,
            submission_date DATETIME NOT NULL,
            status VARCHAR(20) DEFAULT 'pending' NOT NULL,
            grade FLOAT DEFAULT 0 NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Load the dbDelta function
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // Run dbDelta
        dbDelta( $sql );

        $table2 = $wpdb->prefix . 'course_progress';
        $sql2 = "CREATE TABLE IF NOT EXISTS $table2 (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            course_id BIGINT(20) UNSIGNED NOT NULL,
            lesson_id BIGINT(20) UNSIGNED NOT NULL,
            progress_status VARCHAR(20) DEFAULT 'incomplete' NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql2);

        // Create chat messages table
        $table3 = $wpdb->prefix . 'chat_messages';
        $sql3 = "CREATE TABLE IF NOT EXISTS $table3 (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            sender_id BIGINT(20) UNSIGNED NOT NULL,
            receiver_id BIGINT(20) UNSIGNED NOT NULL,
            message TEXT NOT NULL,
            date_sent DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql3);
    }
}