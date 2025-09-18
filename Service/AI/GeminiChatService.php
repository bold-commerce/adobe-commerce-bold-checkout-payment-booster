<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Service\AI;

use Bold\CheckoutPaymentBooster\Api\AI\ChatInterface;
use Bold\CheckoutPaymentBooster\Api\AI\Data\ChatMessageInterface;
use Bold\CheckoutPaymentBooster\Model\ChatMessage;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Gemini Chat Service with hardcoded products for MVP
 */
class GeminiChatService implements ChatInterface
{
    private const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';
    
    /**
     * Hardcoded product catalog for MVP demo
     */
    private const DEMO_PRODUCTS = [
        [
            'id' => 1,
            'name' => 'Wireless Bluetooth Headphones',
            'price' => 99.99,
            'description' => 'Premium noise-canceling wireless headphones with 30-hour battery life',
            'image' => '/media/catalog/product/headphones.jpg',
            'category' => 'Electronics'
        ],
        [
            'id' => 2,
            'name' => 'Smart Fitness Watch',
            'price' => 249.99,
            'description' => 'Advanced fitness tracking with heart rate monitor and GPS',
            'image' => '/media/catalog/product/smartwatch.jpg',
            'category' => 'Electronics'
        ],
        [
            'id' => 3,
            'name' => 'Organic Cotton T-Shirt',
            'price' => 29.99,
            'description' => 'Comfortable organic cotton t-shirt in various colors',
            'image' => '/media/catalog/product/tshirt.jpg',
            'category' => 'Clothing'
        ],
        [
            'id' => 4,
            'name' => 'Stainless Steel Water Bottle',
            'price' => 24.99,
            'description' => 'Insulated water bottle keeps drinks cold for 24 hours',
            'image' => '/media/catalog/product/bottle.jpg',
            'category' => 'Lifestyle'
        ]
    ];

    private Curl $curl;
    private Json $json;
    private LoggerInterface $logger;
    private array $sessions = [];

    public function __construct(
        Curl $curl,
        Json $json,
        LoggerInterface $logger
    ) {
        $this->curl = $curl;
        $this->json = $json;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function sendMessage(string $message, ?string $sessionId = null): ChatMessageInterface
    {
        if (!$sessionId) {
            $sessionId = $this->startSession();
        }

        // Add user message to session
        $userMessage = $this->createMessage($message, 'user', $sessionId);
        $this->addMessageToSession($sessionId, $userMessage);

        // Process message with Gemini and get response
        $aiResponse = $this->processWithGemini($message, $sessionId);
        
        // Create AI response message
        $responseMessage = $this->createMessage($aiResponse['message'], 'assistant', $sessionId);
        
        // Add products if detected in intent
        if (!empty($aiResponse['products'])) {
            $responseMessage->setProducts($aiResponse['products']);
        }
        
        if (!empty($aiResponse['intent'])) {
            $responseMessage->setIntent($aiResponse['intent']);
        }

        $this->addMessageToSession($sessionId, $responseMessage);

        return $responseMessage;
    }

    /**
     * @inheritDoc
     */
    public function getChatHistory(string $sessionId): array
    {
        return $this->sessions[$sessionId] ?? [];
    }

    /**
     * @inheritDoc
     */
    public function startSession(): string
    {
        $sessionId = uniqid('chat_', true);
        $this->sessions[$sessionId] = [];
        
        // Add welcome message
        $welcomeMessage = $this->createMessage(
            "Hello! I'm your shopping assistant. I can help you find products and complete your purchase. What are you looking for today?",
            'assistant',
            $sessionId
        );
        $this->addMessageToSession($sessionId, $welcomeMessage);
        
        return $sessionId;
    }

    /**
     * Process message with Gemini API
     */
    private function processWithGemini(string $message, string $sessionId): array
    {
        // For MVP, we'll use simple keyword matching instead of actual Gemini API
        // This allows us to test the flow without API dependencies
        $intent = $this->detectIntent($message);
        $products = $this->getRelevantProducts($message);
        $responseText = $this->generateResponse($message, $intent, $products);

        return [
            'message' => $responseText,
            'intent' => $intent,
            'products' => $products
        ];
    }

    /**
     * Detect user intent from message
     */
    private function detectIntent(string $message): string
    {
        $message = strtolower($message);
        
        if (strpos($message, 'checkout') !== false || strpos($message, 'buy') !== false || strpos($message, 'purchase') !== false) {
            return 'checkout';
        }
        
        if (strpos($message, 'headphone') !== false || strpos($message, 'audio') !== false) {
            return 'product_search';
        }
        
        if (strpos($message, 'watch') !== false || strpos($message, 'fitness') !== false) {
            return 'product_search';
        }
        
        if (strpos($message, 'shirt') !== false || strpos($message, 'clothing') !== false) {
            return 'product_search';
        }
        
        if (strpos($message, 'bottle') !== false || strpos($message, 'water') !== false) {
            return 'product_search';
        }

        return 'general';
    }

    /**
     * Get relevant products based on message
     */
    private function getRelevantProducts(string $message): array
    {
        $message = strtolower($message);
        $relevantProducts = [];

        foreach (self::DEMO_PRODUCTS as $product) {
            $productName = strtolower($product['name']);
            $productCategory = strtolower($product['category']);
            
            if (strpos($message, 'headphone') !== false && strpos($productName, 'headphone') !== false) {
                $relevantProducts[] = $product;
            } elseif (strpos($message, 'watch') !== false && strpos($productName, 'watch') !== false) {
                $relevantProducts[] = $product;
            } elseif (strpos($message, 'shirt') !== false && strpos($productName, 'shirt') !== false) {
                $relevantProducts[] = $product;
            } elseif (strpos($message, 'bottle') !== false && strpos($productName, 'bottle') !== false) {
                $relevantProducts[] = $product;
            } elseif (strpos($message, $productCategory) !== false) {
                $relevantProducts[] = $product;
            }
        }

        // If no specific products found, return all for general browsing
        if (empty($relevantProducts) && (strpos($message, 'show') !== false || strpos($message, 'browse') !== false)) {
            $relevantProducts = self::DEMO_PRODUCTS;
        }

        return $relevantProducts;
    }

    /**
     * Generate AI response based on intent and products
     */
    private function generateResponse(string $message, string $intent, array $products): string
    {
        switch ($intent) {
            case 'product_search':
                if (!empty($products)) {
                    $productNames = array_column($products, 'name');
                    return "I found some great options for you! Here are " . count($products) . " products that match your search: " . implode(', ', $productNames) . ". Would you like to see more details about any of these?";
                } else {
                    return "I couldn't find any products matching your search. Let me show you our popular items instead!";
                }
                
            case 'checkout':
                return "Great! I can help you complete your purchase. Let me initialize your order and we'll get you checked out quickly.";
                
            case 'general':
            default:
                return "I'm here to help you find the perfect products! You can ask me about headphones, watches, clothing, or lifestyle products. What interests you today?";
        }
    }

    /**
     * Create a chat message
     */
    private function createMessage(string $message, string $role, string $sessionId): ChatMessageInterface
    {
        $chatMessage = new ChatMessage();
        $chatMessage->setMessage($message);
        $chatMessage->setRole($role);
        $chatMessage->setSessionId($sessionId);
        $chatMessage->setTimestamp(date('Y-m-d H:i:s'));
        
        return $chatMessage;
    }

    /**
     * Add message to session history
     */
    private function addMessageToSession(string $sessionId, ChatMessageInterface $message): void
    {
        if (!isset($this->sessions[$sessionId])) {
            $this->sessions[$sessionId] = [];
        }
        
        $this->sessions[$sessionId][] = $message;
    }
} 