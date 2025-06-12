<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Test\Integration\AI\Service;

use Bold\CheckoutPaymentBooster\Api\AI\ChatInterface;
use Bold\CheckoutPaymentBooster\Api\AI\Data\ChatMessageInterface;
use Bold\CheckoutPaymentBooster\Service\AI\GeminiChatService;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for GeminiChatService
 */
class GeminiChatServiceTest extends TestCase
{
    private ChatInterface $chatService;
    private ObjectManagerInterface $objectManager;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->chatService = $this->objectManager->get(ChatInterface::class);
    }

    /**
     * Test that chat service is properly configured
     */
    public function testChatServiceIsProperlyConfigured(): void
    {
        $this->assertInstanceOf(GeminiChatService::class, $this->chatService);
    }

    /**
     * Test starting a new chat session
     */
    public function testStartSession(): void
    {
        $sessionId = $this->chatService->startSession();
        
        $this->assertNotEmpty($sessionId);
        $this->assertStringStartsWith('chat_', $sessionId);
        
        // Test that session has welcome message
        $history = $this->chatService->getChatHistory($sessionId);
        $this->assertCount(1, $history);
        $this->assertEquals('assistant', $history[0]->getRole());
        $this->assertStringContainsString('Hello!', $history[0]->getMessage());
    }

    /**
     * Test sending a message and getting response
     */
    public function testSendMessage(): void
    {
        $sessionId = $this->chatService->startSession();
        $message = "Hello, show me some headphones";
        
        $response = $this->chatService->sendMessage($message, $sessionId);
        
        $this->assertInstanceOf(ChatMessageInterface::class, $response);
        $this->assertEquals('assistant', $response->getRole());
        $this->assertEquals($sessionId, $response->getSessionId());
        $this->assertNotEmpty($response->getMessage());
        $this->assertNotEmpty($response->getTimestamp());
    }

    /**
     * Test product search intent detection
     */
    public function testProductSearchIntentDetection(): void
    {
        $sessionId = $this->chatService->startSession();
        
        $testCases = [
            'headphones' => 'product_search',
            'show me some watches' => 'product_search',
            'I need a shirt' => 'product_search',
            'water bottle' => 'product_search',
        ];
        
        foreach ($testCases as $message => $expectedIntent) {
            $response = $this->chatService->sendMessage($message, $sessionId);
            $this->assertEquals($expectedIntent, $response->getIntent(), "Failed for message: $message");
        }
    }

    /**
     * Test checkout intent detection
     */
    public function testCheckoutIntentDetection(): void
    {
        $sessionId = $this->chatService->startSession();
        
        $testCases = [
            'I want to checkout',
            'Let me buy this',
            'How do I purchase this?',
        ];
        
        foreach ($testCases as $message) {
            $response = $this->chatService->sendMessage($message, $sessionId);
            $this->assertEquals('checkout', $response->getIntent(), "Failed for message: $message");
        }
    }

    /**
     * Test product recommendations
     */
    public function testProductRecommendations(): void
    {
        $sessionId = $this->chatService->startSession();
        
        // Test headphones search
        $response = $this->chatService->sendMessage('show me headphones', $sessionId);
        $products = $response->getProducts();
        
        $this->assertNotEmpty($products);
        $this->assertEquals('Wireless Bluetooth Headphones', $products[0]['name']);
        $this->assertEquals(99.99, $products[0]['price']);
        $this->assertEquals('Electronics', $products[0]['category']);
    }

    /**
     * Test chat history functionality
     */
    public function testChatHistory(): void
    {
        $sessionId = $this->chatService->startSession();
        
        // Send multiple messages
        $this->chatService->sendMessage('Hello', $sessionId);
        $this->chatService->sendMessage('Show me headphones', $sessionId);
        
        $history = $this->chatService->getChatHistory($sessionId);
        
        // Should have: welcome + user message + ai response + user message + ai response = 5 messages
        $this->assertCount(5, $history);
        
        // Check message order and roles
        $this->assertEquals('assistant', $history[0]->getRole()); // Welcome
        $this->assertEquals('user', $history[1]->getRole());      // Hello
        $this->assertEquals('assistant', $history[2]->getRole()); // Response to Hello
        $this->assertEquals('user', $history[3]->getRole());      // Show me headphones
        $this->assertEquals('assistant', $history[4]->getRole()); // Response to headphones
    }

    /**
     * Test multiple sessions are independent
     */
    public function testMultipleSessionsAreIndependent(): void
    {
        $sessionId1 = $this->chatService->startSession();
        $sessionId2 = $this->chatService->startSession();
        
        $this->assertNotEquals($sessionId1, $sessionId2);
        
        // Send different messages to each session
        $this->chatService->sendMessage('Hello from session 1', $sessionId1);
        $this->chatService->sendMessage('Hello from session 2', $sessionId2);
        
        $history1 = $this->chatService->getChatHistory($sessionId1);
        $history2 = $this->chatService->getChatHistory($sessionId2);
        
        // Each should have welcome + user + assistant messages
        $this->assertCount(3, $history1);
        $this->assertCount(3, $history2);
        
        // Messages should be different
        $this->assertStringContainsString('session 1', $history1[1]->getMessage());
        $this->assertStringContainsString('session 2', $history2[1]->getMessage());
    }

    /**
     * Test sending message without session creates new session
     */
    public function testSendMessageWithoutSessionCreatesNewSession(): void
    {
        $response = $this->chatService->sendMessage('Hello');
        
        $this->assertNotEmpty($response->getSessionId());
        $this->assertStringStartsWith('chat_', $response->getSessionId());
        
        // Should have history for the new session
        $history = $this->chatService->getChatHistory($response->getSessionId());
        $this->assertGreaterThan(0, count($history));
    }

    /**
     * Data provider for intent detection tests
     * 
     * @return array
     */
    public function intentDetectionDataProvider(): array
    {
        return [
            'general greeting' => ['Hello there', 'general'],
            'product search - headphones' => ['I need headphones', 'product_search'],
            'product search - watch' => ['fitness watch', 'product_search'],
            'product search - clothing' => ['show me shirts', 'product_search'],
            'checkout intent' => ['ready to checkout', 'checkout'],
            'purchase intent' => ['I want to buy this', 'checkout'],
            'general question' => ['What do you offer?', 'general'],
        ];
    }

    /**
     * Test intent detection with data provider
     * 
     * @dataProvider intentDetectionDataProvider
     */
    public function testIntentDetectionWithDataProvider(string $message, string $expectedIntent): void
    {
        $sessionId = $this->chatService->startSession();
        $response = $this->chatService->sendMessage($message, $sessionId);
        
        $this->assertEquals($expectedIntent, $response->getIntent());
    }
} 