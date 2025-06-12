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
     * Process AI chat message securely
     *
     * @param string $message
     * @param string|null $cartId
     * @return AiChatResponseInterface
     */
    public function processMessage(string $message, ?string $cartId = null): \Bold\CheckoutPaymentBooster\Api\Data\AiChatResponseInterface
    {
        try {
            $apiKey = $this->getGeminiApiKey();
            
            $this->logger->info('🔑 AI Chat - API Key status: ' . ($apiKey ? 'FOUND (' . strlen($apiKey) . ' chars)' : 'NOT FOUND'));
            
            if (!$apiKey) {
                // No API key configured - use fallback
                $this->logger->info('❌ AI Chat - No API key, using fallback');
                return $this->responseFactory->create()
                    ->setSuccess(true)
                    ->setMessage($this->generateFallbackResponse($message))
                    ->setSource('fallback');
            }
            
            $this->logger->info('🤖 AI Chat - Processing message: ' . substr($message, 0, 50) . '...');
            $prompt = $this->buildPrompt($message, $cartId);
            $this->logger->info('📝 AI Chat - Built prompt, calling Gemini API...');
            
            $response = $this->callGeminiApi($apiKey, $prompt);
            
            if ($response) {
                $this->logger->info('✅ AI Chat - Gemini API success, response length: ' . strlen($response));
                return $this->responseFactory->create()
                    ->setSuccess(true)
                    ->setMessage($response)
                    ->setSource('gemini');
            } else {
                $this->logger->warning('⚠️ AI Chat - Gemini API returned empty response');
            }
        } catch (\Exception $e) {
            $this->logger->error('❌ AI Chat - Exception: ' . $e->getMessage());
            $this->logger->error('❌ AI Chat - Stack trace: ' . $e->getTraceAsString());
        }

        // Fallback response
        return $this->responseFactory->create()
            ->setSuccess(true)
            ->setMessage($this->generateFallbackResponse($message))
            ->setSource('fallback');
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
     * Build prompt for Gemini API
     *
     * @param string $message
     * @param string|null $cartId
     * @return string
     */
    private function buildPrompt(string $message, ?string $cartId = null): string
    {
        $availableProducts = [
            'Joust Duffle Bag (24-MB01) - $34.00',
            'Crown Summit Backpack (24-MB03) - $38.00', 
            'Wayfarer Messenger Bag (24-MB05) - $45.00',
            'Compete Track Tote (24-WB02) - $33.00',
            'Strive Shoulder Pack (24-MB04) - $32.00',
            'Voyage Yoga Bag (24-WB01) - $32.00'
        ];

        $context = "You are a helpful shopping assistant for an e-commerce store. ";
        $context .= "Available products: " . implode(", ", $availableProducts) . ". ";
        $context .= "You can help customers find products, get product information, and guide them to add items to cart. ";
        $context .= "Be friendly, helpful, and encouraging. Keep responses concise but informative. ";
        
        if ($cartId) {
            $context .= "The customer has an active shopping cart. ";
        }

        $context .= "\n\nCustomer message: " . $message;
        $context .= "\n\nPlease respond as a helpful shopping assistant:";

        return $context;
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
            ],
            'generation_config' => [
                'response_mime_type' => 'application/json'
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