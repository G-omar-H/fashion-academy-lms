<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FA_Post_Types {


    public function __construct() {
        add_action('init', array($this, 'register_post_types'));
        add_action('add_meta_boxes', array($this, 'add_course_link_metabox'));
        add_action('add_meta_boxes', array($this, 'add_lesson_order_metabox')); // Add this line
        add_action('save_post_lesson', array($this, 'save_lesson_course_link'));
        add_action('save_post_lesson', array($this, 'save_lesson_order')); // Add this line

        // Hook the auto_assign_lesson_order function
        add_action('save_post', array($this, 'auto_assign_lesson_order'), 20, 3);

        // Add custom column in admin list
        add_filter('manage_edit-lesson_columns', array($this, 'add_course_column'));
        add_action('manage_lesson_posts_custom_column', array($this, 'render_course_column'), 10, 2);

        // Add custom column for lesson order
        add_filter('manage_edit-lesson_columns', array($this, 'add_lesson_order_column')); // Add this line
        add_action('manage_lesson_posts_custom_column', array($this, 'render_lesson_order_column'), 10, 2); // Add this line

        add_shortcode('fa_custom_register', 'fa_render_registration_form');
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
            'public'             => true,  // Changed from false to true
            'publicly_queryable' => true,  // Ensure lessons can be queried publicly
            'show_ui'            => true,   
            'show_in_menu'       => true,   
            'exclude_from_search'=> false, // Include in search if desired
            'hierarchical'       => false,
            'rewrite'            => array('slug' => 'lessons'), // Set a URL slug
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

    /**
     * Add Lesson Order Meta Box
     */
    public function add_lesson_order_metabox() {
        add_meta_box(
            'fa_lesson_order_metabox',
            __('Lesson Order', 'fashion-academy-lms'),
            array($this, 'render_lesson_order_metabox'),
            'lesson',
            'side',
            'default'
        );
    }

    /**
     * Render Lesson Order Meta Box
     */
    public function render_lesson_order_metabox($post) {
        // Add a nonce field for security
        wp_nonce_field('fa_save_lesson_order', 'fa_lesson_order_nonce');

        // Retrieve existing value from the database
        $lesson_order = get_post_meta($post->ID, 'lesson_order', true);
        ?>
        <label for="fa_lesson_order"><?php _e('Order of Lesson within the Course:', 'fashion-academy-lms'); ?></label>
        <input type="number" name="fa_lesson_order" id="fa_lesson_order" value="<?php echo esc_attr($lesson_order); ?>" min="1" style="width: 100%;" />
        <?php
    }

    /**
     * Save Lesson Order Meta Box Data
     */
    public function save_lesson_order($post_id) {
        // Security checks & nonce verification
        if ( ! isset($_POST['fa_lesson_order_nonce']) ) {
            return;
        }
        if ( ! wp_verify_nonce($_POST['fa_lesson_order_nonce'], 'fa_save_lesson_order') ) {
            return;
        }
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
            return;
        }
        if ( isset($_POST['post_type']) && 'lesson' === $_POST['post_type'] ) {
            if ( ! current_user_can('edit_post', $post_id) ) {
                return;
            }
        } else {
            return;
        }

        // If admin manually sets a lesson order
        if ( isset($_POST['fa_lesson_order']) ) {
            $lesson_order = (int) $_POST['fa_lesson_order'];
            update_post_meta($post_id, 'lesson_order', $lesson_order);

            // If user typed 0 or negative, you might force it to 1 or show an error
            // if ($lesson_order <= 0) {
            //     // e.g. force it to 1 or show error
            // }

            // Now check for duplicates
            $course_id = get_post_meta($post_id, 'lesson_course_id', true);
            if ($course_id && $lesson_order > 0) {
                $this->ensure_unique_order($post_id, $course_id, $lesson_order);
            }
        }
    }

    /**
     * If the admin manually sets a duplicate order, auto-increment or handle the conflict.
     */
    private function ensure_unique_order($post_id, $course_id, $lesson_order) {
        $duplicates = get_posts(array(
            'post_type' => 'lesson',
            'meta_query' => array(
                'relation' => 'AND',
                array('key' => 'lesson_course_id', 'value' => $course_id),
                array('key' => 'lesson_order', 'value' => $lesson_order),
            ),
            'exclude' => array($post_id),
            'fields'  => 'ids'
        ));

        if (! empty($duplicates)) {
            // We found a duplicate => Block publication
            fa_plugin_log("Lesson #$post_id tried to set order=$lesson_order in course #$course_id, but that order is taken. Blocking publish.");

            wp_die(
                sprintf(
                    __('Error: The order number %d in course %d is already used by another lesson. Please choose a unique order.', 'fashion-academy-lms'),
                    $lesson_order,
                    $course_id
                ),
                __('Duplicate Lesson Order', 'fashion-academy-lms'),
                array('back_link' => true)
            );
        }
    }



    /**
     * Automatically Assign Lesson Order Upon Lesson Creation
     * (Optional: Uncomment if you prefer automatic assignment)
     */
    public function auto_assign_lesson_order($post_id, $post, $update) {
        // if ($update) {
        //    return;
        // }

        // If the post_date_gmt is not the default, it's not brand new
        //if ($post->post_date_gmt != '0000-00-00 00:00:00') {
        //    return;
        //}

        if ($post->post_type !== 'lesson') {
            return;
        }

        $course_id = get_post_meta($post_id, 'lesson_course_id', true);
        if (!$course_id) {
            return;
        }

        $existing_order = get_post_meta($post_id, 'lesson_order', true);
        if (empty($existing_order)) {
            $this->assign_next_available_order($post_id, $course_id);
        }
    }

    /**
     * Helper method to assign the next available order in the same course.
     */
    private function assign_next_available_order($post_id, $course_id) {
        // Find the highest lesson_order so far in this course
        $existing_lessons = get_posts(array(
            'post_type'      => 'lesson',
            'posts_per_page' => 1,
            'meta_key'       => 'lesson_order',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'meta_query'     => array(
                array(
                    'key'     => 'lesson_course_id',
                    'value'   => $course_id,
                    'compare' => '='
                )
            )
        ));

        $last_order = 0;
        if (!empty($existing_lessons)) {
            $last_order = (int) get_post_meta($existing_lessons[0]->ID, 'lesson_order', true);
        }

        $new_order = $last_order + 1;
        update_post_meta($post_id, 'lesson_order', $new_order);

        fa_plugin_log("Auto-assigned lesson_order=$new_order to lesson #$post_id in course #$course_id (first creation).");
    }


    /**
     * Add Lesson Order Column to Admin List
     */
    public function add_lesson_order_column($columns) {
        $columns['lesson_order'] = __('Order', 'fashion-academy-lms');
        return $columns;
    }

    /**
     * Render Lesson Order Column in Admin List
     */
    public function render_lesson_order_column($column, $post_id) {
        if($column === 'lesson_order') {
            $lesson_order = get_post_meta($post_id, 'lesson_order', true);
            echo esc_html($lesson_order ? $lesson_order : __('N/A', 'fashion-academy-lms'));
        }
    }

    /**
     * Add Lesson Order Column alongside Course Column
     */
    public function add_lesson_order_column_to_courses($columns) {
        // This function is already covered by 'add_lesson_order_column'
    }

    // Existing methods for course columns...
}
?>
