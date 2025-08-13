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
        .user-info p { margin-top: 5px; }
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
        .empty-cart { text-align: center; padding: 50px 20px; color: var(--gray); }
        .cart-item { display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #eee; gap: 15px; align-items: center; }
        .cart-item-details { flex-grow: 1; }
        .cart-item-name { font-weight: 600; font-size: 1.1rem; }
        .cart-item-sub { font-size: 0.9rem; color: var(--gray); }
        .cart-item-total { font-weight: 700; font-size: 1.1rem; }
        .cart-item-remove { background: none; border: none; color: var(--danger); cursor: pointer; font-size: 1.2rem; }
        .cart-footer { padding: 25px; background: #f8f9fa; }
        .cart-total-section { display: flex; justify-content: space-between; font-size: 1.8rem; font-weight: 800; margin-bottom: 20px; color: var(--dark); }
        .form-group { margin-bottom: 15px; }
        .form-control { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ddd; font-size: 1rem; }
        .btn-group { display: flex; gap: 10px; margin-top: 10px; }
        .btn { padding: 12px 20px; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; border: none; }
        .btn-success { background: var(--success); color: white; flex-grow: 1; }
        .btn-danger { background: var(--danger); color: white; }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 30px; border-radius: 15px; width: 90%; max-width: 550px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .modal-close { background: none; border: none; font-size: 2rem; cursor: pointer; }
        .modal-body .quantity-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .modal-footer { border-top: 1px solid #eee; padding-top: 20px; margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px; }
        
        #sales-history-table-container { max-height: 70vh; overflow-y: auto; }
        .sales-history-section table { width: 100%; border-collapse: collapse; }
        .sales-history-section th, .sales-history-section td { padding: 12px; border-bottom: 1px solid #eee; }
        .date-filter { display: flex; gap: 15px; align-items: center; margin-bottom: 20px; }
        .date-filter label { font-weight: 600; }
        .date-filter input { padding: 8px; border-radius: 5px; border: 1px solid #ccc; }
        .btn-view-receipt { background: var(--success); color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none; border: none; cursor: pointer; }

        @media print { body * { visibility: hidden; } #printable-receipt, #printable-receipt * { visibility: visible; } #printable-receipt { position: absolute; left: 0; top: 0; width: 100%; } }
        #printable-receipt { display: none; font-family: 'Courier New', Courier, monospace; width: 77.5mm; font-size: 12pt; font-weight: bold; }
        #printable-receipt h2 { font-size: 16pt; text-align: center; }
        #printable-receipt p { text-align: center; font-size: 10pt; }
        #printable-receipt table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        #printable-receipt th, #printable-receipt td { padding: 4px 2px; }
        #printable-receipt th { border-bottom: 2px solid #000; }
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
                    <div class="products-header"><h2><i class="fas fa-boxes"></i> Products</h2><div class="search-bar"><i class="fas fa-search"></i><input type="text" id="product-search" class="search-input" placeholder="Search by name..."></div></div>
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
                    <div class="cart-header"><h2><i class="fas fa-shopping-cart"></i> Current Sale</h2></div>
                    <div class="cart-body" id="cart-items"><div class="empty-cart"><i class="fas fa-shopping-cart"></i><h3>Your cart is empty</h3></div></div>
                    <div class="cart-footer">
                        <div class="cart-total-section"><span>Total:</span><span id="cart-total">₦0.00</span></div>
                        <form id="sale-form">
                            <div class="form-group"><label for="customer_id">Customer</label><input list="customers-list" id="customer_input" class="form-control" placeholder="Search customer..."><datalist id="customers-list"><?php while($customer = $customers_result->fetch_assoc()): ?><option value="<?= htmlspecialchars($customer['name']) ?>" data-id="<?= $customer['id'] ?>"></option><?php endwhile; ?></datalist><input type="hidden" name="customer_id" id="customer_id"></div>
                            <div class="form-group"><label for="payment_method">Payment Method</label><select name="payment_method" id="payment_method" class="form-control"><option value="cash">Cash</option><option value="credit_card">Credit Card</option></select></div>
                            <div class="btn-group"><button type="button" id="clear-cart-btn" class="btn btn-danger"><i class="fas fa-trash"></i> Clear</button><button type="submit" class="btn btn-success"><i class="fas fa-check-circle"></i> Process Sale</button></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div id="history-tab" class="tab-content">
            <div class="sales-history-section">
                <div class="sales-history-header"><h2><i class="fas fa-receipt"></i> Sales Report</h2></div>
                <div class="date-filter">
                    <label for="start-date">From:</label><input type="date" id="start-date" class="form-control" style="width: auto;">
                    <label for="end-date">To:</label><input type="date" id="end-date" class="form-control" style="width: auto;">
                </div>
                <div id="sales-history-table-container">
                    <table>
                        <thead><tr><th>ID</th><th>Customer</th><th>Total</th><th>Payment</th><th>Date</th><th>Actions</th></tr></thead>
                        <tbody id="sales-history-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="pos-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header"><h2 id="modal-product-name"></h2><button class="modal-close">&times;</button></div>
            <div class="modal-body">
                <p>Cost Price: <strong id="modal-cost-price"></strong></p>
                <div class="quantity-grid">
                    <div class="form-group"><label for="modal-carton-quantity">Quantity (ctn)</label><input type="number" id="modal-carton-quantity" class="form-control" value="0" min="0"></div>
                    <div class="form-group"><label for="modal-piece-quantity">Quantity (pcs)</label><input type="number" id="modal-piece-quantity" class="form-control" value="1" min="0"></div>
                </div>
                <div class="form-group"><label for="modal-price">Selling Price (per piece)</label><input type="number" id="modal-price" class="form-control" step="0.01" min="0"></div>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary modal-close">Cancel</button><button id="add-to-cart-btn" class="btn btn-primary">Add to Cart</button></div>
        </div>
    </div>
    <div id="receipt-modal" class="modal-overlay">
        <div class="modal-content" id="receipt-modal-content">
            <div class="modal-header"><h2>Receipt</h2><button class="modal-close">&times;</button></div>
            <div class="modal-body" id="receipt-body"></div>
            <div class="modal-footer"><button class="btn btn-secondary modal-close">New Sale</button><button id="print-receipt-btn" class="btn btn-primary"><i class="fas fa-print"></i> Print</button></div>
        </div>
    </div>
    <div id="printable-receipt"></div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- DOM Elements ---
        const productGrid = document.getElementById('products-grid');
        const productSearchInput = document.getElementById('product-search');
        const cartItemsContainer = document.getElementById('cart-items');
        const cartTotalEl = document.getElementById('cart-total');
        const clearCartBtn = document.getElementById('clear-cart-btn');
        const saleForm = document.getElementById('sale-form');
        const customerInput = document.getElementById('customer_input');
        const customerIdInput = document.getElementById('customer_id');
        const customersDatalist = document.getElementById('customers-list');
        const posModal = document.getElementById('pos-modal');
        const addToCartBtn = document.getElementById('add-to-cart-btn');
        const receiptModal = document.getElementById('receipt-modal');
        const printReceiptBtn = document.getElementById('print-receipt-btn');
        let currentProduct = null;

        // --- API Helper ---
        const apiRequest = async (endpoint, method, body) => {
            try {
                const options = { method, headers: { 'Content-Type': 'application/json' } };
                if (body) options.body = JSON.stringify(body);
                const response = await fetch(endpoint, options);
                const result = await response.json();
                if (!response.ok) throw new Error(result.error || `HTTP error! status: ${response.status}`);
                return result;
            } catch (error) {
                console.error(`API Error (${method} ${endpoint}):`, error);
                alert(`An error occurred: ${error.message}`);
                return null;
            }
        };

        // --- Cart Functions ---
        const updateCartDisplay = async () => {
            const data = await apiRequest('../api/cart.php', 'GET');
            if (!data || !data.success) return;
            cartItemsContainer.innerHTML = '';
            if (data.cart.length === 0) {
                cartItemsContainer.innerHTML = `<div class="empty-cart"><i class="fas fa-shopping-cart"></i><h3>Your cart is empty</h3></div>`;
            } else {
                data.cart.forEach(item => {
                    const itemEl = document.createElement('div');
                    itemEl.classList.add('cart-item');
                    itemEl.innerHTML = `<div class="cart-item-details"><div class="cart-item-name">${item.name}</div><div class="cart-item-sub">${item.quantity} x ₦${item.price.toFixed(2)}</div></div><div class="cart-item-total">₦${(item.quantity * item.price).toFixed(2)}</div><button class="cart-item-remove" data-id="${item.id}" data-price="${item.price}">&times;</button>`;
                    cartItemsContainer.appendChild(itemEl);
                });
            }
            cartTotalEl.textContent = `₦${data.total.toFixed(2)}`;
        };

        const addToCart = async () => {
            const cartonQty = parseInt(document.getElementById('modal-carton-quantity').value, 10) || 0;
            const pieceQty = parseInt(document.getElementById('modal-piece-quantity').value, 10) || 0;
            const price = parseFloat(document.getElementById('modal-price').value);
            const totalQuantity = (cartonQty * currentProduct.piecesPerCarton) + pieceQty;
            if (totalQuantity <= 0 || isNaN(price) || price <= 0) {
                alert('Please enter a valid quantity and price.');
                return;
            }
            const result = await apiRequest('../api/cart.php', 'POST', { id: currentProduct.id, name: currentProduct.name, quantity: totalQuantity, price: price });
            if (result && result.success) {
                updateCartDisplay();
                posModal.style.display = 'none';
            }
        };

        const removeFromCart = async (id, price) => {
            await apiRequest('../api/cart.php', 'DELETE', { id, price });
            updateCartDisplay();
        };

        const clearCart = async () => {
            if (confirm('Are you sure you want to clear the cart?')) {
                await apiRequest('../api/cart.php', 'DELETE', { clear_all: true });
                updateCartDisplay();
            }
        };

        // --- Sale & Receipt Functions ---
        const processSale = async () => {
            const result = await apiRequest('../api/sale.php', 'POST', { customer_id: customerIdInput.value, payment_method: document.getElementById('payment_method').value });
            if (result && result.success) {
                updateCartDisplay();
                showReceipt(result.receipt);
            }
        };

        const showReceipt = (receiptData) => {
            // Populate on-screen modal
            let itemsHtml = `<h2>${receiptData.store_name || 'Remkon Store'}</h2><p>Receipt ID: ${receiptData.id}</p><p>Date: ${new Date(receiptData.date).toLocaleString()}</p><table style="width:100%;text-align:left;margin-top:20px;border-collapse:collapse;"><thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead><tbody>`;
            receiptData.items.forEach(item => {
                itemsHtml += `<tr><td style="padding:8px;border-bottom:1px dashed #ccc;">${item.name}</td><td style="padding:8px;border-bottom:1px dashed #ccc;">${item.quantity}</td><td style="padding:8px;border-bottom:1px dashed #ccc;">${item.price.toFixed(2)}</td><td style="padding:8px;border-bottom:1px dashed #ccc;">${(item.quantity * item.price).toFixed(2)}</td></tr>`;
            });
            itemsHtml += `</tbody></table><div style="margin-top:20px;font-size:1.4rem;font-weight:bold;text-align:right;">Total: ₦${receiptData.total.toFixed(2)}</div>`;
            document.getElementById('receipt-body').innerHTML = itemsHtml;

            // Populate printable receipt
            let printableHtml = `<h2>${receiptData.store_name || 'Remkon Store'}</h2><p>Date: ${new Date(receiptData.date).toLocaleString()}</p><p>Receipt: ${receiptData.id}</p><p>----------------------------------------</p><table><thead><tr><th>SN</th><th>ITEMS</th><th>QTY</th><th>U/PRICE</th><th>MOUNT(N)</th></tr></thead><tbody>`;
            let sn = 1;
            receiptData.items.forEach((item) => {
                printableHtml += `<tr><td>${sn++}</td><td>${item.name}</td><td>${item.quantity}</td><td style="text-align:right;">${item.price.toFixed(2)}</td><td style="text-align:right;">${(item.quantity * item.price).toFixed(2)}</td></tr>`;
            });
            printableHtml += `<tr><td colspan="4" style="border-top:2px solid #000;font-weight:bold;padding-top:5px;">TOTAL</td><td style="text-align:right;border-top:2px solid #000;font-weight:bold;padding-top:5px;">₦${receiptData.total.toFixed(2)}</td></tr></tbody></table><p>----------------------------------------</p><p style="text-align:center;">Thank you!</p>`;
            document.getElementById('printable-receipt').innerHTML = printableHtml;

            receiptModal.style.display = 'flex';
        };

        // --- Event Listeners ---
        productGrid.addEventListener('click', e => {
            const card = e.target.closest('.product-card');
            if (card && !card.classList.contains('out-of-stock')) openPosModal(card);
        });

        productSearchInput.addEventListener('keyup', () => {
            const filter = productSearchInput.value.toLowerCase();
            document.querySelectorAll('.product-card').forEach(card => {
                const name = card.dataset.productName.toLowerCase();
                card.style.display = name.includes(filter) ? "" : "none";
            });
        });

        posModal.addEventListener('click', e => { if (e.target.matches('.modal-overlay, .modal-close')) posModal.style.display = 'none'; });
        receiptModal.addEventListener('click', e => { if (e.target.matches('.modal-overlay, .modal-close')) receiptModal.style.display = 'none'; });
        addToCartBtn.addEventListener('click', addToCart);
        clearCartBtn.addEventListener('click', clearCart);
        saleForm.addEventListener('submit', (e) => { e.preventDefault(); processSale(); });
        cartItemsContainer.addEventListener('click', (e) => { if (e.target.classList.contains('cart-item-remove')) removeFromCart(e.target.dataset.id, e.target.dataset.price); });
        printReceiptBtn.addEventListener('click', () => window.print());

        // --- Init & Clock ---
        updateCartDisplay();
        updateClock();
        setInterval(updateClock, 1000);
        function updateClock() {
            const now = new Date();
            document.getElementById('live-time').innerText = now.toLocaleTimeString();
            document.getElementById('live-date').innerText = now.toLocaleDateString();
        }

        // Sales History Init
        const startDateInput = document.getElementById('start-date');
        const endDateInput = document.getElementById('end-date');
        const salesHistoryBody = document.getElementById('sales-history-body');
        const today = new Date().toISOString().split('T')[0];
        startDateInput.value = today;
        endDateInput.value = today;

        async function fetchSalesHistory() {
            const start = startDateInput.value;
            const end = endDateInput.value;
            const response = await apiRequest(`../api/sales_history.php?start_date=${start}&end_date=${end}`, 'GET');
            salesHistoryBody.innerHTML = '';
            if (response.success && response.data.length > 0) {
                response.data.forEach(sale => {
                    salesHistoryBody.innerHTML += `<tr><td>${sale.id}</td><td>${sale.customer_name || 'N/A'}</td><td>₦${parseFloat(sale.total_amount).toFixed(2)}</td><td>${sale.payment_method}</td><td>${new Date(sale.sale_date).toLocaleString()}</td><td><button class="btn-view-receipt" data-sale-id="${sale.id}">View</button></td></tr>`;
                });
            } else {
                salesHistoryBody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No sales found.</td></tr>';
            }
        }
        startDateInput.addEventListener('change', fetchSalesHistory);
        endDateInput.addEventListener('change', fetchSalesHistory);
        salesHistoryBody.addEventListener('click', async e => {
            if (e.target.classList.contains('btn-view-receipt')) {
                const saleId = e.target.dataset.saleId;
                const result = await apiRequest(`../api/get_receipt.php?id=${saleId}`, 'GET');
                if (result.success) showReceipt(result.receipt);
            }
        });

        // Tab Logic
        document.querySelectorAll('.main-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.main-tab, .tab-content').forEach(el => el.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab).classList.add('active');
                if (tab.dataset.tab === 'history-tab') fetchSalesHistory();
            });
        });

        function openPosModal(productCard) {
            currentProduct = { id: productCard.dataset.productId, name: productCard.dataset.productName, defaultPrice: parseFloat(productCard.dataset.productPrice), costPrice: parseFloat(productCard.dataset.costPrice), piecesPerCarton: parseInt(productCard.dataset.piecesPerCarton, 10) || 1 };
            document.getElementById('modal-product-name').textContent = currentProduct.name;
            document.getElementById('modal-cost-price').textContent = `₦${currentProduct.costPrice.toFixed(2)}`;
            document.getElementById('modal-carton-quantity').value = 0;
            document.getElementById('modal-piece-quantity').value = 1;
            document.getElementById('modal-price').value = currentProduct.defaultPrice.toFixed(2);
            posModal.style.display = 'flex';
        }
    });
    </script>
</body>
</html>
