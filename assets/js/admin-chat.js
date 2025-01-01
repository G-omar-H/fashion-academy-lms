jQuery(document).ready(function($) {
    var recipient_id = $('#fa-admin-chat-form').data('recipient');

    // Send Message from Admin
    $('#fa-admin-chat-form').on('submit', function(e) {
        e.preventDefault();
        var message = $('#fa-admin-chat-input').val().trim();
        if (message === '') return;

        $('#fa-admin-chat-input').val('');

        $.ajax({
            url: faChat.ajaxUrl,
            method: 'POST',
            data: {
                action: 'fa_send_chat_message',
                nonce: faChat.nonce,
                recipient_id: recipient_id,
                message: message
            },
            success: function(response) {
                if (response.success) {
                    appendAdminMessage(message);
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert(faChat.errorMessage || 'An error occurred.');
            }
        });
    });

    // Fetch Chat Messages Periodically
    function fetchChatMessages() {
        var lastTimestamp = $('#fa-admin-chat-messages .fa-admin-message:last-child .fa-chat-timestamp').text();
        $.ajax({
            url: faChat.ajaxUrl,
            method: 'POST',
            data: {
                action: 'fa_fetch_chat_messages',
                nonce: faChat.nonce,
                recipient_id: recipient_id,
                last_timestamp: lastTimestamp
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    response.data.forEach(function(msg) {
                        var sender = msg.sender_id == faChat.adminUserId ? 'sent' : 'received';
                        appendAdminMessage(msg.message, sender);
                    });
                }
            },
            error: function() {
                console.log('Failed to fetch messages.');
            }
        });
    }

    // Append Admin Message
    function appendAdminMessage(message) {
        var msgClass = 'fa-admin-sent';
        var msgHTML = '<div class="fa-admin-message ' + msgClass + '"><span>' + escapeHtml(message) + '</span></div>';
        $('#fa-admin-chat-messages').append(msgHTML);
        $('#fa-admin-chat-messages').scrollTop($('#fa-admin-chat-messages')[0].scrollHeight);
    }

    // Polling for new messages every 5 seconds
    setInterval(function() {
        fetchChatMessages();
    }, 5000);

    // Utility Function to Escape HTML
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
