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
    var homeworkForm = document.getElementById('fa-homework-form');
    if (homeworkForm) {
        homeworkForm.addEventListener('submit', function (e) {
            // Hide the form container
            var homeworkContainer = document.getElementById('fa-homework-container');
            if (homeworkContainer) {
                homeworkContainer.style.display = 'none';
            }

            // Show spinner
            var spinnerDiv = document.createElement('div');
            spinnerDiv.innerHTML = faLMS.spinnerHTML;
            homeworkContainer.parentNode.appendChild(spinnerDiv);
        });
    }

    /**
     * Handle file uploads: Preview and removal.
     */
    var fileInput = document.getElementById('homework_files');
    var filePreview = document.getElementById('file_preview');

    if (fileInput && filePreview) {
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
        var form = document.getElementById('fa-homework-form');
        if (form) {
            form.addEventListener('submit', function () {
                fileInput.files = dt.files; // Update file input with DataTransfer files
            });
        }
    }

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
