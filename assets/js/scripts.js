
    document.addEventListener('DOMContentLoaded', function() {
    const submissionId = parseInt("<?php echo (int) $submission->id; ?>", 10);
    const pollInterval = 15000; // 15 seconds

    // If submission is in "pending", we poll:
    let submissionStatus = `<?php echo esc_js($submission->status); ?>`;
    if (submissionStatus === 'pending') {
    setInterval(checkSubmissionStatus, pollInterval);
}

    function checkSubmissionStatus() {
    fetch(`<?php echo admin_url('admin-ajax.php'); ?>?action=fa_check_submission&submission_id=${submissionId}`, {
    credentials: 'same-origin'
})
    .then(r => r.json())
    .then(data => {
    // data might be { status: 'passed', grade: 85 }
    if (data.status !== submissionStatus) {
    // The status changed => reload or re-render
    window.location.reload();
}
})
    .catch(err => console.log(err));
}
});
