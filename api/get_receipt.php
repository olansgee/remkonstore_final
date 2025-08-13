<?php
// api/get_receipt.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!is_logged_in()) { // Allow any logged-in user to view a receipt
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$sale_id = $_GET['id'] ?? 0;

if (empty($sale_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Sale ID is required.']);
    exit();
}

try {
    // Fetch sale details
    $sale_stmt = $conn->prepare("SELECT * FROM sales WHERE id = ?");
    $sale_stmt->bind_param("i", $sale_id);
    $sale_stmt->execute();
    $sale_result = $sale_stmt->get_result();
    $sale = $sale_result->fetch_assoc();
    $sale_stmt->close();

    if (!$sale) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Sale not found.']);
        exit();
    }

    // Fetch sale items
    $items_stmt = $conn->prepare("
        SELECT si.quantity, si.unit_price, p.product_name
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        WHERE si.sale_id = ?
    ");
    $items_stmt->bind_param("i", $sale_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    $items = [];
    while ($item = $items_result->fetch_assoc()) {
        // In the new POS, the name is stored on the cart item, but for old sales we need to fetch it.
        // For simplicity, we'll just use product_name here.
        $items[] = [
            'name' => $item['product_name'],
            'quantity' => $item['quantity'],
            'price' => (float)$item['unit_price']
        ];
    }
    $items_stmt->close();

    // Construct receipt object
    $receipt = [
        'id' => $sale['id'],
        'date' => $sale['sale_date'],
        'total' => (float)$sale['total_amount'],
        'store_name' => $_SESSION['store_name'] ?? 'Remkon Store', // Get from session
        'items' => $items
    ];

    echo json_encode(['success' => true, 'receipt' => $receipt]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An internal server error occurred.']);
}

$conn->close();
?>
