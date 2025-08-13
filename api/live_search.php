<?php
// api/live_search.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!is_logged_in() || !has_role('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$table = $_GET['table'] ?? '';
$term = $_GET['term'] ?? '';

if (empty($table) || empty($term)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameters.']);
    exit();
}

$term = "%{$term}%";
$results = [];

try {
    switch ($table) {
        case 'products':
            $sql = "SELECT id, product_name, category, cost_price, price, stock_quantity, barcode, product_description FROM products WHERE product_name LIKE ? OR barcode LIKE ? ORDER BY product_name ASC LIMIT 50";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $term, $term);
            break;

        case 'customers':
            $sql = "SELECT id, name, phone, email, address FROM customers WHERE name LIKE ? OR phone LIKE ? OR email LIKE ? ORDER BY name ASC LIMIT 50";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $term, $term, $term);
            break;

        case 'suppliers':
            $sql = "SELECT id, supplier_name, supplier_address, phone, email FROM suppliers WHERE supplier_name LIKE ? OR phone LIKE ? OR email LIKE ? ORDER BY supplier_name ASC LIMIT 50";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sss", $term, $term, $term);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid table specified.']);
            exit();
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'data' => $results]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An internal server error occurred.']);
}

$conn->close();
?>
