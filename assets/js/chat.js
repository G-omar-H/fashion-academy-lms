jQuery(document).ready(function($) {
    // Toggle Chat Pop-Up
    $('#fa-chat-toggle').on('click', function() {
        $('#fa-chat-popup').toggle();
        if ($('#fa-chat-popup').is(':visible')) {
            fetchChatMessages();
        }
    });

    // Close Chat Pop-Up
    $('#fa-chat-close').on('click', function() {
        $('#fa-chat-popup').hide();
    });

    // Send Message from Student
    $('#fa-chat-form').on('submit', function(e) {
        e.preventDefault();
        var message = $('#fa-chat-input').val().trim();
        if (message === '') return;

        $('#fa-chat-input').val('');

        $.ajax({
            url: faChat.ajaxUrl,
            method: 'POST',
            dataType: 'json', // Ensure response is treated as JSON
            data: {
                action: 'fa_send_chat_message',
                nonce: faChat.nonce,
                recipient_id: faChat.adminUserId,
                message: message
            },
            success: function(response) {
                if (typeof response === 'object' && response.success) {
                    appendMessage(response.data, 'sent'); // response.data is the message content
                } else {
                    alert(faChat.errorMessage || 'An unexpected error occurred.');
                    console.log('Unexpected AJAX response:', response);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                alert(faChat.errorMessage || 'حدث خطأ أثناء إرسال الرسالة.');
                console.log('AJAX Error:', textStatus, errorThrown);
            }
        });
    });

    // Function to Append Message to Chat
    function appendMessage(message, type) {
        var msgClass = type === 'sent' ? 'fa-admin-sent' : 'fa-admin-received';
        var msgHTML = '<div class="fa-admin-message ' + msgClass + '"><span>' + escapeHtml(message) + '</span></div>';
        $('#fa-chat-messages').append(msgHTML);
        $('#fa-chat-messages').scrollTop($('#fa-chat-messages')[0].scrollHeight);
    }

    // Fetch Chat Messages Periodically
    function fetchChatMessages() {
        var lastTimestamp = $('#fa-chat-messages .fa-admin-message:last-child .fa-chat-timestamp').text();
        $.ajax({
            url: faChat.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'fa_fetch_chat_messages',
                nonce: faChat.nonce,
                recipient_id: faChat.adminUserId,
                last_timestamp: lastTimestamp
            },
            success: function(response) {
                if (typeof response === 'object' && response.success && Array.isArray(response.data) && response.data.length > 0) {
                    response.data.forEach(function(msg) {
                        var sender = msg.sender_id == faChat.adminUserId ? 'sent' : 'received';
                        appendMessage(msg.message, sender);
                    });
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log('Failed to fetch messages:', textStatus, errorThrown);
            }
        });
    }

    // Polling for new messages every 5 seconds
    setInterval(function() {
        if ($('#fa-chat-popup').is(':visible')) {
            fetchChatMessages();
        }
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
