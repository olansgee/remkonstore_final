<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!is_logged_in() || !has_role('cashier')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed.']);
    exit();
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cannot process an empty sale.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$customer_id = isset($data['customer_id']) && !empty($data['customer_id']) ? (int)$data['customer_id'] : null;
$payment_method = $data['payment_method'] ?? 'cash';
$cashier_name = $_SESSION['username'];
$store_id = $_SESSION['store_id'] ?? 1;

$total_amount = 0;
foreach ($cart as $item) {
    $total_amount += $item['price'] * $item['quantity'];
}

$conn->begin_transaction();

try {
    $sale_stmt = $conn->prepare("INSERT INTO sales (customer_id, sale_date, total_amount, payment_method, cashier_name, store_id) VALUES (?, NOW(), ?, ?, ?, ?)");
    $sale_stmt->bind_param("idssi", $customer_id, $total_amount, $payment_method, $cashier_name, $store_id);
    $sale_stmt->execute();
    $sale_id = $conn->insert_id;

    $item_stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
    $stock_stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");

    foreach ($cart as $item) {
        $subtotal = $item['quantity'] * $item['price'];
        $item_stmt->bind_param("iiidd", $sale_id, $item['id'], $item['quantity'], $item['price'], $subtotal);
        $item_stmt->execute();

        $stock_stmt->bind_param("ii", $item['quantity'], $item['id']);
        $stock_stmt->execute();
    }

    $conn->commit();
    
    // Store receipt data for the next page/request
    $_SESSION['last_receipt'] = [
        'id' => $sale_id,
        'date' => date('Y-m-d H:i:s'),
        'store_name' => 'Remkon Store', // This should be fetched from DB if multi-store
        'cashier' => $cashier_name,
        'payment_method' => $payment_method,
        'total' => $total_amount,
        'items' => array_values($cart)
    ];

    // Clear the cart
    $receipt_data = $_SESSION['last_receipt'];
    $_SESSION['cart'] = [];

    echo json_encode(['success' => true, 'sale_id' => $sale_id, 'receipt' => $receipt_data, 'message' => 'Sale processed successfully!']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred while processing the sale: ' . $e->getMessage()]);
}
?>
