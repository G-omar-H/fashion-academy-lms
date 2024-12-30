// fa-chat.js

jQuery(document).ready(function ($) {
    const chatContainer = $('.fa-chat-container');
    const messagesContainer = $('#fa-chat-messages');
    const messageInput = $('#fa-chat-message');
    const sendButton = $('#fa-send-message');
    const lessonId = chatContainer.data('lesson-id');

    // Function to escape HTML to prevent XSS
    function escapeHtml(text) {
        return $('<div>').text(text).html();
    }

    // Function to format timestamp
    function formatTimestamp(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleString(); // Adjust formatting as needed
    }

    // Function to fetch messages
    function fetchMessages() {
        $.ajax({
            url: faChat.ajax_url,
            type: 'POST',
            data: {
                action: 'fa_fetch_messages',
                lesson_id: lessonId,
                nonce: faChat.nonce
            },
            success: function (response) {
                if (response.success) {
                    messagesContainer.empty();
                    response.data.messages.forEach(function (message) {
                        const messageClass = (message.sender_id === faChat.current_user_id) ? 'fa-chat-message-sent' : 'fa-chat-message-received';
                        const avatar = message.user_avatar ? `<img src="${message.user_avatar}" alt="${escapeHtml(message.display_name)}" class="fa-chat-avatar">` : '';
                        const messageElement = `
                            <div class="fa-chat-message ${messageClass}">
                                ${avatar}
                                <div class="fa-chat-message-content">
                                    <strong>${escapeHtml(message.display_name)}</strong>
                                    <p>${escapeHtml(message.message)}</p>
                                    ${message.attachment_url ? `<p><a href="${escapeHtml(message.attachment_url)}" target="_blank">${__('Attachment', 'fashion-academy-lms')}</a></p>` : ''}
                                    <span class="fa-chat-timestamp">${formatTimestamp(message.timestamp)}</span>
                                </div>
                            </div>
                        `;
                        messagesContainer.append(messageElement);
                    });
                    messagesContainer.scrollTop(messagesContainer.prop("scrollHeight"));
                } else {
                    console.error(response.data);
                }
            },
            error: function (xhr, status, error) {
                console.error(error);
            }
        });
    }

    // Initial fetch
    fetchMessages();

    // Polling for new messages every 5 seconds
    setInterval(fetchMessages, 5000);

    // Handle sending messages
    sendButton.on('click', function () {
        sendMessage();
    });

    messageInput.on('keypress', function (e) {
        if (e.which === 13 && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    function sendMessage() {
        const message = messageInput.val().trim();
        if (message === '') return;

        $.ajax({
            url: faChat.ajax_url,
            type: 'POST',
            data: {
                action: 'fa_send_message',
                lesson_id: lessonId,
                message: message,
                nonce: faChat.nonce
            },
            success: function (response) {
                if (response.success) {
                    messageInput.val('');
                    fetchMessages();
                } else {
                    alert(response.data);
                }
            },
            error: function (xhr, status, error) {
                console.error(error);
            }
        });
    }
});
