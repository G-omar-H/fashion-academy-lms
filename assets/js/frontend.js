// frontend.js

/**
 * Function to handle retaking homework.
 * Confirm with the user before redirecting.
 */
function retakeHomework(submissionId) {
    if (!confirm(faLMS.retakeConfirm)) return;
    window.location.href = '?retake_homework=' + submissionId;
}

document.addEventListener('DOMContentLoaded', function () {
    /**
     * Handle form submission to show spinner.
     */
    var homeworkForms = [
        {
            formId: 'fa-homework-form',
            containerId: 'fa-homework-container'
        },
        {
            formId: 'fa-admin-homework-form',
            containerId: null // No container to hide for admin form
        }
    ];

    homeworkForms.forEach(function (formConfig) {
        var form = document.getElementById(formConfig.formId);
        if (form) {
            form.addEventListener('submit', function (e) {
                if (formConfig.containerId) {
                    var container = document.getElementById(formConfig.containerId);
                    if (container) {
                        container.style.display = 'none';
                    }
                }

                // Show spinner
                var spinnerDiv = document.createElement('div');
                spinnerDiv.innerHTML = faLMS.spinnerHTML;
                form.parentNode.insertBefore(spinnerDiv, form.nextSibling);
            });
        }
    });

    /**
     * Function to initialize file upload preview and removal.
     * @param {string} fileInputId - The ID of the file input element.
     * @param {string} previewContainerId - The ID of the preview container element.
     */
    function initializeFileUploadPreview(fileInputId, previewContainerId) {
        var fileInput = document.getElementById(fileInputId);
        var filePreview = document.getElementById(previewContainerId);

        if (fileInput && filePreview) {
            // Find the form that contains the fileInput
            var form = fileInput.closest('form');
            if (!form) {
                console.warn('Form not found for file input:', fileInputId);
                return;
            }

            // Initialize a DataTransfer object to manage the files
            var dt = new DataTransfer();

            // Handle new file selections
            fileInput.addEventListener('change', function () {
                var files = Array.from(this.files);

                files.forEach(function (file) {
                    // Check for duplicates in the DataTransfer
                    var duplicate = Array.from(dt.files).some(function (f) {
                        return f.name === file.name &&
                            f.size === file.size &&
                            f.lastModified === file.lastModified;
                    });

                    if (!duplicate) {
                        // Add the file to the DataTransfer
                        dt.items.add(file);

                        // Append the preview
                        var fileDiv = document.createElement('div');
                        fileDiv.className = 'fa-file-preview';

                        var fileName = document.createElement('span');
                        fileName.textContent = file.name;
                        fileDiv.appendChild(fileName);

                        var removeButton = document.createElement('button');
                        removeButton.type = 'button';
                        removeButton.className = 'fa-remove-file';
                        removeButton.textContent = faLMS.removeButtonText; // Use localized text

                        // Attach event listener to remove files
                        removeButton.addEventListener('click', function () {
                            var fileIndex = Array.from(dt.files).findIndex(function (f) {
                                return f.name === file.name &&
                                    f.size === file.size &&
                                    f.lastModified === file.lastModified;
                            });

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
            form.addEventListener('submit', function () {
                fileInput.files = dt.files; // Update file input with DataTransfer files
            });
        }
    }

    // Initialize file upload previews for both student and admin forms
    initializeFileUploadPreview('homework_files', 'file_preview');
    initializeFileUploadPreview('instructor_files', 'admin_file_preview');

    /**
     * Handle AJAX polling for submission status.
     */
    var submissionStatus = faLMS.currentStatus;
    var submissionId = faLMS.submissionId;
    var pollInterval = faLMS.pollInterval; // in milliseconds

    if (submissionStatus === 'pending') {
        setInterval(checkSubmissionStatus, pollInterval);
    }

    function checkSubmissionStatus() {
        fetch(faLMS.ajaxUrl + '?action=fa_check_submission&submission_id=' + submissionId, {
            credentials: 'same-origin'
        })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                console.log('AJAX Response:', data);

                if (!data.success) {
                    console.warn('AJAX Error:', data.data);
                    return;
                }

                var newStatus = data.data.status;

                if (newStatus !== submissionStatus) {
                    // Reload the page if the status has changed
                    window.location.reload();
                }
            })
            .catch(function(err) {
                console.log('Fetch error:', err);
            });
    }
});
