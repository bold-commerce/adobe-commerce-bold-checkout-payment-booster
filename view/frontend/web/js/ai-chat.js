define([
    'jquery',
    'mage/translate',
    'ko',
    'mage/storage',
    'Magento_Customer/js/customer-data'
], function ($, $t, ko, storage, customerData) {
    'use strict';

    return function (config, element) {
        var self = this;
        var $container = $(element);
        var cartId = null;
        var chatOpen = false;

        // Initialize chat bubble
        function initializeChatBubble() {
            var chatHtml = [
                '<div class="bold-ai-chat-bubble">',
                    '<div class="chat-bubble-icon">',
                        '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">',
                            '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>',
                        '</svg>',
                    '</div>',
                '</div>',
                '<div class="bold-ai-chat-window" style="display: none;">',
                    '<div class="chat-header">',
                        '<h3>' + $t('Shopping Assistant') + '</h3>',
                        '<button class="chat-close-btn">×</button>',
                    '</div>',
                    '<div class="chat-messages">',
                        '<div class="chat-message bot-message">',
                            '<p>' + $t('Hello! I can help you find products, add them to your cart, and checkout. How can I assist you today?') + '</p>',
                        '</div>',
                    '</div>',
                    '<div class="chat-input-container">',
                        '<input type="text" class="chat-input" placeholder="' + $t('Type your message...') + '">',
                        '<button class="chat-send-btn">' + $t('Send') + '</button>',
                    '</div>',
                '</div>'
            ].join('');

            $container.html(chatHtml);
            bindEvents();
            createEmptyCart();
        }

        // Bind chat events
        function bindEvents() {
            // Toggle chat window
            $container.on('click', '.bold-ai-chat-bubble', function () {
                toggleChat();
            });

            // Close chat
            $container.on('click', '.chat-close-btn', function () {
                closeChat();
            });

            // Send message
            $container.on('click', '.chat-send-btn', function () {
                sendMessage();
            });

            // Send message on Enter
            $container.on('keypress', '.chat-input', function (e) {
                if (e.which === 13) {
                    sendMessage();
                }
            });
        }

        // Toggle chat window
        function toggleChat() {
            chatOpen = !chatOpen;
            if (chatOpen) {
                $('.bold-ai-chat-window').fadeIn(300);
                $('.bold-ai-chat-bubble').hide();
                $('.chat-input').focus();
            } else {
                closeChat();
            }
        }

        // Close chat
        function closeChat() {
            chatOpen = false;
            $('.bold-ai-chat-window').fadeOut(300);
            $('.bold-ai-chat-bubble').show();
        }

        // Create empty cart
        function createEmptyCart() {
            var serviceUrl = window.bold_ai_chat_config.graphqlUrl;
            var payload = {
                query: window.bold_ai_chat_config.createCartMutation
            };

            storage.post(
                serviceUrl,
                JSON.stringify(payload),
                false,
                'application/json'
            ).done(function (response) {
                if (response.data && response.data.createEmptyCart) {
                    cartId = response.data.createEmptyCart;
                    console.log('Cart created with ID:', cartId);
                }
            }).fail(function (response) {
                console.error('Failed to create cart:', response);
            });
        }

        // Send message
        function sendMessage() {
            var $input = $('.chat-input');
            var message = $input.val().trim();

            if (!message) {
                return;
            }

            // Add user message to chat
            appendMessage(message, 'user');
            $input.val('');

            // Simulate bot response (replace with actual AI integration)
            setTimeout(function () {
                var botResponse = $t('I understand you said: "') + message + $t('". This is a demo response. Integration with Dialogflow will be added here.');
                appendMessage(botResponse, 'bot');
            }, 1000);
        }

        // Append message to chat
        function appendMessage(message, sender) {
            var messageClass = sender === 'user' ? 'user-message' : 'bot-message';
            var messageHtml = '<div class="chat-message ' + messageClass + '"><p>' + message + '</p></div>';
            
            $('.chat-messages').append(messageHtml);
            $('.chat-messages').scrollTop($('.chat-messages')[0].scrollHeight);
        }

        // Initialize when DOM is ready
        $(document).ready(function () {
            if (window.bold_ai_chat_config && window.bold_ai_chat_config.isEnabled) {
                initializeChatBubble();
            }
        });
    };
}); 