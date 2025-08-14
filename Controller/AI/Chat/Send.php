<?php
declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Controller\AI\Chat;

use Bold\CheckoutPaymentBooster\Api\AI\ChatInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

/**
 * Chat Send Controller
 */
class Send implements HttpPostActionInterface
{
    private RequestInterface $request;
    private JsonFactory $resultJsonFactory;
    private ChatInterface $chatService;
    private Json $json;
    private LoggerInterface $logger;

    public function __construct(
        RequestInterface $request,
        JsonFactory $resultJsonFactory,
        ChatInterface $chatService,
        Json $json,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->chatService = $chatService;
        $this->json = $json;
        $this->logger = $logger;
    }

    /**
     * Execute chat send action
     */
    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();
        
        try {
            // Get request data
            $content = $this->request->getContent();
            $data = $this->json->unserialize($content);
            
            $message = $data['message'] ?? '';
            $sessionId = $data['session_id'] ?? null;
            
            if (empty($message)) {
                return $result->setData([
                    'success' => false,
                    'error' => 'Message is required'
                ]);
            }
            
            // Send message to AI service
            $response = $this->chatService->sendMessage($message, $sessionId);
            
            // Format response
            $responseData = [
                'success' => true,
                'data' => [
                    'message' => $response->getMessage(),
                    'role' => $response->getRole(),
                    'timestamp' => $response->getTimestamp(),
                    'session_id' => $response->getSessionId(),
                    'intent' => $response->getIntent(),
                    'products' => $response->getProducts()
                ]
            ];
            
            return $result->setData($responseData);
            
        } catch (\Exception $e) {
            $this->logger->error('AI Chat Error: ' . $e->getMessage());
            
            return $result->setData([
                'success' => false,
                'error' => 'An error occurred while processing your message'
            ]);
        }
    }
} 