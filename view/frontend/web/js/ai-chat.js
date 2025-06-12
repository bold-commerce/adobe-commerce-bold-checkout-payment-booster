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
        var conversationHistory = [];

        // Real products from your Magento system
        var availableProducts = {
            'bag': { sku: '24-MB01', name: 'Joust Duffle Bag', price: 34.00 },
            'duffle': { sku: '24-MB01', name: 'Joust Duffle Bag', price: 34.00 },
            'backpack': { sku: '24-MB03', name: 'Crown Summit Backpack', price: 38.00 },
            'messenger': { sku: '24-MB05', name: 'Wayfarer Messenger Bag', price: 45.00 },
            'tote': { sku: '24-WB02', name: 'Compete Track Tote', price: 33.00 },
            'shoulder': { sku: '24-MB04', name: 'Strive Shoulder Pack', price: 32.00 },
            'yoga': { sku: '24-WB01', name: 'Voyage Yoga Bag', price: 32.00 }
        };

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
                        '<h3>' + $t('AI Shopping Assistant') + '</h3>',
                        '<button class="chat-close-btn">×</button>',
                    '</div>',
                    '<div class="chat-messages">',
                        '<div class="chat-message bot-message">',
                            '<p>' + $t('Hello! I\'m your AI shopping assistant. I can help you find products and add them to your cart. Try asking me about bags, backpacks, totes, or messenger bags!') + '</p>',
                        '</div>',
                    '</div>',
                    '<div class="chat-input-container">',
                        '<input type="text" class="chat-input" placeholder="' + $t('Ask me about products...') + '">',
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

            // Add to conversation history
            conversationHistory.push({
                role: 'user',
                content: message
            });

            // Show typing indicator
            appendMessage($t('AI is thinking...'), 'bot', 'typing-indicator');

            // Process with AI
            processWithAI(message);
        }

        // Process message with AI (Gemini API)
        function processWithAI(userMessage) {
            // Simple keyword detection for MVP
            var lowerMessage = userMessage.toLowerCase();
            var detectedProduct = null;

            // Check for product mentions
            Object.keys(availableProducts).forEach(function(keyword) {
                if (lowerMessage.includes(keyword)) {
                    detectedProduct = availableProducts[keyword];
                }
            });

            // Check for cart/checkout keywords
            var isCartRequest = lowerMessage.includes('cart') || lowerMessage.includes('add') || lowerMessage.includes('buy');
            var isCheckoutRequest = lowerMessage.includes('checkout') || lowerMessage.includes('purchase') || lowerMessage.includes('order');

            if (detectedProduct && isCartRequest) {
                // Add product to cart
                addProductToCart(detectedProduct);
            } else if (detectedProduct) {
                // Show product information
                showProductInfo(detectedProduct);
            } else if (isCheckoutRequest) {
                // Initiate checkout
                initiateCheckout();
            } else {
                // Call secure AI API for general questions
                callAiAPI(userMessage);
            }
        }

        // Call secure AI API endpoint
        function callAiAPI(message) {
            var apiUrl = window.bold_ai_chat_config.aiChatApiUrl;
            
            console.log('🤖 callAiAPI called with message:', message);
            console.log('🔗 API URL:', apiUrl);
            console.log('🛒 Cart ID:', cartId);
            
            if (!apiUrl) {
                console.log('❌ No API URL configured, using fallback');
                // Fallback response when API is not configured
                setTimeout(function() {
                    removeTypingIndicator();
                    var response = generateFallbackResponse(message);
                    appendMessage(response, 'bot');
                    conversationHistory.push({
                        role: 'assistant',
                        content: response
                    });
                }, 1000);
                return;
            }

            var payload = {
                message: message,
                cartId: cartId
            };
            
            console.log('📤 Sending payload:', payload);

            $.ajax({
                url: apiUrl,
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                data: JSON.stringify(payload),
                beforeSend: function(xhr) {
                    console.log('🚀 Making AJAX request to:', apiUrl);
                },
                success: function(response) {
                    console.log('✅ API Success response:', response);
                    removeTypingIndicator();
                    if (response && response.message) {
                        console.log('💬 Using AI response:', response.message);
                        appendMessage(response.message, 'bot');
                        conversationHistory.push({
                            role: 'assistant',
                            content: response.message
                        });
                    } else {
                        console.log('⚠️ Invalid response format, using error message');
                        appendMessage($t('Sorry, I had trouble understanding that. Can you try asking differently?'), 'bot');
                    }
                },
                error: function(xhr, status, error) {
                    console.log('❌ API Error - Status:', status);
                    console.log('❌ API Error - Error:', error);
                    console.log('❌ API Error - XHR:', xhr);
                    console.log('❌ API Error - Response Text:', xhr.responseText);
                    console.log('❌ API Error - Status Code:', xhr.status);
                    
                    removeTypingIndicator();
                    var fallbackResponse = generateFallbackResponse(message);
                    console.log('🔄 Using fallback response:', fallbackResponse);
                    appendMessage(fallbackResponse, 'bot');
                    conversationHistory.push({
                        role: 'assistant',
                        content: fallbackResponse
                    });
                }
            });
        }

        // Generate fallback response without API
        function generateFallbackResponse(message) {
            var lowerMessage = message.toLowerCase();
            
            if (lowerMessage.includes('hello') || lowerMessage.includes('hi')) {
                return $t('Hello! I can help you find and add products to your cart. We have backpacks, messenger bags, totes, and duffle bags available.');
            } else if (lowerMessage.includes('help')) {
                return $t('I can help you with: finding products, adding items to cart, and starting checkout. Try asking "show me backpacks" or "add tote to cart".');
            } else if (lowerMessage.includes('price')) {
                return $t('Our products range from $32.00 to $45.00. Would you like to see specific product prices?');
            } else {
                return $t('I understand you\'re interested in shopping! We have great bags available. Try asking about backpacks, messenger bags, totes, or duffle bags.');
            }
        }

        // Show product information
        function showProductInfo(product) {
            removeTypingIndicator();
            var response = $t('Great choice! The %1 (SKU: %2) is available for $%3. Would you like me to add it to your cart?')
                .replace('%1', product.name)
                .replace('%2', product.sku)
                .replace('%3', product.price);
            
            appendMessage(response, 'bot');
        }

        // Add product to cart using GraphQL
        function addProductToCart(product) {
            if (!cartId) {
                removeTypingIndicator();
                appendMessage($t('Sorry, there was an issue with your cart. Please refresh the page and try again.'), 'bot');
                return;
            }

            var mutation = `
                mutation {
                    addProductsToCart(
                        cartId: "${cartId}",
                        cartItems: [
                            {
                                sku: "${product.sku}",
                                quantity: 1
                            }
                        ]
                    ) {
                        cart {
                            items {
                                product {
                                    sku
                                    name
                                }
                                quantity
                            }
                            total_quantity
                        }
                        user_errors {
                            code
                            message
                        }
                    }
                }
            `;

            var serviceUrl = window.bold_ai_chat_config.graphqlUrl;
            var payload = {
                query: mutation
            };

            storage.post(
                serviceUrl,
                JSON.stringify(payload),
                false,
                'application/json'
            ).done(function (response) {
                removeTypingIndicator();
                if (response.data && response.data.addProductsToCart) {
                    if (response.data.addProductsToCart.user_errors && response.data.addProductsToCart.user_errors.length > 0) {
                        var errorMessage = response.data.addProductsToCart.user_errors[0].message;
                        appendMessage($t('Sorry, I couldn\'t add that product: %1').replace('%1', errorMessage), 'bot');
                    } else {
                        var totalQuantity = response.data.addProductsToCart.cart.total_quantity;
                        appendMessage($t('Perfect! I\'ve added the %1 to your cart. You now have %2 item(s) in your cart. Would you like to continue shopping or proceed to checkout?')
                            .replace('%1', product.name)
                            .replace('%2', totalQuantity), 'bot');
                        
                        // Update customer data section to refresh mini cart
                        customerData.invalidate(['cart']);
                        customerData.reload(['cart']);
                    }
                } else {
                    appendMessage($t('Sorry, I had trouble adding that to your cart. The product might not be available.'), 'bot');
                }
            }).fail(function (response) {
                removeTypingIndicator();
                console.error('Failed to add product to cart:', response);
                appendMessage($t('Sorry, there was an error adding the product to your cart. Please try again.'), 'bot');
            });
        }

        // Initiate checkout
        function initiateCheckout() {
            removeTypingIndicator();
            appendMessage($t('Great! Let me redirect you to checkout to complete your purchase.'), 'bot');
            
            setTimeout(function() {
                window.location.href = '/checkout';
            }, 2000);
        }

        // Remove typing indicator
        function removeTypingIndicator() {
            $('.chat-message.typing-indicator').remove();
        }

        // Append message to chat
        function appendMessage(message, sender, extraClass) {
            var messageClass = sender === 'user' ? 'user-message' : 'bot-message';
            if (extraClass) {
                messageClass += ' ' + extraClass;
            }
            
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