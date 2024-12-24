<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FA_Post_Types {

    public function __construct() {
        add_action('init', array($this, 'register_post_types'));
        add_action('add_meta_boxes', array($this, 'add_course_link_metabox'));
        add_action('save_post_lesson', array($this, 'save_lesson_course_link'));
    
        // Add custom column in admin list
        add_filter('manage_edit-lesson_columns', array($this, 'add_course_column'));
        add_action('manage_lesson_posts_custom_column', array($this, 'render_course_column'), 10, 2);
    }
    

    public function register_post_types() {
        /**
         * 1) Register the 'course' Custom Post Type
         */
        $labels = array(
            'name'               => __('Courses', 'fashion-academy-lms'),
            'singular_name'      => __('Course', 'fashion-academy-lms'),
            'menu_name'          => __('Courses', 'fashion-academy-lms'),
            'name_admin_bar'     => __('Course', 'fashion-academy-lms'),
            'add_new'            => __('Add New', 'fashion-academy-lms'),
            'add_new_item'       => __('Add New Course', 'fashion-academy-lms'),
            'new_item'           => __('New Course', 'fashion-academy-lms'),
            'edit_item'          => __('Edit Course', 'fashion-academy-lms'),
            'view_item'          => __('View Course', 'fashion-academy-lms'),
            'all_items'          => __('All Courses', 'fashion-academy-lms'),
            'search_items'       => __('Search Courses', 'fashion-academy-lms'),
            'parent_item_colon'  => __('Parent Courses:', 'fashion-academy-lms'),
            'not_found'          => __('No courses found.', 'fashion-academy-lms'),
            'not_found_in_trash' => __('No courses found in Trash.', 'fashion-academy-lms')
        );
        $args = array(
            'labels'             => $labels,
            'public'             => true,  
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => 'courses'),
            'capability_type'    => 'post',
            'has_archive'        => true, 
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-welcome-learn-more', // Optional icon
            'supports'           => array('title','editor','thumbnail','excerpt'),
        );
        register_post_type('course', $args);

        /**
         * 2) Register the 'lesson' Custom Post Type
         */
        $lesson_labels = array(
            'name'               => __('Lessons', 'fashion-academy-lms'),
            'singular_name'      => __('Lesson', 'fashion-academy-lms'),
            'menu_name'          => __('Lessons', 'fashion-academy-lms'),
            'name_admin_bar'     => __('Lesson', 'fashion-academy-lms'),
            'add_new'            => __('Add New', 'fashion-academy-lms'),
            'add_new_item'       => __('Add New Lesson', 'fashion-academy-lms'),
            'new_item'           => __('New Lesson', 'fashion-academy-lms'),
            'edit_item'          => __('Edit Lesson', 'fashion-academy-lms'),
            'view_item'          => __('View Lesson', 'fashion-academy-lms'),
            'all_items'          => __('All Lessons', 'fashion-academy-lms'),
            'search_items'       => __('Search Lessons', 'fashion-academy-lms'),
            'parent_item_colon'  => __('Parent Lessons:', 'fashion-academy-lms'),
            'not_found'          => __('No lessons found.', 'fashion-academy-lms'),
            'not_found_in_trash' => __('No lessons found in Trash.', 'fashion-academy-lms')
        );
        $lesson_args = array(
            'labels'             => $lesson_labels,
            'public'             => false,  
            'show_ui'            => true,   
            'show_in_menu'       => true,   
            'exclude_from_search'=> true,
            'hierarchical'       => false,
            'rewrite'            => array('slug' => 'lessons'),
            'supports'           => array('title','editor','thumbnail'),
            'menu_icon'          => 'dashicons-welcome-learn-more',
        );
        register_post_type('lesson', $lesson_args);
    }


public function add_course_link_metabox() {
    add_meta_box(
        'lesson_course_metabox',
        __('Assign to Course', 'fashion-academy-lms'),
        array($this, 'render_lesson_course_metabox'),
        'lesson',
        'side',
        'default'
    );
}

public function render_lesson_course_metabox($post) {
    // Fetch all courses
    $courses = get_posts(array(
        'post_type' => 'course',
        'numberposts' => -1,
        'post_status' => 'publish',
    ));

    // Get saved course_id from meta (if any)
    $saved_course_id = get_post_meta($post->ID, 'lesson_course_id', true);

    echo '<label for="lesson_course_id">'.__('Select Course:', 'fashion-academy-lms').'</label><br>';
    echo '<select name="lesson_course_id" id="lesson_course_id">';
    echo '<option value="">'.__('-- None --', 'fashion-academy-lms').'</option>';
    if($courses) {
        foreach($courses as $course) {
            $selected = ($saved_course_id == $course->ID) ? 'selected' : '';
            echo '<option value="'.$course->ID.'" '.$selected.'>'.esc_html($course->post_title).'</option>';
        }
    }
    echo '</select>';
}

public function save_lesson_course_link($post_id) {
    // Check user permissions, autosave, etc.
    if( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if( isset($_POST['lesson_course_id']) ) {
        update_post_meta($post_id, 'lesson_course_id', sanitize_text_field($_POST['lesson_course_id']));
    }
}


public function add_course_column($columns) {
    $columns['assigned_course'] = __('Course', 'fashion-academy-lms');
    return $columns;
}

public function render_course_column($column, $post_id) {
    if($column === 'assigned_course') {
        $course_id = get_post_meta($post_id, 'lesson_course_id', true);
        if($course_id) {
            $course_title = get_the_title($course_id);
            echo esc_html($course_title);
        } else {
            echo __('Not assigned', 'fashion-academy-lms');
        }
    }
}
    
}
