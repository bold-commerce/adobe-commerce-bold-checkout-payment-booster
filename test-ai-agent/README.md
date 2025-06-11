# AI Agent Test Environment

This folder contains a standalone test environment for the Bold Checkout Payment Booster AI Agent.

## Quick Start

1. **Start the test server:**
   ```bash
   cd test-ai-agent
   php -S localhost:8000
   ```

2. **Open your browser:**
   Navigate to: http://localhost:8000

3. **Test the AI agent:**
   Try these example messages:
   - "Show me headphones" 🎧
   - "I need a fitness watch" ⌚
   - "Looking for clothing" 👕
   - "Show me a water bottle" 🍶
   - "I want to buy something" 🛒

## Files

- `index.php` - Main test page with chat interface
- `api.php` - Simple API backend for chat functionality
- `README.md` - This file

## Features

✅ **Working Features:**
- Real-time chat interface
- Keyword-based intent detection
- Hardcoded product recommendations
- Product display with prices and descriptions
- Session management
- Responsive design

🔄 **Next Steps:**
- Integrate with actual Gemini API
- Connect to Bold Checkout APIs for real checkout flow
- Add more sophisticated product matching
- Implement actual order processing

## Architecture

This test environment simulates the AI agent functionality without requiring a full Magento setup:

```
Browser → index.php (UI) → api.php (Logic) → Hardcoded Products
```

The actual implementation will integrate with:
```
Browser → Magento Frontend → Bold Payment Booster Module → Bold APIs
```

## Development Notes

- Uses simple keyword matching instead of AI for MVP testing
- Products are hardcoded in `api.php`
- No database required - everything runs in memory
- CORS headers included for development
- Error handling and logging included 