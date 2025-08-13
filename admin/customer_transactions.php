<?php
// admin/customer_transactions.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

if (!isset($_GET['id'])) {
    header("Location: customers.php");
    exit();
}

$customer_id = (int)$_GET['id'];
$customer_stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
$customer_stmt->bind_param("i", $customer_id);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();
if ($customer_result->num_rows === 0) {
    header("Location: customers.php"); // Or show a not found error
    exit();
}
$customer = $customer_result->fetch_assoc();
$customer_stmt->close();

$transactions_stmt = $conn->prepare("
    SELECT s.*, st.store_name 
    FROM sales s
    LEFT JOIN stores st ON s.store_id = st.id
    WHERE s.customer_id = ?
    ORDER BY s.sale_date DESC
");
$transactions_stmt->bind_param("i", $customer_id);
$transactions_stmt->execute();
$transactions = $transactions_stmt->get_result();
$transactions_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History - <?= htmlspecialchars($customer['name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #2c3e50; --secondary: #3498db; --accent: #e74c3c; --success: #27ae60; --warning: #f39c12; --light: #ecf0f1; --dark: #2c3e50; --gray: #95a5a6; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); color: #333; min-height: 100vh; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        header { background: linear-gradient(to right, var(--primary), var(--dark)); color: white; padding: 25px; border-radius: 15px; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        header h1 { font-size: 2.2rem; display: flex; align-items: center; gap: 15px; }
        .btn-dashboard { background: var(--secondary); color: white; padding: 12px 20px; text-decoration: none; border-radius: 8px; transition: background 0.3s; font-weight: 600; }
        .btn-dashboard:hover { background: #2980b9; }
        .card { background: white; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 30px; }
        .card-header { background: var(--primary); color: white; padding: 20px; }
        .card-body { padding: 25px; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 16px; text-align: left; border-bottom: 1px solid #e1e5eb; }
        th { background: var(--primary); color: white; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-receipt"></i> Transaction History</h1>
            <div>
                <a href="customers.php" class="btn-dashboard" style="margin-right: 10px;"><i class="fas fa-arrow-left"></i> Back to Customers</a>
                <a href="dashboard.php" class="btn-dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </div>
        </header>

        <div class="card">
            <div class="card-header">
                <h2>History for: <strong><?= htmlspecialchars($customer['name']) ?></strong> (<?= htmlspecialchars($customer['phone']) ?>)</h2>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Transaction ID</th>
                                <th>Date</th>
                                <th>Store</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Cashier</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($transactions->num_rows > 0): ?>
                                <?php while($tx = $transactions->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $tx['id'] ?></td>
                                    <td><?= date('M d, Y h:i A', strtotime($tx['sale_date'])) ?></td>
                                    <td><?= htmlspecialchars($tx['store_name'] ?? 'N/A') ?></td>
                                    <td>₦<?= number_format($tx['total_amount'], 2) ?></td>
                                    <td><?= htmlspecialchars(ucfirst($tx['payment_method'])) ?></td>
                                    <td><?= htmlspecialchars($tx['cashier_name']) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align:center;">No transactions found for this customer.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
