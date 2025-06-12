<?php

namespace Bold\CheckoutPaymentBooster\Model;

use Bold\CheckoutPaymentBooster\Api\AiChatInterface;
use Bold\CheckoutPaymentBooster\Api\Data\AiChatResponseInterface;
use Bold\CheckoutPaymentBooster\Api\Data\AiChatResponseInterfaceFactory;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\App\DeploymentConfig;
use Psr\Log\LoggerInterface;

class AiChat implements AiChatInterface
{
    private const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
    private const DEFAULT_API_KEY = null; // No hardcoded key - use environment variables

    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @var AiChatResponseInterfaceFactory
     */
    private $responseFactory;

    /**
     * @param Curl $curl
     * @param LoggerInterface $logger
     * @param DeploymentConfig $deploymentConfig
     * @param AiChatResponseInterfaceFactory $responseFactory
     */
    public function __construct(
        Curl $curl,
        LoggerInterface $logger,
        DeploymentConfig $deploymentConfig,
        AiChatResponseInterfaceFactory $responseFactory
    ) {
        $this->curl = $curl;
        $this->logger = $logger;
        $this->deploymentConfig = $deploymentConfig;
        $this->responseFactory = $responseFactory;
    }

    /**
     * Process AI chat message with context
     *
     * @param string $message
     * @param array|null $context
     * @return AiChatResponseInterface
     */
    public function processMessage(string $message, ?array $context = null): \Bold\CheckoutPaymentBooster\Api\Data\AiChatResponseInterface
    {
        // Initialize or update context
        $context = $this->initializeContext($context);
        
        try {
            $apiKey = $this->getGeminiApiKey();
            
            $this->logger->info('🔑 AI Chat - API Key status: ' . ($apiKey ? 'FOUND (' . strlen($apiKey) . ' chars)' : 'NOT FOUND'));
            
            if (!$apiKey) {
                $this->logger->info('❌ AI Chat - No API key, using fallback');
                return $this->createFallbackResponse($message, $context);
            }
            
            $this->logger->info('🤖 AI Chat - Processing message: ' . substr($message, 0, 50) . '...');
            $prompt = $this->buildPromptFromContext($message, $context);
            $this->logger->info('📝 AI Chat - Built prompt, calling Gemini API...');
            
            $response = $this->callGeminiApi($apiKey, $prompt);
            
            if ($response) {
                $this->logger->info('✅ AI Chat - Gemini API success, response length: ' . strlen($response));
                
                // Update context with new conversation
                $updatedContext = $this->updateContext($context, $message, $response);
                
                return $this->responseFactory->create()
                    ->setSuccess(true)
                    ->setMessage($response)
                    ->setSource('gemini')
                    ->setContext($updatedContext);
            } else {
                $this->logger->warning('⚠️ AI Chat - Gemini API returned empty response');
            }
        } catch (\Exception $e) {
            $this->logger->error('❌ AI Chat - Exception: ' . $e->getMessage());
            $this->logger->error('❌ AI Chat - Stack trace: ' . $e->getTraceAsString());
        }

        // Fallback response
        return $this->createFallbackResponse($message, $context);
    }

    /**
     * Get Gemini API key from environment configuration
     *
     * @return string
     */
    private function getGeminiApiKey(): ?string
    {
        // Try to get from env.php first, fallback to environment variable, then default
        $apiKey = $this->deploymentConfig->get('bold_ai/gemini_api_key') 
            ?? getenv('BOLD_GEMINI_API_KEY') 
            ?? self::DEFAULT_API_KEY;

        return $apiKey;
    }

    /**
     * Initialize context object
     *
     * @param array|null $context
     * @return array
     */
    private function initializeContext(?array $context = null): array
    {
        if ($context === null) {
            return [
                'products' => [
                    ['sku' => '24-MB01', 'name' => 'Joust Duffle Bag', 'price' => '$34.00'],
                    ['sku' => '24-MB03', 'name' => 'Crown Summit Backpack', 'price' => '$38.00'],
                    ['sku' => '24-MB05', 'name' => 'Wayfarer Messenger Bag', 'price' => '$45.00'],
                    ['sku' => '24-WB02', 'name' => 'Compete Track Tote', 'price' => '$33.00'],
                    ['sku' => '24-MB04', 'name' => 'Strive Shoulder Pack', 'price' => '$32.00'],
                    ['sku' => '24-WB01', 'name' => 'Voyage Yoga Bag', 'price' => '$32.00']
                ],
                'conversation' => [],
                'prompt_config' => [
                    'role' => 'You are a helpful shopping AI assistant for an online store.',
                    'instructions' => 'Be friendly, helpful, and conversational. Respond naturally and pay attention to what the customer just said. When they ask about a specific product, tell them about it and offer to add it to their cart. If they agree (yes, sure, okay, etc.), acknowledge that you\'ll add it to their cart. Avoid repeating the same questions. Do not pretend to be a real human store worker, but be as helpful as possible.',
                    'max_history' => 5
                ],
                'cart_id' => null
            ];
        }
        
        return $context;
    }

    /**
     * Build prompt from context object
     *
     * @param string $message
     * @param array $context
     * @return string
     */
    private function buildPromptFromContext(string $message, array $context): string
    {
        $prompt = $context['prompt_config']['role'] . "\n\n";
        
        // Add available products
        $prompt .= "Available products:\n";
        foreach ($context['products'] as $product) {
            $prompt .= "- {$product['name']} ({$product['sku']}) - {$product['price']}\n";
        }
        
        $prompt .= "\n" . $context['prompt_config']['instructions'] . "\n";
        
        if ($context['cart_id']) {
            $prompt .= "The customer has an active shopping cart. ";
        }

        // Add conversation history
        if (!empty($context['conversation'])) {
            $prompt .= "\nConversation history:\n";
            foreach ($context['conversation'] as $entry) {
                $prompt .= "Customer: {$entry['message']}\n";
                $prompt .= "Assistant: {$entry['response']}\n";
            }
        }

        $prompt .= "\nCurrent customer message: $message\n";
        $prompt .= "\nRespond naturally based on what the customer just said and the conversation so far. If they say 'sure', 'yes', 'okay' or similar agreement words, they're likely agreeing to what you just offered. If they're asking for something specific, focus on that. Don't repeat previous questions unnecessarily:";

        return $prompt;
    }

    /**
     * Update context with new conversation
     *
     * @param array $context
     * @param string $message
     * @param string $response
     * @return array
     */
    private function updateContext(array $context, string $message, string $response): array
    {
        // Add to conversation history
        $context['conversation'][] = [
            'message' => $message,
            'response' => $response,
            'timestamp' => time()
        ];
        
        // Keep only last N exchanges
        $maxHistory = $context['prompt_config']['max_history'] ?? 5;
        if (count($context['conversation']) > $maxHistory) {
            $context['conversation'] = array_slice($context['conversation'], -$maxHistory);
        }
        
        return $context;
    }

    /**
     * Create fallback response with updated context
     *
     * @param string $message
     * @param array $context
     * @return AiChatResponseInterface
     */
    private function createFallbackResponse(string $message, array $context): \Bold\CheckoutPaymentBooster\Api\Data\AiChatResponseInterface
    {
        $fallbackMessage = $this->generateFallbackResponse($message);
        $updatedContext = $this->updateContext($context, $message, $fallbackMessage);
        
        return $this->responseFactory->create()
            ->setSuccess(true)
            ->setMessage($fallbackMessage)
            ->setSource('fallback')
            ->setContext($updatedContext);
    }

    /**
     * Call Gemini API
     *
     * @param string $apiKey
     * @param string $prompt
     * @return string|null
     */
    private function callGeminiApi(string $apiKey, string $prompt): ?string
    {
        $url = self::GEMINI_API_URL . '?key=' . $apiKey;

        $requestData = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'text' => $prompt
                        ]
                    ]
                ]
            ]
        ];

        $this->logger->info('🌐 AI Chat - Calling URL: ' . $url);
        $this->logger->info('📤 AI Chat - Request data: ' . json_encode($requestData));

        $this->curl->setOption(CURLOPT_TIMEOUT, 30);
        $this->curl->addHeader('Content-Type', 'application/json');
        $this->curl->addHeader('x-goog-api-key', $apiKey);
        $this->curl->post($url, json_encode($requestData));

        $response = $this->curl->getBody();
        $httpCode = $this->curl->getStatus();

        $this->logger->info('📥 AI Chat - HTTP Code: ' . $httpCode);
        $this->logger->info('📥 AI Chat - Response: ' . substr($response, 0, 200) . '...');

        if ($httpCode !== 200) {
            $this->logger->error('❌ AI Chat - HTTP Error: ' . $httpCode . ' - ' . $response);
            return null;
        }

        $responseData = json_decode($response, true);

        if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
            $aiResponse = trim($responseData['candidates'][0]['content']['parts'][0]['text']);
            $this->logger->info('✅ AI Chat - Extracted response: ' . substr($aiResponse, 0, 100) . '...');
            return $aiResponse;
        }

        $this->logger->error('❌ AI Chat - Unexpected response format: ' . $response);
        return null;
    }

    /**
     * Generate fallback response when API is unavailable
     *
     * @param string $message
     * @return string
     */
    private function generateFallbackResponse(string $message): string
    {
        $lowerMessage = strtolower($message);

        if (strpos($lowerMessage, 'hello') !== false || strpos($lowerMessage, 'hi') !== false) {
            return 'Hello! I can help you find and add products to your cart. We have backpacks, messenger bags, totes, and duffle bags available.';
        } elseif (strpos($lowerMessage, 'help') !== false) {
            return 'I can help you with: finding products, adding items to cart, and starting checkout. Try asking "show me backpacks" or "add tote to cart".';
        } elseif (strpos($lowerMessage, 'price') !== false) {
            return 'Our products range from $32.00 to $45.00. Would you like to see specific product prices?';
        } elseif (strpos($lowerMessage, 'bag') !== false || strpos($lowerMessage, 'backpack') !== false) {
            return 'We have several great bags available: Joust Duffle Bag ($34), Crown Summit Backpack ($38), and Wayfarer Messenger Bag ($45). Which interests you?';
        } else {
            return 'I understand you\'re interested in shopping! We have great bags available. Try asking about backpacks, messenger bags, totes, or duffle bags.';
        }
    }
} 