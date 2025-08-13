<?php
// remkonstore/api/cart.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// This is a simple API endpoint, so we'll handle different request methods
$method = $_SERVER['REQUEST_METHOD'];

// All responses will be JSON
header('Content-Type: application/json');

if (!is_logged_in() || !has_role('cashier')) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit();
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($method === 'GET') {
    // Get the current cart state
    $cart = $_SESSION['cart'] ?? [];
    $total = 0;
    foreach ($cart as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    echo json_encode(['success' => true, 'cart' => array_values($cart), 'total' => $total]);
} 
elseif ($method === 'POST') {
    // Add an item to the cart
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['id']) || !isset($data['quantity']) || !isset($data['price'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'error' => 'Invalid product data.']);
        exit();
    }

    $product_id = (int)$data['id'];
    $quantity = (int)$data['quantity'];
    $price = (float)$data['price'];
    $name = $data['name'];

    // Check if product exists and has enough stock
    $stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if (!$product) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'error' => 'Product not found.']);
        exit();
    }

    $cart_key = $product_id . '-' . $price; // Unique key for item at a specific price
    $existing_qty_in_cart = isset($_SESSION['cart'][$cart_key]) ? $_SESSION['cart'][$cart_key]['quantity'] : 0;

    if ($product['stock_quantity'] < ($quantity + $existing_qty_in_cart)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Not enough stock available. Only ' . $product['stock_quantity'] . ' left.']);
        exit();
    }
    
    if (isset($_SESSION['cart'][$cart_key])) {
        $_SESSION['cart'][$cart_key]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][$cart_key] = [
            'id' => $product_id,
            'name' => $name,
            'quantity' => $quantity,
            'price' => $price,
        ];
    }
    
    echo json_encode(['success' => true, 'message' => 'Item added to cart.']);

} 
elseif ($method === 'DELETE') {
    // Could be used to remove a single item or clear the cart
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['clear_all']) && $data['clear_all'] === true) {
        $_SESSION['cart'] = [];
        echo json_encode(['success' => true, 'message' => 'Cart cleared.']);
    } elseif (isset($data['id']) && isset($data['price'])) {
        $cart_key = (int)$data['id'] . '-' . (float)$data['price'];
        if (isset($_SESSION['cart'][$cart_key])) {
            unset($_SESSION['cart'][$cart_key]);
            echo json_encode(['success' => true, 'message' => 'Item removed.']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Item not found in cart.']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request for DELETE.']);
    }
}
else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'error' => 'Method not supported.']);
}
?>
