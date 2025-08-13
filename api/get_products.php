<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!is_logged_in() || !has_role('cashier')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit();
}

$status = $_GET['status'] ?? 'all'; // 'in-stock', 'out-of-stock', 'all'

$sql = "SELECT id, product_name, price, cost_price, stock_quantity, barcode, pieces_per_carton FROM products";

if ($status === 'in-stock') {
    $sql .= " WHERE stock_quantity > 0";
} elseif ($status === 'out-of-stock') {
    $sql .= " WHERE stock_quantity <= 0";
}

$sql .= " ORDER BY product_name ASC";

$result = $conn->query($sql);
$products = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

echo json_encode(['success' => true, 'products' => $products]);
?>
