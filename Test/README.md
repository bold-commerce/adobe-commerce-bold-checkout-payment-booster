# Bold CheckoutPaymentBooster Test Suite

This directory contains comprehensive tests for the AI chatbot and rate limiting functionality.

## Test Structure

```
Test/
├── Integration/
│   ├── AI/
│   │   ├── Service/
│   │   │   └── GeminiChatServiceTest.php
│   │   └── ChatHappyPathTest.php ⭐ **Sales Demo**
│   ├── Plugin/
│   │   └── GraphQl/
│   │       └── AddToCartPluginTest.php
│   └── Service/
│       └── RateLimiterTest.php
└── README.md
```

## Test Coverage

### 1. RateLimiterTest.php
**Tests**: `Bold\CheckoutPaymentBooster\Service\RateLimiter`

**Test Cases**:
- ✅ `testAllowsRequestsWithinLimit()` - Verifies requests are allowed within rate limit
- ✅ `testBlocksRequestsExceedingLimit()` - Ensures rate limiting blocks excessive requests
- ✅ `testDifferentKeysHaveSeparateLimits()` - Confirms different IP addresses have separate limits
- ✅ `testDefaultLimitAndWindow()` - Tests default configuration (10 requests/60 seconds)
- ✅ `testCustomLimitAndWindow()` - Tests custom rate limit configurations
- ✅ `testCacheKeyGeneration()` - Verifies proper cache key generation and storage

**Key Features Tested**:
- Rate limiting enforcement
- Cache-based storage
- IP-based key generation
- Exception handling for limit exceeded
- Custom limit/window configurations

### 2. GeminiChatServiceTest.php
**Tests**: `Bold\CheckoutPaymentBooster\AI\Service\GeminiChatService`

**Test Cases**:
- ✅ `testChatServiceIsProperlyConfigured()` - DI configuration validation
- ✅ `testStartSession()` - Session creation and welcome message
- ✅ `testSendMessage()` - Basic message sending and response
- ✅ `testProductSearchIntentDetection()` - Intent detection for product searches
- ✅ `testCheckoutIntentDetection()` - Intent detection for checkout flows
- ✅ `testProductRecommendations()` - Product matching and recommendations
- ✅ `testChatHistory()` - Conversation history tracking
- ✅ `testMultipleSessionsAreIndependent()` - Session isolation
- ✅ `testSendMessageWithoutSessionCreatesNewSession()` - Auto-session creation
- ✅ `testIntentDetectionWithDataProvider()` - Comprehensive intent testing

**Key Features Tested**:
- Session management
- Intent detection (product_search, checkout, general)
- Product recommendations
- Chat history tracking
- Message handling
- Session isolation

### 3. AddToCartPluginTest.php
**Tests**: `Bold\CheckoutPaymentBooster\Plugin\GraphQl\AddToCartPlugin`

**Test Cases**:
- ✅ `testPluginIsProperlyInstantiated()` - Plugin DI configuration
- ✅ `testSuccessfulResolverCallWithinRateLimit()` - Normal operation within limits
- ✅ `testRateLimitingBlocksRequestsWhenLimitExceeded()` - Rate limit enforcement
- ✅ `testPluginLogsRequestsProperly()` - Request/response logging
- ✅ `testPluginLogsRateLimitWarnings()` - Rate limit warning logs
- ✅ `testPluginHandlesUnknownIpAddressGracefully()` - Null IP handling
- ✅ `testPluginPreservesOriginalResolverArguments()` - Argument preservation
- ✅ `testPluginWorksWithDifferentCartItemConfigurations()` - Various cart scenarios

**Key Features Tested**:
- GraphQL resolver interception
- Rate limiting integration
- Comprehensive logging
- Error handling
- Argument preservation
- Multiple cart item scenarios

### 4. ChatHappyPathTest.php ⭐ **Sales Demo Test**
**Tests**: Complete AI Agent workflow following CHK-8730 architecture

**Test Cases**:
- ✅ `testAiAgentHappyPathWorkflow()` - Complete 20-step workflow simulation
- ✅ `testConversationFlowMatchesArchitecture()` - Intent validation
- ✅ `testApiEndpointsAvailability()` - API endpoint documentation

**20-Step Workflow Simulation**:
1. User loads website
2. Agent conversation starts & loads products
3. User selects products through chat
4. Agent prompts for checkout
5. User confirms checkout
6. Agent initializes order
7. Returns order ID and auth token
8. Agent prompts for wallet payment
9. User declines wallet payment
10. Agent prompts for address selection
11. User selects address
12. Agent adds address to order
13. System returns shipping options
14. Agent presents shipping options
15. User selects shipping option
16. Agent sets shipping line on order
17. Agent confirms totals with user
18. User confirms final order
19. Agent prepares for payment (EPS config)
20. Ready for payment processing

**Key Features Tested**:
- Complete conversation workflow
- Product discovery and selection
- Order/cart management
- Address and shipping handling
- Payment preparation (stops before processing)
- API integration points
- Sales demonstration readiness

## Running Tests

### Prerequisites
1. Magento 2 environment with test database configured
2. Integration test setup completed
3. Module installed and enabled

### Command Line Execution

**Run All Integration Tests**:
```bash
cd dev/tests/integration
php -f ../../../vendor/bin/phpunit -- --configuration phpunit.xml.dist ../../../app/code/Bold/CheckoutPaymentBooster/Test/Integration/
```

**Run Individual Test Files**:
```bash
# Rate Limiter Tests
php -f ../../../vendor/bin/phpunit -- --configuration phpunit.xml.dist ../../../app/code/Bold/CheckoutPaymentBooster/Test/Integration/Service/RateLimiterTest.php

# AI Chat Service Tests
php -f ../../../vendor/bin/phpunit -- --configuration phpunit.xml.dist ../../../app/code/Bold/CheckoutPaymentBooster/Test/Integration/AI/Service/GeminiChatServiceTest.php

# GraphQL Plugin Tests
php -f ../../../vendor/bin/phpunit -- --configuration phpunit.xml.dist ../../../app/code/Bold/CheckoutPaymentBooster/Test/Integration/Plugin/GraphQl/AddToCartPluginTest.php
```

**Run Specific Test Methods**:
```bash
php -f ../../../vendor/bin/phpunit -- --configuration phpunit.xml.dist --filter testRateLimitingBlocksRequestsWhenLimitExceeded ../../../app/code/Bold/CheckoutPaymentBooster/Test/Integration/Plugin/GraphQl/AddToCartPluginTest.php
```

## Test Environment Setup

### Database Requirements
- MySQL/MariaDB test database
- Proper connection configuration in `dev/tests/integration/etc/install-config-mysql.php`

### Dependencies
- PHPUnit 9.x
- Magento Test Framework
- Module dependencies properly installed

### Configuration Files
Tests use Magento's integration test framework with:
- `dev/tests/integration/phpunit.xml.dist`
- `dev/tests/integration/etc/install-config-mysql.php`

## Sales Demo Test ⭐

The `ChatHappyPathTest.php` is specifically designed for **sales demonstrations** and follows the exact workflow defined in `CHK-8730-agent-arch.md`. 

### Quick Demo Run
```bash
cd dev/tests/integration
php -f ../../../vendor/bin/phpunit -- --configuration phpunit.xml.dist --filter testAiAgentHappyPathWorkflow ../../../app/code/Bold/CheckoutPaymentBooster/Test/Integration/AI/ChatHappyPathTest.php
```

This test demonstrates:
- ✅ **Complete customer journey** from product discovery to payment ready
- ✅ **AI conversation flow** with intent detection
- ✅ **Order management** with cart, address, and shipping
- ✅ **Payment preparation** (stops before processing for safety)
- ✅ **API integration points** for Bold commerce services

Perfect for demonstrating the AI agent capabilities to potential customers!

## Risk-Based Testing Strategy

These tests focus on **high-risk areas** identified in the AI chatbot architecture:

### Critical Risk Areas (100% Coverage)
1. **Rate Limiting** - Prevents system abuse
2. **GraphQL Interception** - Ensures proper plugin functionality
3. **Session Management** - Critical for chat functionality
4. **Intent Detection** - Core AI functionality
5. **Error Handling** - System stability

### Medium Risk Areas (Tested)
- Product recommendations
- Logging functionality
- Cache operations
- Multi-session handling

### Low Risk Areas (Basic Coverage)
- Static product catalog
- Message formatting
- Timestamp generation

## Continuous Integration

These tests are designed to be integrated into CI/CD pipelines:

```yaml
# Example GitHub Actions step
- name: Run AI Chatbot Integration Tests
  run: |
    cd dev/tests/integration
    php -f ../../../vendor/bin/phpunit -- --configuration phpunit.xml.dist ../../../app/code/Bold/CheckoutPaymentBooster/Test/Integration/
```

## Test Data & Fixtures

Tests use:
- **Mocked dependencies** for isolation
- **Unique test keys** to prevent conflicts
- **Data providers** for comprehensive scenario coverage
- **Proper cleanup** in tearDown methods

## Troubleshooting
   - Integration tests can be memory intensive
   - Increase PHP memory limit if needed

## Maintenance

- **Add new test cases** when new features are added
- **Update data providers** when business logic changes
- **Maintain test independence** - each test should be isolated
- **Regular test execution** in CI/CD pipeline

## Documentation

- Each test method includes comprehensive docblocks
- Data providers document test scenarios
- Assertions include descriptive failure messages
- Test structure follows Magento conventions 