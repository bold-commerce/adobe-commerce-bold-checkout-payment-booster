# AI Shopping Assistant - Testing Notes

## Quick Setup Commands

```bash
# Clear cache after changes
warden shell -c "php bin/magento cache:clean"

# Check API key status
warden shell -c 'php -r "require \"/var/www/html/app/bootstrap.php\"; \$app = \Magento\Framework\App\Bootstrap::create(BP, \$_SERVER); \$om = \$app->getObjectManager(); \$deploymentConfig = \$om->get(\"Magento\Framework\App\DeploymentConfig\"); \$apiKey = \$deploymentConfig->get(\"bold_ai/gemini_api_key\"); echo \"API Key configured: \" . (\$apiKey ? \"YES (\" . strlen(\$apiKey) . \" chars)\" : \"NO\") . PHP_EOL;"'
```

## Backend API Testing

### Basic AI Chat Test
```bash
warden shell -c 'php -r "require \"/var/www/html/app/bootstrap.php\"; \$app = \Magento\Framework\App\Bootstrap::create(BP, \$_SERVER); \$om = \$app->getObjectManager(); \$ai = \$om->get(\"Bold\CheckoutPaymentBooster\Api\AiChatInterface\"); \$r1 = \$ai->processMessage(\"what products do you have?\"); echo \"Source: \" . \$r1->getSource() . \"\n\"; echo \"Response: \" . \$r1->getMessage() . \"\n\";"'
```

### Context Object Testing
```bash
# Test conversation with context
warden shell -c 'php -r "require \"/var/www/html/app/bootstrap.php\"; \$app = \Magento\Framework\App\Bootstrap::create(BP, \$_SERVER); \$om = \$app->getObjectManager(); \$ai = \$om->get(\"Bold\CheckoutPaymentBooster\Api\AiChatInterface\"); \$r1 = \$ai->processMessage(\"I need a bag for work\"); \$context = \$r1->getContext(); echo \"=== Message 1 ===\n\"; echo \"Source: \" . \$r1->getSource() . \"\n\"; echo \"Response: \" . substr(\$r1->getMessage(), 0, 100) . \"...\n\"; echo \"Context returned: \" . (is_array(\$context) ? \"YES\" : \"NO\") . \"\n\n\";"'

# Test follow-up with context
warden shell -c 'php -r "require \"/var/www/html/app/bootstrap.php\"; \$app = \Magento\Framework\App\Bootstrap::create(BP, \$_SERVER); \$om = \$app->getObjectManager(); \$ai = \$om->get(\"Bold\CheckoutPaymentBooster\Api\AiChatInterface\"); \$r1 = \$ai->processMessage(\"I need a bag for work\"); \$context = \$r1->getContext(); echo \"=== Follow-up with Context ===\n\"; \$r2 = \$ai->processMessage(\"What about something smaller?\", \$context); echo \"Source: \" . \$r2->getSource() . \"\n\"; echo \"Response: \" . substr(\$r2->getMessage(), 0, 150) . \"...\n\";"'
```

## Frontend Testing

### Chat Bubble Testing
1. **Open your Magento storefront**
2. **Verify chat bubble appears** in bottom right corner
3. **Click the chat bubble** to open chat window
4. **Test conversation flow**:
   - "Hello, I need a bag for work" 
   - "What about something smaller?"
   - "Can you add the shoulder pack to my cart?"
5. **Test clear button** (🗑️) to reset conversation
6. **Test close button** (×) to close chat

### Expected Responses
- **Natural conversational tone** (not JSON)
- **Context awareness** between messages
- **Product recommendations** mentioned naturally
- **Cart integration** working
- **Proper error handling** if API fails

## Log Monitoring

```bash
# Monitor AI chat logs in real-time
warden shell -c 'tail -f /var/www/html/var/log/system.log | grep "AI Chat"'
```

### Key Log Indicators
- `🔑 AI Chat - API Key status: FOUND`
- `🤖 AI Chat - Processing message`
- `✅ AI Chat - Gemini API success`
- `📋 Updated context from API`

## API Endpoints

### Process Message
```
POST /rest/V1/bold/ai-chat/message
Content-Type: application/json

{
  "message": "Hello, I need a bag for work",
  "context": null
}
```

### Response Format
```json
{
  "success": true,
  "message": "Hi there! Looking for a work bag?...",
  "source": "gemini",
  "context": {
    "products": [...],
    "conversation": [...],
    "prompt_config": {...},
    "cart_id": null
  }
}
```

## Architecture Notes

### Context Object Structure
```json
{
  "products": [
    {"sku": "24-MB01", "name": "Joust Duffle Bag", "price": "$34.00"},
    ...
  ],
  "conversation": [
    {"message": "...", "response": "...", "timestamp": 1234567890}
  ],
  "prompt_config": {
    "role": "You are a helpful shopping assistant...",
    "instructions": "Be friendly, helpful...",
    "max_history": 5
  },
  "cart_id": null
}
```

### Key Features
- **Stateless**: No server-side sessions
- **Context-driven**: Conversation history in context object
- **Conversational**: Natural language responses (not JSON)
- **Gemini Flash**: Fast AI model
- **Cart integration**: GraphQL mutations
- **Fallback**: Works without API key

## Troubleshooting

### Common Issues
1. **API Key not working**: Check `env.php` configuration
2. **Fallback responses**: Verify Gemini API key is valid
3. **Cache issues**: Run `php bin/magento cache:clean`
4. **Frontend not loading**: Check browser console for errors
5. **Context not persisting**: Verify context object in API responses

### Debug Commands
```bash
# Check deployment config
warden shell -c 'php -r "require \"/var/www/html/app/bootstrap.php\"; \$app = \Magento\Framework\App\Bootstrap::create(BP, \$_SERVER); \$om = \$app->getObjectManager(); \$config = \$om->get(\"Magento\Framework\App\DeploymentConfig\"); var_dump(\$config->get(\"bold_ai\"));"'

# Test API directly
curl -X POST "http://your-domain.test/rest/V1/bold/ai-chat/message" \
  -H "Content-Type: application/json" \
  -d '{"message":"test","context":null}'
```

## Known Limitations & Caveats

- **No True Cart Mutation on Agreement**: The AI can suggest adding products to the cart, but actual cart mutation only happens if the frontend detects agreement and triggers the GraphQL mutation. If the user says "yes" or "sure" in a backend-only test, the cart is not updated.
- **No Real-Time Inventory Awareness**: The AI only knows about the static list of products provided in the context. It does not check real-time inventory or product availability.
- **No User Authentication/Personalization**: The assistant does not personalize responses based on user account, order history, or preferences.
- **Limited Conversation Memory**: Only the last 5 exchanges are kept in the context object to avoid prompt bloat. Longer conversations may lose earlier context.
- **No Multi-Turn Cart Actions**: The AI cannot handle complex cart actions (e.g., "add two of those" or "remove the last item")—it only supports simple add-to-cart flows.
- **No Image or Rich Media Support**: All responses are plain text; the AI cannot show product images or rich content.
- **Frontend/Backend Context Sync**: If the frontend context is lost (e.g., page reload), the conversation history is reset.
- **Fallback Mode**: If the API key is missing or the Gemini API fails, the assistant uses a simple keyword-based fallback with limited intelligence.
- **Prompt Injection Risk**: Since user messages are included verbatim in the prompt, there is a theoretical risk of prompt injection (though mitigated by context and instructions).
- **No Language/Locale Adaptation**: The AI always responds in English and does not adapt to user locale or language.
- **No Product Search**: The AI cannot search the full Magento catalog; it only knows about the hardcoded product list in the context.
- **No Session Persistence**: Conversation context is not persisted server-side; it is passed in each API call from the frontend.