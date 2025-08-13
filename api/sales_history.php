<?php
// api/sales_history.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if (!is_logged_in() || !has_role('cashier')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$cashier_name = $_SESSION['full_name'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Add time to end_date to include the full day
$end_date_inclusive = $end_date . ' 23:59:59';

$sales = [];

try {
    $sql = "SELECT s.id, s.total_amount, s.payment_method, s.sale_date, c.name as customer_name
            FROM sales s
            LEFT JOIN customers c ON s.customer_id = c.id
            WHERE s.cashier_name = ?
            AND s.sale_date BETWEEN ? AND ?
            ORDER BY s.sale_date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $cashier_name, $start_date, $end_date_inclusive);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }
    $stmt->close();

    echo json_encode(['success' => true, 'data' => $sales]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An internal server error occurred.']);
}

$conn->close();
?>
