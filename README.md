# Fashion Academy LMS Plugin

Welcome to the Fashion Academy LMS Plugin! This WordPress plugin is designed to provide a comprehensive Learning Management System (LMS) for fashion enthusiasts and professionals.


## Features

- **Course Management**: Create, manage, and organize fashion courses.
- **Student Enrollment**: Easy enrollment process for students.
- **Progress Tracking**: Track student progress and performance.
- **Quizzes and Assignments**: Create quizzes and assignments to evaluate students.
- **Responsive Design**: Fully responsive and mobile-friendly.

## Installation

1. Download the plugin from the repository.
2. Upload the plugin files to the `/wp-content/plugins/fashion-academy-lms` directory.
3. Activate the plugin through the 'Plugins' menu in WordPress.

## Usage

1. Navigate to the 'Fashion Academy LMS' menu in the WordPress dashboard.
2. Create and manage courses, quizzes, and assignments.
3. Monitor student progress and manage enrollments.

## Project Structure
The project structure is organized as follows:

```
fashion-academy-lms/
├── fashion-academy-lms.php // Main plugin file
├── admin/
│   └── class-fa-admin.php // Admin menus, backend logic
├── public/
│   └── class-fa-frontend.php // Frontend display logic
├── includes/
│   ├── class-fa-post-types.php // Defines custom post types
│   ├── class-fa-activator.php // Runs on plugin activation (DB table creation)
│   └── ...
├── assets/
│   ├── css/ // Styles for admin or frontend
│   └── js/ // JavaScript for admin or frontend
└── README.md
```

- **fashion-academy-lms.php**: Main plugin file.
- **admin/**: Contains admin-specific functionality.
    - **class-fa-admin.php**: Admin menus, backend logic.
- **public/**: Contains frontend-specific functionality.
    - **class-fa-frontend.php**: Frontend display logic.
- **includes/**: Contains core functionality of the plugin.
    - **class-fa-post-types.php**: Defines custom post types.
    - **class-fa-activator.php**: Runs on plugin activation (DB table creation).
- **assets/**: Contains CSS and JavaScript files.
    - **css/**: Styles for admin or frontend.
    - **js/**: JavaScript for admin or frontend.
- **README.md**: Project documentation.

## Contributing

We welcome contributions from the community! To contribute:

1. Fork the repository.
2. Create a new branch (`git checkout -b feature-branch`).
3. Make your changes and commit them (`git commit -m 'Add new feature'`).
4. Push to the branch (`git push origin feature-branch`).
5. Create a new Pull Request.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Contact

For any questions or support, please contact us at support@fashionacademy.ma.

Thank you for using the Fashion Academy LMS Plugin!
