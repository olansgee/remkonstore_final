<?php
// cashier/pos.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (!is_logged_in() || !has_role('cashier')) {
    header("Location: ../index.php");
    exit();
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Fetch store name
$store_name = 'General Store'; // Default
if (isset($_SESSION['store_id'])) {
    $stmt = $conn->prepare("SELECT store_name FROM stores WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['store_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $store_name = $row['store_name'];
    }
    $stmt->close();
}
$_SESSION['store_name'] = $store_name;

$products_result = $conn->query("SELECT id, product_name, price, cost_price, stock_quantity, barcode, pieces_per_carton FROM products ORDER BY stock_quantity > 0 DESC, product_name ASC");
$customers_result = $conn->query("SELECT id, name, phone FROM customers ORDER BY name ASC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS - <?= htmlspecialchars($store_name) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #3498db; --secondary: #2c3e50; --success: #2ecc71; --danger: #e74c3c; --light: #ecf0f1; --dark: #34495e; --gray: #95a5a6; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: #333; line-height: 1.6; min-height: 100vh; }
        .container { max-width: 1600px; margin: 0 auto; padding: 20px; }
        header { background: linear-gradient(135deg, var(--secondary), var(--dark)); color: white; border-radius: 15px; padding: 20px; margin-bottom: 25px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); }
        .header-content { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
        .logo { display: flex; align-items: center; gap: 15px; }
        .logo i { font-size: 2.8rem; color: var(--primary); }
        .logo-text h1 { font-size: 2.2rem; }
        .logo-text p { font-size: 1.1rem; opacity: 0.9; }
        .user-info { text-align: right; }
        .user-info .time { font-size: 1.5rem; font-weight: bold; }
        .user-info .date { font-size: 1rem; opacity: 0.9; }
        .user-info .logout-btn { background: var(--danger); color: white; border: none; padding: 8px 15px; border-radius: 8px; cursor: pointer; margin-top: 10px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }

        .main-tabs { display: flex; border-bottom: 2px solid var(--secondary); margin-bottom: 20px; }
        .main-tab { padding: 15px 30px; cursor: pointer; font-size: 1.2rem; font-weight: 600; color: #fff; opacity: 0.7; }
        .main-tab.active { color: #fff; opacity: 1; border-bottom: 3px solid var(--primary); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .layout { display: grid; grid-template-columns: 1fr 450px; gap: 30px; }
        .products-section, .sales-history-section { background: rgba(255, 255, 255, 0.95); border-radius: 15px; padding: 30px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); }
        .products-header, .sales-history-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; }
        h2 { color: var(--secondary); font-size: 1.8rem; }
        .search-bar { display: flex; align-items: center; gap: 10px; width: 50%; border: 1px solid #ddd; border-radius: 25px; padding: 5px 15px; background: #fff; }
        .search-input { width: 100%; border: none; padding: 10px; font-size: 1.1rem; background: transparent; }
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 25px; max-height: 70vh; overflow-y: auto; padding: 5px; }
        .product-card { border: 1px solid #e0e7ff; border-radius: 15px; padding: 20px; transition: all 0.3s ease; background: white; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); cursor: pointer; }
        .product-card.out-of-stock { opacity: 0.5; cursor: not-allowed; }
        .product-name { font-weight: 700; font-size: 1.2rem; color: var(--dark); }
        .product-price { font-weight: 800; font-size: 1.5rem; color: var(--primary); }
        .product-stock { font-size: 1rem; padding: 6px 15px; border-radius: 20px; display: inline-block; font-weight: 600; }
        .product-stock.in-stock { background: #e8f5e9; color: #2e7d32; }
        .product-stock.out-of-stock { background: #ffebee; color: #c62828; }
        .cart-container { background: rgba(255, 255, 255, 0.95); border-radius: 15px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); display: flex; flex-direction: column; height: fit-content; }
        .cart-header { background: linear-gradient(135deg, var(--primary), #1a5276); color: white; padding: 25px; }
        .cart-body { padding: 20px; max-height: 400px; overflow-y: auto; }
        .cart-footer { padding: 25px; background: #f8f9fa; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 30px; border-radius: 15px; width: 90%; max-width: 550px; }
        
        #sales-history-table-container { max-height: 70vh; overflow-y: auto; }
        .date-filter { display: flex; gap: 15px; align-items: center; margin-bottom: 20px; }
        .date-filter label { font-weight: 600; }
        .date-filter input { padding: 8px; border-radius: 5px; border: 1px solid #ccc; }
        .btn-view-receipt { background: var(--success); color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none; }

        @media print {
            body * { visibility: hidden; }
            #printable-receipt, #printable-receipt * { visibility: visible; }
            #printable-receipt { position: absolute; left: 0; top: 0; width: 100%; }
        }
        #printable-receipt { display: none; font-family: 'Courier New', Courier, monospace; width: 77.5mm; font-size: 12pt; font-weight: bold; }
        /* Other receipt styles from before */
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <div class="logo"><i class="fas fa-cash-register"></i><div class="logo-text"><h1><?= htmlspecialchars($store_name) ?></h1><p>POS System</p></div></div>
                <div class="user-info">
                    <div id="live-time" class="time"></div>
                    <div id="live-date" class="date"></div>
                    <p><i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['full_name']) ?></p>
                    <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </header>

        <div class="main-tabs">
            <div class="main-tab active" data-tab="pos-tab"><i class="fas fa-th-large"></i> POS</div>
            <div class="main-tab" data-tab="history-tab"><i class="fas fa-history"></i> Sales History</div>
        </div>

        <div id="pos-tab" class="tab-content active">
            <div class="layout">
                <div class="products-section">
                    <div class="products-header">
                        <h2><i class="fas fa-boxes"></i> Products</h2>
                        <div class="search-bar"><i class="fas fa-search"></i><input type="text" id="product-search" class="search-input" placeholder="Search by name or barcode..."></div>
                    </div>
                    <div class="products-grid" id="products-grid">
                        <?php if ($products_result && $products_result->num_rows > 0): ?>
                            <?php while ($product = $products_result->fetch_assoc()): ?>
                                <div class="product-card <?php if ($product['stock_quantity'] <= 0) echo 'out-of-stock'; ?>" data-product-id="<?= $product['id'] ?>" data-product-name="<?= htmlspecialchars($product['product_name']) ?>" data-product-price="<?= $product['price'] ?>" data-cost-price="<?= $product['cost_price'] ?>" data-stock-quantity="<?= $product['stock_quantity'] ?>" data-pieces-per-carton="<?= $product['pieces_per_carton'] ?? 1 ?>">
                                    <div class="product-name"><?= htmlspecialchars($product['product_name']) ?></div>
                                    <div class="product-price">Ref Price: ₦<?= number_format($product['price'], 2) ?></div>
                                    <div class="product-stock <?= $product['stock_quantity'] > 0 ? 'in-stock' : 'out-of-stock' ?>"><?= $product['stock_quantity'] > 0 ? $product['stock_quantity'] . ' in stock' : 'Out of Stock' ?></div>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="cart-container">
                    <!-- Cart HTML from before -->
                </div>
            </div>
        </div>

        <div id="history-tab" class="tab-content">
            <div class="sales-history-section">
                <div class="sales-history-header"><h2><i class="fas fa-receipt"></i> Daily Sales Report</h2></div>
                <div class="date-filter">
                    <label for="start-date">From:</label>
                    <input type="date" id="start-date">
                    <label for="end-date">To:</label>
                    <input type="date" id="end-date">
                </div>
                <div id="sales-history-table-container">
                    <table class="table">
                        <thead><tr><th>ID</th><th>Customer</th><th>Total</th><th>Payment</th><th>Date</th><th>Actions</th></tr></thead>
                        <tbody id="sales-history-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div id="pos-modal" class="modal-overlay"><!-- POS Modal HTML from before --></div>
    <div id="receipt-modal" class="modal-overlay"><!-- Receipt Modal HTML from before --></div>
    <div id="printable-receipt"></div>

    <script>
        // All previous JS for POS, modals, cart, etc.
        // ...

        // NEW SCRIPT FOR TABS AND SALES HISTORY
        document.addEventListener('DOMContentLoaded', function() {
            // ... (all previous JS code for POS)

            const tabs = document.querySelectorAll('.main-tab');
            const tabContents = document.querySelectorAll('.tab-content');
            const startDateInput = document.getElementById('start-date');
            const endDateInput = document.getElementById('end-date');
            const salesHistoryBody = document.getElementById('sales-history-body');

            const today = new Date().toISOString().split('T')[0];
            startDateInput.value = today;
            endDateInput.value = today;

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    const target = document.getElementById(tab.dataset.tab);
                    tabContents.forEach(tc => tc.classList.remove('active'));
                    target.classList.add('active');
                    if (tab.dataset.tab === 'history-tab') {
                        fetchSalesHistory();
                    }
                });
            });

            async function fetchSalesHistory() {
                const start = startDateInput.value;
                const end = endDateInput.value;
                const response = await fetch(`../api/sales_history.php?start_date=${start}&end_date=${end}`);
                const result = await response.json();

                salesHistoryBody.innerHTML = '';
                if (result.success && result.data.length > 0) {
                    result.data.forEach(sale => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${sale.id}</td>
                            <td>${sale.customer_name || 'N/A'}</td>
                            <td>₦${parseFloat(sale.total_amount).toFixed(2)}</td>
                            <td>${sale.payment_method}</td>
                            <td>${new Date(sale.sale_date).toLocaleString()}</td>
                            <td><button class="btn-view-receipt" data-sale-id="${sale.id}">View</button></td>
                        `;
                        salesHistoryBody.appendChild(row);
                    });
                } else {
                    salesHistoryBody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No sales found for this period.</td></tr>';
                }
            }

            startDateInput.addEventListener('change', fetchSalesHistory);
            endDateInput.addEventListener('change', fetchSalesHistory);

            salesHistoryBody.addEventListener('click', async (e) => {
                if (e.target.classList.contains('btn-view-receipt')) {
                    const saleId = e.target.dataset.saleId;
                    const response = await fetch(`../api/get_receipt.php?id=${saleId}`);
                    const result = await response.json();
                    if (result.success) {
                        showReceipt(result.receipt);
                    } else {
                        alert('Could not fetch receipt details.');
                    }
                }
            });

            // Live Clock
            function updateClock() {
                // ... (clock logic from before)
            }
            // ...
        });
    </script>
</body>
</html>
