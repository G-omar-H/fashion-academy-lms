<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class FA_Post_Types {



    public function __construct() {
        // Register Custom Post Types
        add_action('init', array($this, 'register_post_types'));

        // Add Meta Boxes
        add_action('add_meta_boxes', array($this, 'add_lesson_meta_boxes'));
        add_action('add_meta_boxes', array($this, 'add_module_meta_boxes'));

        // Save Meta Box Data
        add_action('save_post_lesson', array($this, 'save_lesson_meta'));
        add_action('save_post_module', array($this, 'save_module_meta'));

        // Automatically Assign Orders Upon Creation
        add_action('save_post', array($this, 'auto_assign_lesson_order'), 20, 3);
        add_action('save_post', array($this, 'auto_assign_module_order'), 20, 3);

        // Add Custom Columns in Admin List
        add_filter('manage_edit-lesson_columns', array($this, 'set_lesson_columns'));
        add_action('manage_lesson_posts_custom_column', array($this, 'custom_lesson_column'), 10, 2);

        add_filter('manage_edit-module_columns', array($this, 'set_module_columns'));
        add_action('manage_module_posts_custom_column', array($this, 'custom_module_column'), 10, 2);

        // Make Columns Sortable (Optional)
        add_filter('manage_edit-lesson_sortable_columns', array($this, 'sortable_lesson_columns'));
        add_filter('manage_edit-module_sortable_columns', array($this, 'sortable_module_columns'));

        // Add Shortcode
        add_shortcode('fa_custom_register', array($this, 'fa_render_registration_form'));

        // handle post deletion
        add_action('before_delete_post', array($this, 'handle_module_deletion'));
    }

    /**
     * Register Custom Post Types: Course, Lesson, Module
     */
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
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'exclude_from_search'=> false,
            'hierarchical'       => false,
            'rewrite'            => array('slug' => 'lessons'),
            'supports'           => array('title','editor','thumbnail'),
            'menu_icon'          => 'dashicons-welcome-learn-more',
        );
        register_post_type('lesson', $lesson_args);

        /**
         * 3) Register the 'module' Custom Post Type
         */
        $module_labels = array(
            'name'                  => _x( 'Modules', 'Post Type General Name', 'fashion-academy-lms' ),
            'singular_name'         => _x( 'Module', 'Post Type Singular Name', 'fashion-academy-lms' ),
            'menu_name'             => __( 'Modules', 'fashion-academy-lms' ),
            'name_admin_bar'        => __( 'Module', 'fashion-academy-lms' ),
            'archives'              => __( 'Module Archives', 'fashion-academy-lms' ),
            'attributes'            => __( 'Module Attributes', 'fashion-academy-lms' ),
            'parent_item_colon'     => __( 'Parent Module:', 'fashion-academy-lms' ),
            'all_items'             => __( 'All Modules', 'fashion-academy-lms' ),
            'add_new_item'          => __( 'Add New Module', 'fashion-academy-lms' ),
            'add_new'               => __( 'Add New', 'fashion-academy-lms' ),
            'new_item'              => __( 'New Module', 'fashion-academy-lms' ),
            'edit_item'             => __( 'Edit Module', 'fashion-academy-lms' ),
            'update_item'           => __( 'Update Module', 'fashion-academy-lms' ),
            'view_item'             => __( 'View Module', 'fashion-academy-lms' ),
            'view_items'            => __( 'View Modules', 'fashion-academy-lms' ),
            'search_items'          => __( 'Search Module', 'fashion-academy-lms' ),
            'not_found'             => __( 'Not found', 'fashion-academy-lms' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'fashion-academy-lms' ),
            'featured_image'        => __( 'Featured Image', 'fashion-academy-lms' ),
            'set_featured_image'    => __( 'Set featured image', 'fashion-academy-lms' ),
            'remove_featured_image' => __( 'Remove featured image', 'fashion-academy-lms' ),
            'use_featured_image'    => __( 'Use as featured image', 'fashion-academy-lms' ),
            'insert_into_item'      => __( 'Insert into module', 'fashion-academy-lms' ),
            'uploaded_to_this_item' => __( 'Uploaded to this module', 'fashion-academy-lms' ),
            'items_list'            => __( 'Modules list', 'fashion-academy-lms' ),
            'items_list_navigation' => __( 'Modules list navigation', 'fashion-academy-lms' ),
            'filter_items_list'     => __( 'Filter modules list', 'fashion-academy-lms' ),
        );

        $module_args = array(
            'label'                 => __( 'Module', 'fashion-academy-lms' ),
            'description'           => __( 'Course Modules', 'fashion-academy-lms' ),
            'labels'                => $module_labels,
            'supports'              => array( 'title', 'editor', 'thumbnail' ),
            'taxonomies'            => array(), // Add if needed
            'hierarchical'          => false,
            'public'                => true,
            'show_ui'               => true,
            'show_in_menu'          => 'fa-dashboard', // Place under Fashion Academy menu
            'menu_position'         => 6,
            'menu_icon'             => 'dashicons-book', // Choose an appropriate icon
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => true,
            'exclude_from_search'   => false,
            'publicly_queryable'    => true,
            'capability_type'       => 'post',
        );

        register_post_type( 'module', $module_args );
    }

    /**
     * Add Meta Boxes for Lessons
     */
    public function add_lesson_meta_boxes() {
        // Assign Course to Lesson
        add_meta_box(
            'lesson_course_metabox',
            __('Assign to Course', 'fashion-academy-lms'),
            array($this, 'render_lesson_course_metabox'),
            'lesson',
            'side',
            'default'
        );

        // Assign Module to Lesson (Optional)
        add_meta_box(
            'lesson_module_metabox',
            __('Assign to Module', 'fashion-academy-lms'),
            array($this, 'render_lesson_module_metabox'),
            'lesson',
            'side',
            'default'
        );

        // Lesson Order
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
     * Add Meta Boxes for Modules
     */
    public function add_module_meta_boxes() {
        // Assign Course to Module
        add_meta_box(
            'module_course_metabox',
            __('Assign to Course', 'fashion-academy-lms'),
            array($this, 'render_module_course_metabox'),
            'module',
            'side',
            'default'
        );

        // Module Order
        add_meta_box(
            'fa_module_order_metabox',
            __('Module Order within Course', 'fashion-academy-lms'),
            array($this, 'render_module_order_metabox'),
            'module',
            'side',
            'default'
        );
    }

    /**
     * Render the Course Assignment Meta Box for Lessons
     */
    public function render_lesson_course_metabox($post) {
        // Fetch all courses
        $courses = get_posts(array(
            'post_type'      => 'course',
            'numberposts'    => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC'
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

    /**
     * Render the Module Assignment Meta Box for Lessons
     */
    public function render_lesson_module_metabox($post) {
        // Fetch all modules
        $modules = get_posts(array(
            'post_type'      => 'module',
            'numberposts'    => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC'
        ));

        // Get saved module_id from meta (if any)
        $saved_module_id = get_post_meta($post->ID, 'lesson_module_id', true);

        echo '<label for="lesson_module_id">'.__('Select Module:', 'fashion-academy-lms').'</label><br>';
        echo '<select name="lesson_module_id" id="lesson_module_id">';
        echo '<option value="">'.__('-- None --', 'fashion-academy-lms').'</option>';
        if($modules) {
            foreach($modules as $module) {
                $selected = ($saved_module_id == $module->ID) ? 'selected' : '';
                echo '<option value="'.$module->ID.'" '.$selected.'>'.esc_html($module->post_title).'</option>';
            }
        }
        echo '</select>';
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
        <label for="fa_lesson_order"><?php _e('Order of Lesson:', 'fashion-academy-lms'); ?></label>
        <input type="number" name="fa_lesson_order" id="fa_lesson_order" value="<?php echo esc_attr($lesson_order); ?>" min="1" style="width: 100%;" />
        <?php
    }

    /**
     * Save Lesson Meta Data (Course, Module, Order)
     */
    public function save_lesson_meta($post_id) {
        // Verify nonce for Course
        if ( isset($_POST['lesson_course_id']) ) {
            update_post_meta($post_id, 'lesson_course_id', sanitize_text_field($_POST['lesson_course_id']));
        }

        // Verify nonce for Module
        if ( isset($_POST['lesson_module_id']) ) {
            update_post_meta($post_id, 'lesson_module_id', sanitize_text_field($_POST['lesson_module_id']));
        }

        // Verify nonce and save Lesson Order
        if ( isset($_POST['fa_lesson_order_nonce']) ) {
            if ( ! wp_verify_nonce($_POST['fa_lesson_order_nonce'], 'fa_save_lesson_order') ) {
                return;
            }
        } else {
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

            // Fetch module ID to ensure uniqueness within the module
            $module_id = get_post_meta($post_id, 'lesson_module_id', true);
            if ($module_id && $lesson_order > 0) {
                $this->ensure_unique_lesson_order($post_id, $module_id, $lesson_order);
            }
        }
    }

    /**
     * Ensure the Lesson Order is Unique within the Module
     */
    private function ensure_unique_lesson_order($post_id, $module_id, $lesson_order) {
        $duplicates = get_posts(array(
            'post_type'   => 'lesson',
            'meta_query'  => array(
                'relation' => 'AND',
                array(
                    'key'     => 'lesson_module_id',
                    'value'   => $module_id,
                    'compare' => '='
                ),
                array(
                    'key'     => 'lesson_order',
                    'value'   => $lesson_order,
                    'compare' => '=',
                    'type'    => 'NUMERIC'
                )
            ),
            'exclude'     => array($post_id),
            'fields'      => 'ids'
        ));

        if (! empty($duplicates)) {
            // We found a duplicate => Block publication
            fa_plugin_log("Lesson #$post_id tried to set order=$lesson_order in module #$module_id, but that order is taken. Blocking publish.");

            wp_die(
                sprintf(
                    __('Error: The order number %d in module "%s" is already used by another lesson. Please choose a unique order.', 'fashion-academy-lms'),
                    $lesson_order,
                    get_the_title($module_id)
                ),
                __('Duplicate Lesson Order', 'fashion-academy-lms'),
                array('back_link' => true)
            );
        }
    }

    /**
     * Automatically Assign Lesson Order Upon Lesson Creation
     */
    public function auto_assign_lesson_order($post_id, $post, $update) {
        if ($post->post_type !== 'lesson') {
            return;
        }

        // Avoid recursion
        remove_action('save_post', array($this, 'auto_assign_lesson_order'), 20, 3);

        $module_id = get_post_meta($post_id, 'lesson_module_id', true);
        if (!$module_id) {
            // If no module is assigned, do not auto-assign
            add_action('save_post', array($this, 'auto_assign_lesson_order'), 20, 3);
            return;
        }

        $existing_order = get_post_meta($post_id, 'lesson_order', true);
        if (empty($existing_order)) {
            $this->assign_next_available_lesson_order($post_id, $module_id);
        }

        // Re-hook the action
        add_action('save_post', array($this, 'auto_assign_lesson_order'), 20, 3);
    }

    /**
     * Assign the Next Available Lesson Order within the Module
     */
    private function assign_next_available_lesson_order($post_id, $module_id) {
        // Find the highest lesson_order so far in this module
        $existing_lessons = get_posts(array(
            'post_type'      => 'lesson',
            'posts_per_page' => 1,
            'meta_key'       => 'lesson_order',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'meta_query'     => array(
                array(
                    'key'     => 'lesson_module_id',
                    'value'   => $module_id,
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

        fa_plugin_log("Auto-assigned lesson_order=$new_order to lesson #$post_id in module #$module_id.");
    }

    /**
     * Render the Course Assignment Meta Box for Modules
     */
    public function render_module_course_metabox($post) {
        // Fetch all courses
        $courses = get_posts(array(
            'post_type'      => 'course',
            'numberposts'    => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC'
        ));

        // Get saved course_id from meta (if any)
        $saved_course_id = get_post_meta($post->ID, 'module_course_id', true);

        echo '<label for="module_course_id">'.__('Select Course:', 'fashion-academy-lms').'</label><br>';
        echo '<select name="module_course_id" id="module_course_id">';
        echo '<option value="">'.__('-- None --', 'fashion-academy-lms').'</option>';
        if($courses) {
            foreach($courses as $course) {
                $selected = ($saved_course_id == $course->ID) ? 'selected' : '';
                echo '<option value="'.$course->ID.'" '.$selected.'>'.esc_html($course->post_title).'</option>';
            }
        }
        echo '</select>';
    }

    /**
     * Render Module Order Meta Box
     */
    public function render_module_order_metabox($post) {
        // Add a nonce field for security
        wp_nonce_field('fa_save_module_order', 'fa_module_order_nonce');

        // Retrieve existing value from the database
        $module_order = get_post_meta($post->ID, 'module_order', true);
        ?>
        <label for="fa_module_order"><?php _e('Order of Module within the Course:', 'fashion-academy-lms'); ?></label>
        <input type="number" name="fa_module_order" id="fa_module_order" value="<?php echo esc_attr($module_order); ?>" min="1" style="width: 100%;" />
        <?php
    }

    /**
     * Save Module Meta Data (Course, Order)
     */
    public function save_module_meta($post_id) {
        // Verify nonce for Course
        if ( isset($_POST['module_course_id']) ) {
            update_post_meta($post_id, 'module_course_id', sanitize_text_field($_POST['module_course_id']));
        }

        // Verify nonce and save Module Order
        if ( isset($_POST['fa_module_order_nonce']) ) {
            if ( ! wp_verify_nonce($_POST['fa_module_order_nonce'], 'fa_save_module_order') ) {
                return;
            }
        } else {
            return;
        }

        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
            return;
        }

        if ( isset($_POST['post_type']) && 'module' === $_POST['post_type'] ) {
            if ( ! current_user_can('edit_post', $post_id) ) {
                return;
            }
        } else {
            return;
        }

        // If admin manually sets a module order
        if ( isset($_POST['fa_module_order']) ) {
            $module_order = (int) $_POST['fa_module_order'];
            update_post_meta($post_id, 'module_order', $module_order);

            // Fetch course ID to ensure uniqueness within the course
            $course_id = get_post_meta($post_id, 'module_course_id', true);
            if ($course_id && $module_order > 0) {
                $this->ensure_unique_module_order($post_id, $course_id, $module_order);
            }
        }
    }

    /**
     * Ensure the Module Order is Unique within the Course
     */
    private function ensure_unique_module_order($post_id, $course_id, $module_order) {
        $duplicates = get_posts(array(
            'post_type'   => 'module',
            'meta_query'  => array(
                'relation' => 'AND',
                array(
                    'key'     => 'module_course_id',
                    'value'   => $course_id,
                    'compare' => '='
                ),
                array(
                    'key'     => 'module_order',
                    'value'   => $module_order,
                    'compare' => '=',
                    'type'    => 'NUMERIC'
                )
            ),
            'exclude'     => array($post_id),
            'fields'      => 'ids'
        ));

        if (! empty($duplicates)) {
            // We found a duplicate => Block publication
            fa_plugin_log("Module #$post_id tried to set order=$module_order in course #$course_id, but that order is taken. Blocking publish.");

            wp_die(
                sprintf(
                    __('Error: The order number %d in course "%s" is already used by another module. Please choose a unique order.', 'fashion-academy-lms'),
                    $module_order,
                    get_the_title($course_id)
                ),
                __('Duplicate Module Order', 'fashion-academy-lms'),
                array('back_link' => true)
            );
        }
    }

    /**
     * Automatically Assign Module Order Upon Module Creation
     */
    public function auto_assign_module_order($post_id, $post, $update) {
        if ($post->post_type !== 'module') {
            return;
        }

        // Avoid recursion
        remove_action('save_post', array($this, 'auto_assign_module_order'), 20, 3);

        $course_id = get_post_meta($post_id, 'module_course_id', true);
        if (!$course_id) {
            // If no course is assigned, do not auto-assign
            add_action('save_post', array($this, 'auto_assign_module_order'), 20, 3);
            return;
        }

        $existing_order = get_post_meta($post_id, 'module_order', true);
        if (empty($existing_order)) {
            $this->assign_next_available_module_order($post_id, $course_id);
        }

        // Re-hook the action
        add_action('save_post', array($this, 'auto_assign_module_order'), 20, 3);
    }

    /**
     * Assign the Next Available Module Order within the Course
     */
    private function assign_next_available_module_order($post_id, $course_id) {
        // Find the highest module_order so far in this course
        $existing_modules = get_posts(array(
            'post_type'      => 'module',
            'posts_per_page' => 1,
            'meta_key'       => 'module_order',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'meta_query'     => array(
                array(
                    'key'     => 'module_course_id',
                    'value'   => $course_id,
                    'compare' => '='
                )
            )
        ));

        $last_order = 0;
        if (!empty($existing_modules)) {
            $last_order = (int) get_post_meta($existing_modules[0]->ID, 'module_order', true);
        }

        $new_order = $last_order + 1;
        update_post_meta($post_id, 'module_order', $new_order);

        fa_plugin_log("Auto-assigned module_order=$new_order to module #$post_id in course #$course_id.");
    }

    /**
     * Set Custom Columns for Lessons
     */
    public function set_lesson_columns($columns) {
        // Remove unwanted columns if necessary
        unset($columns['date']);

        // Add new columns
        $columns['assigned_course'] = __('Course', 'fashion-academy-lms');
        $columns['assigned_module'] = __('Module', 'fashion-academy-lms');
        $columns['lesson_order']    = __('Order', 'fashion-academy-lms');
        $columns['date']            = __('Date', 'fashion-academy-lms'); // Re-add date at the end

        return $columns;
    }

    /**
     * Render Custom Columns for Lessons
     */
    public function custom_lesson_column($column, $post_id) {
        switch ($column) {
            case 'assigned_course':
                $course_id = get_post_meta($post_id, 'lesson_course_id', true);
                if($course_id) {
                    $course_title = get_the_title($course_id);
                    echo esc_html($course_title);
                } else {
                    echo __('Not assigned', 'fashion-academy-lms');
                }
                break;

            case 'assigned_module':
                $module_id = get_post_meta($post_id, 'lesson_module_id', true);
                if($module_id) {
                    $module_title = get_the_title($module_id);
                    echo esc_html($module_title);
                } else {
                    echo __('Not assigned', 'fashion-academy-lms');
                }
                break;

            case 'lesson_order':
                $lesson_order = get_post_meta($post_id, 'lesson_order', true);
                echo esc_html($lesson_order ? $lesson_order : __('N/A', 'fashion-academy-lms'));
                break;
        }
    }

    /**
     * Make Lesson Columns Sortable
     */
    public function sortable_lesson_columns($columns) {
        $columns['lesson_order'] = 'lesson_order';
        $columns['assigned_course'] = 'assigned_course';
        $columns['assigned_module'] = 'assigned_module';
        return $columns;
    }

    /**
     * Set Custom Columns for Modules
     */
    public function set_module_columns($columns) {
        // Remove unwanted columns if necessary
        unset($columns['date']);

        // Add new columns
        $columns['assigned_course'] = __('Course', 'fashion-academy-lms');
        $columns['module_order']   = __('Order', 'fashion-academy-lms');
        $columns['date']           = __('Date', 'fashion-academy-lms'); // Re-add date at the end

        return $columns;
    }

    /**
     * Render Custom Columns for Modules
     */
    public function custom_module_column($column, $post_id) {
        switch ($column) {
            case 'assigned_course':
                $course_id = get_post_meta($post_id, 'module_course_id', true);
                if($course_id) {
                    $course_title = get_the_title($course_id);
                    echo esc_html($course_title);
                } else {
                    echo __('Not assigned', 'fashion-academy-lms');
                }
                break;

            case 'module_order':
                $module_order = get_post_meta($post_id, 'module_order', true);
                echo esc_html($module_order ? $module_order : __('N/A', 'fashion-academy-lms'));
                break;
        }
    }

    /**
     * Make Module Columns Sortable
     */
    public function sortable_module_columns($columns) {
        $columns['module_order']   = 'module_order';
        $columns['assigned_course'] = 'assigned_course';
        return $columns;
    }

    /**
     * Handle module deletion by unassigning it from Lessons
     */
    public function handle_module_deletion($post_id) {
        // Get the post object
        $post = get_post($post_id);

        // Make sure it's a 'module' post type
        if ($post->post_type !== 'module') {
            return;
        }

        // Fetch all Lessons assigned to this Module
        $lessons = get_posts(array(
            'post_type'      => 'lesson',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => 'lesson_module_id',
                    'value'   => $post_id,
                    'compare' => '='
                )
            )
        ));

        // Unassign the Module from these Lessons
        foreach ($lessons as $lesson) {
            update_post_meta($lesson->ID, 'lesson_module_id', '');
        }

        // Log the deletion and unassignment
        if (function_exists('fa_plugin_log')) {
            fa_plugin_log("Module ID $post_id deleted. Unassigned from " . count($lessons) . " Lessons.");
        }
    }

    /**
     * Render the Registration Form Shortcode Callback
     */
    public function fa_render_registration_form() {
        // Your registration form rendering logic here
        return '<p>' . __('Registration Form Placeholder', 'fashion-academy-lms') . '</p>';
    }
}

?>
