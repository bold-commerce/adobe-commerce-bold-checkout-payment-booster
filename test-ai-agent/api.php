<?php
/**
 * Simple API for AI Agent testing
 */

header('Content-Type: application/json');

// Hardcoded product catalog for demo
$DEMO_PRODUCTS = [
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

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['action'])) {
        throw new Exception('Invalid request');
    }
    
    if ($input['action'] === 'chat') {
        $message = $input['message'] ?? '';
        $sessionId = $input['session_id'] ?? '';
        
        if (empty($message)) {
            throw new Exception('Message is required');
        }
        
        // Process the message
        $intent = detectIntent($message);
        $products = getRelevantProducts($message, $DEMO_PRODUCTS);
        $responseText = generateResponse($message, $intent, $products);
        
        echo json_encode([
            'success' => true,
            'message' => $responseText,
            'intent' => $intent,
            'products' => $products,
            'session_id' => $sessionId
        ]);
    } else {
        throw new Exception('Unknown action');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function detectIntent($message) {
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
    
    if (strpos($message, 'electronics') !== false) {
        return 'product_search';
    }

    return 'general';
}

function getRelevantProducts($message, $products) {
    $message = strtolower($message);
    $relevantProducts = [];

    foreach ($products as $product) {
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
        $relevantProducts = $products;
    }

    return $relevantProducts;
}

function generateResponse($message, $intent, $products) {
    switch ($intent) {
        case 'product_search':
            if (!empty($products)) {
                $productNames = array_column($products, 'name');
                return "I found some great options for you! Here are " . count($products) . " products that match your search: " . implode(', ', $productNames) . ". Would you like to see more details about any of these?";
            } else {
                return "I couldn't find any products matching your search. Let me show you our popular items instead!";
            }
            
        case 'checkout':
            return "Great! I can help you complete your purchase. Let me initialize your order and we'll get you checked out quickly. 🛒✨";
            
        case 'general':
        default:
            return "I'm here to help you find the perfect products! You can ask me about headphones, watches, clothing, or lifestyle products. What interests you today? 😊";
    }
}
?> 