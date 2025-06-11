<?php
/**
 * Simple router for AI Agent test
 */

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve the test HTML page
if ($uri === '/' || $uri === '/index.php') {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Agent Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }
        .chat-container {
            border: 1px solid #ddd;
            height: 400px;
            overflow-y: auto;
            padding: 15px;
            margin-bottom: 15px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .message {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 8px;
            max-width: 80%;
        }
        .user-message {
            background-color: #007bff;
            color: white;
            margin-left: auto;
            text-align: right;
        }
        .assistant-message {
            background-color: #e9ecef;
            color: #333;
            margin-right: auto;
        }
        .input-container {
            display: flex;
            gap: 10px;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        #messageInput {
            flex: 1;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 25px;
            outline: none;
            font-size: 16px;
        }
        #messageInput:focus {
            border-color: #007bff;
        }
        #sendButton {
            padding: 12px 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            transition: transform 0.2s;
        }
        #sendButton:hover {
            transform: translateY(-2px);
        }
        .products {
            margin-top: 15px;
            padding: 15px;
            background: linear-gradient(135deg, #ffeaa7 0%, #fab1a0 100%);
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .product {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .loading {
            color: #666;
            font-style: italic;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .examples {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .examples h3 {
            color: #333;
            margin-top: 0;
        }
        .examples ul {
            list-style-type: none;
            padding: 0;
        }
        .examples li {
            background: #f8f9fa;
            margin: 5px 0;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .examples li:hover {
            background: #e9ecef;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🤖 AI Shopping Agent Test</h1>
        <p>Testing Bold Checkout Payment Booster AI Integration</p>
    </div>
    
    <div class="examples">
        <h3>💡 Try these example messages:</h3>
        <ul>
            <li onclick="sendExampleMessage('Show me headphones')">🎧 "Show me headphones"</li>
            <li onclick="sendExampleMessage('I need a fitness watch')">⌚ "I need a fitness watch"</li>
            <li onclick="sendExampleMessage('Looking for clothing')">👕 "Looking for clothing"</li>
            <li onclick="sendExampleMessage('Show me a water bottle')">🍶 "Show me a water bottle"</li>
            <li onclick="sendExampleMessage('I want to buy something')">🛒 "I want to buy something"</li>
        </ul>
    </div>
    
    <div class="chat-container" id="chatContainer">
        <!-- Messages will appear here -->
    </div>
    
    <div class="input-container">
        <input type="text" id="messageInput" placeholder="Type your message here..." />
        <button id="sendButton">Send</button>
    </div>

    <script>
        let sessionId = null;
        const chatContainer = document.getElementById('chatContainer');
        const messageInput = document.getElementById('messageInput');
        const sendButton = document.getElementById('sendButton');

        // Initialize chat session
        async function initializeChat() {
            addMessage('AI Agent', 'Hello! I\'m your shopping assistant. I can help you find products and complete your purchase. What are you looking for today?', 'assistant');
            sessionId = 'demo_session_' + Date.now();
        }

        // Send example message
        function sendExampleMessage(message) {
            messageInput.value = message;
            sendMessage();
        }

        // Send message to AI agent
        async function sendMessage() {
            const message = messageInput.value.trim();
            if (!message) return;

            // Add user message to chat
            addMessage('You', message, 'user');
            messageInput.value = '';

            // Show loading
            const loadingDiv = addMessage('AI Agent', 'Thinking...', 'assistant', true);

            try {
                const response = await fetch('/api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'chat',
                        message: message,
                        session_id: sessionId
                    })
                });

                const data = await response.json();
                
                // Remove loading message
                loadingDiv.remove();

                if (data.success) {
                    addMessage('AI Agent', data.message, 'assistant');
                    
                    // Show products if any
                    if (data.products && data.products.length > 0) {
                        showProducts(data.products);
                    }
                } else {
                    addMessage('AI Agent', 'Error: ' + (data.error || 'Unknown error'), 'assistant');
                }
            } catch (error) {
                loadingDiv.remove();
                console.error('Error sending message:', error);
                addMessage('AI Agent', 'Sorry, I encountered an error. Please try again.', 'assistant');
            }
        }

        // Add message to chat container
        function addMessage(sender, message, type, isLoading = false) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}-message`;
            if (isLoading) {
                messageDiv.className += ' loading';
            }
            
            messageDiv.innerHTML = `<strong>${sender}:</strong> ${message}`;
            chatContainer.appendChild(messageDiv);
            chatContainer.scrollTop = chatContainer.scrollHeight;
            
            return messageDiv;
        }

        // Show products
        function showProducts(products) {
            const productsDiv = document.createElement('div');
            productsDiv.className = 'products';
            productsDiv.innerHTML = '<strong>🛍️ Products Found:</strong>';
            
            products.forEach(product => {
                const productDiv = document.createElement('div');
                productDiv.className = 'product';
                productDiv.innerHTML = `
                    <strong>${product.name}</strong> - <span style="color: #28a745; font-weight: bold;">$${product.price}</span><br>
                    <small style="color: #666;">${product.description}</small>
                `;
                productsDiv.appendChild(productDiv);
            });
            
            chatContainer.appendChild(productsDiv);
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        // Event listeners
        sendButton.addEventListener('click', sendMessage);
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });

        // Initialize chat when page loads
        window.addEventListener('load', initializeChat);
    </script>
</body>
</html>
    <?php
    exit;
}

// Handle API requests
if ($uri === '/api.php') {
    include 'api.php';
    exit;
}

// 404 for other routes
http_response_code(404);
echo "Not Found";
?> 