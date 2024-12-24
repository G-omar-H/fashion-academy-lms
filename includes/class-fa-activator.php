<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FA_Activator {

    public static function activate() {
        global $wpdb;

        // 1) Set up table name
        $table_name = $wpdb->prefix . 'homework_submissions';
        $charset_collate = $wpdb->get_charset_collate();

        // 2) Prepare the SQL statement
        // NOTE: dbDelta requires the lines to be uppercase for CREATE TABLE and all keys, etc.
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            course_id BIGINT(20) UNSIGNED NOT NULL,
            lesson_id BIGINT(20) UNSIGNED NOT NULL,
            submission_date DATETIME NOT NULL,
            status VARCHAR(20) DEFAULT 'pending' NOT NULL,
            grade FLOAT DEFAULT 0 NOT NULL,
            -- If you want to store file URLs or attachments, you can add a column here
            PRIMARY KEY (id)
        ) $charset_collate;";



        // 3) Load the dbDelta function
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // 4) Run dbDelta
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

    }
}
