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

// This page is now primarily for rendering the initial layout.
// All cart and sale logic will be handled by AJAX calls to the API.

$sql = "SELECT id, product_name, price, cost_price, stock_quantity, barcode, pieces_per_carton FROM products WHERE stock_quantity > 0 ORDER BY product_name ASC";
$products_result = $conn->query($sql);

// Fetch customers for the dropdown/search
$customers_result = $conn->query("SELECT id, name, phone FROM customers ORDER BY name ASC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remkon Store - POS System</title>
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
        .layout { display: grid; grid-template-columns: 1fr 450px; gap: 30px; }
        .products-section { background: rgba(255, 255, 255, 0.95); border-radius: 15px; padding: 30px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); }
        .products-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; }
        .products-header h2 { color: var(--secondary); font-size: 1.8rem; }
        .search-bar { display: flex; align-items: center; gap: 10px; width: 50%; border: 1px solid #ddd; border-radius: 25px; padding: 5px 15px; background: #fff; }
        .search-bar i { color: var(--gray); font-size: 1.2rem; }
        .search-input { width: 100%; border: none; padding: 10px; font-size: 1.1rem; background: transparent; }
        .search-input:focus { outline: none; }
        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 25px; max-height: 70vh; overflow-y: auto; padding: 5px; }
        .product-card { border: 1px solid #e0e7ff; border-radius: 15px; padding: 20px; transition: all 0.3s ease; background: white; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05); cursor: pointer; }
        .product-card:hover { transform: translateY(-8px); box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15); border-color: var(--primary); }
        .product-name { font-weight: 700; font-size: 1.2rem; color: var(--dark); margin-bottom: 10px; }
        .product-price { font-weight: 800; font-size: 1.5rem; color: var(--primary); margin-bottom: 10px; }
        .product-stock { font-size: 1rem; padding: 6px 15px; border-radius: 20px; display: inline-block; background: #e8f5e9; color: #2e7d32; font-weight: 600; }
        .cart-container { background: rgba(255, 255, 255, 0.95); border-radius: 15px; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); display: flex; flex-direction: column; height: fit-content; }
        .cart-header { background: linear-gradient(135deg, var(--primary), #1a5276); color: white; padding: 25px; }
        .cart-header h2 { font-size: 1.8rem; margin: 0; }
        .cart-body { padding: 20px; flex-grow: 1; max-height: 400px; overflow-y: auto; }
        .empty-cart { text-align: center; padding: 50px 20px; color: var(--gray); }
        .empty-cart i { font-size: 5rem; margin-bottom: 20px; opacity: 0.2; }
        .cart-item { display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #eee; gap: 15px; align-items: center; }
        .cart-item-details { flex-grow: 1; }
        .cart-item-name { font-weight: 600; font-size: 1.1rem; }
        .cart-item-sub { font-size: 0.9rem; color: var(--gray); }
        .cart-item-total { font-weight: 700; font-size: 1.1rem; }
        .cart-item-remove { background: none; border: none; color: var(--danger); cursor: pointer; font-size: 1.2rem; }
        .cart-footer { padding: 25px; background: linear-gradient(135deg, #f8f9fa, #e9ecef); }
        .cart-total-section { display: flex; justify-content: space-between; font-size: 1.8rem; font-weight: 800; margin-bottom: 20px; color: var(--dark); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-control { width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ddd; font-size: 1rem; }
        .btn-group { display: flex; gap: 10px; margin-top: 10px; }
        .btn { padding: 12px 20px; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; border: none; }
        .btn-success { background: var(--success); color: white; flex-grow: 1; }
        .btn-danger { background: var(--danger); color: white; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 10px; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; padding: 30px; border-radius: 15px; width: 90%; max-width: 500px; box-shadow: 0 5px 20px rgba(0,0,0,0.3); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .modal-close { background: none; border: none; font-size: 2rem; cursor: pointer; }
        .modal-body .form-group { margin-bottom: 15px; }
        .modal-body .form-control { width: 100%; padding: 14px; border: 2px solid #e1e5eb; border-radius: 10px; font-size: 1rem; }
        .modal-footer { border-top: 1px solid #eee; padding-top: 20px; margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px; }
        .modal-footer .btn { padding: 12px 25px; }
        .modal-footer .btn-primary { background: var(--primary); color: white; }
        .modal-footer .btn-secondary { background: var(--gray); color: white; }
        
        /* Receipt Modal Styles */
        #receipt-modal-content { max-width: 400px; }
        #receipt-body { text-align: center; }
        #receipt-body h2 { font-size: 1.8rem; margin-bottom: 10px; }
        #receipt-items-table { width: 100%; text-align: left; margin-top: 20px; border-collapse: collapse; }
        #receipt-items-table th, #receipt-items-table td { padding: 8px; }
        #receipt-items-table th { border-bottom: 2px solid #333; }
        #receipt-items-table td { border-bottom: 1px dashed #ccc; }
        #receipt-total { margin-top: 20px; font-size: 1.5rem; font-weight: bold; text-align: right; }

        /* Printable Receipt Styles */
        @media print {
            body * { visibility: hidden; }
            #printable-receipt, #printable-receipt * { visibility: visible; }
            #printable-receipt { position: absolute; left: 0; top: 0; width: 100%; }
        }
        #printable-receipt { display: none; font-family: 'Courier New', Courier, monospace; width: 300px; font-size: 14px; }
        #printable-receipt h2 { font-size: 20px; text-align: center; margin: 0; }
        #printable-receipt p { text-align: center; margin: 2px 0; }
        #printable-receipt table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        #printable-receipt th, #printable-receipt td { padding: 5px 2px; }
        #printable-receipt th { border-bottom: 1px solid #000; font-weight: bold; }
        #printable-receipt .text-right { text-align: right; }
        #printable-receipt .total-row td { border-top: 1px solid #000; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <header>
             <div class="header-content">
                <div class="logo">
                    <i class="fas fa-cash-register"></i>
                    <div class="logo-text">
                        <h1>POS System</h1>
                        <p>Remkon Store</p>
                    </div>
                </div>
            </div>
        </header>

        <div class="layout">
            <div class="products-section">
                <div class="products-header">
                    <h2><i class="fas fa-boxes"></i> Products</h2>
                    <div class="search-bar">
                        <i class="fas fa-search"></i>
                        <input type="text" id="product-search" class="search-input" placeholder="Search by name or barcode...">
                    </div>
                </div>
                <div class="products-grid" id="products-grid">
                    <?php if ($products_result && $products_result->num_rows > 0): ?>
                        <?php while ($product = $products_result->fetch_assoc()): ?>
                            <div class="product-card" 
                                 data-product-id="<?= $product['id'] ?>" 
                                 data-product-name="<?= htmlspecialchars($product['product_name']) ?>" 
                                 data-product-barcode="<?= htmlspecialchars($product['barcode']) ?>"
                                 data-cost-price="<?= $product['cost_price'] ?>"
                                 data-pieces-per-carton="<?= $product['pieces_per_carton'] ?? 0 ?>">
                                <div class="product-name"><?= htmlspecialchars($product['product_name']) ?></div>
                                <div class="product-price">Ref Price: ₦<?= number_format($product['price'], 2) ?></div>
                                <div class="product-stock"><?= $product['stock_quantity'] ?> in stock</div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No products in stock.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="cart-container">
                <div class="cart-header">
                    <h2><i class="fas fa-shopping-cart"></i> Current Sale</h2>
                </div>
                <div class="cart-body" id="cart-items">
                     <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Your cart is empty</h3>
                        <p>Click on a product to start a sale</p>
                    </div>
                </div>
                <div class="cart-footer">
                    <div class="cart-total-section">
                        <span>Total:</span>
                        <span id="cart-total">₦0.00</span>
                    </div>
                    <form id="sale-form" method="POST">
                        <div class="form-group">
                            <label for="customer_id">Customer</label>
                            <input list="customers-list" id="customer_input" class="form-control" placeholder="Search for customer...">
                            <datalist id="customers-list">
                                <?php while($customer = $customers_result->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($customer['name']) ?> (<?= htmlspecialchars($customer['phone']) ?>)" data-id="<?= $customer['id'] ?>">
                                <?php endwhile; ?>
                            </datalist>
                            <input type="hidden" name="customer_id" id="customer_id">
                        </div>
                        <div class="form-group">
                            <label for="payment_method">Payment Method</label>
                            <select name="payment_method" id="payment_method" class="form-control">
                                <option value="cash">Cash</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="debit_card">Debit Card</option>
                                <option value="mobile_payment">Mobile Payment</option>
                            </select>
                        </div>
                        <div class="btn-group">
                            <button type="button" id="clear-cart-btn" class="btn btn-danger"><i class="fas fa-trash"></i> Clear</button>
                            <button type="submit" name="process_sale" class="btn btn-success"><i class="fas fa-check-circle"></i> Process Sale</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="pos-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-product-name">Product Name</h2>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 15px; font-size: 1.1rem;">Cost Price: <strong id="modal-cost-price" style="color: var(--danger);"></strong></p>
                <div class="form-group">
                    <label for="modal-quantity">Quantity</label>
                    <input type="number" id="modal-quantity" class="form-control" value="1" min="1">
                </div>
                <div class="form-group">
                    <label for="modal-price">Selling Price (per piece)</label>
                    <input type="number" id="modal-price" class="form-control" step="0.01" min="0" placeholder="Enter manual price">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                <button type="button" id="add-pieces-btn" class="btn btn-primary">Add as Pieces</button>
                <button type="button" id="add-carton-btn" class="btn btn-success" style="display: none;">Add as Carton</button>
            </div>
        </div>
    </div>
    
    <!-- Receipt Modal -->
    <div id="receipt-modal" class="modal-overlay">
        <div id="receipt-modal-content" class="modal-content">
            <div class="modal-header">
                <h2>Receipt</h2>
                <button type="button" class="modal-close">&times;</button>
            </div>
            <div id="receipt-body" class="modal-body">
                <!-- Receipt content will be injected here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close">New Sale</button>
                <button type="button" id="print-receipt-btn" class="btn btn-primary"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>
    </div>

    <!-- Printable Receipt -->
    <div id="printable-receipt"></div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Element Selectors ---
        const searchInput = document.getElementById('product-search');
        const productCards = document.querySelectorAll('.product-card');
        const cartItemsContainer = document.getElementById('cart-items');
        const cartTotalEl = document.getElementById('cart-total');
        const clearCartBtn = document.getElementById('clear-cart-btn');
        const saleForm = document.getElementById('sale-form');
        const customerInput = document.getElementById('customer_input');
        const customerIdInput = document.getElementById('customer_id');
        const customersDatalist = document.getElementById('customers-list');
        
        // Add Modal
        const posModal = document.getElementById('pos-modal');
        const posModalCloseBtns = posModal.querySelectorAll('.modal-close');
        const modalProductNameEl = document.getElementById('modal-product-name');
        const modalCostPriceEl = document.getElementById('modal-cost-price');
        const modalQuantityInput = document.getElementById('modal-quantity');
        const modalPriceInput = document.getElementById('modal-price');
        const addPiecesBtn = document.getElementById('add-pieces-btn');
        const addCartonBtn = document.getElementById('add-carton-btn');

        // Receipt Modal
        const receiptModal = document.getElementById('receipt-modal');
        const receiptModalCloseBtns = receiptModal.querySelectorAll('.modal-close');
        const receiptBody = document.getElementById('receipt-body');
        const printReceiptBtn = document.getElementById('print-receipt-btn');
        const printableReceiptContainer = document.getElementById('printable-receipt');

        let currentProduct = null;

        // --- API Functions ---
        const apiRequest = async (endpoint, method, body) => {
            try {
                const options = {
                    method,
                    headers: { 'Content-Type': 'application/json' },
                };
                if (body) {
                    options.body = JSON.stringify(body);
                }
                const response = await fetch(endpoint, options);
                if (!response.ok) {
                    const errorResult = await response.json();
                    throw new Error(errorResult.error || `HTTP error! status: \${response.status}`);
                }
                return await response.json();
            } catch (error) {
                console.error(\`API Error (\${method} \${endpoint}):\`, error);
                alert(\`An error occurred: \${error.message}\`);
                return null;
            }
        };

        const updateCartDisplay = async () => {
            const data = await apiRequest('../api/cart.php', 'GET');
            if (!data || !data.success) return;

            cartItemsContainer.innerHTML = '';
            if (data.cart.length === 0) {
                cartItemsContainer.innerHTML = \`<div class="empty-cart"><i class="fas fa-shopping-cart"></i><h3>Your cart is empty</h3><p>Click on a product to start a sale</p></div>\`;
            } else {
                data.cart.forEach(item => {
                    const itemEl = document.createElement('div');
                    itemEl.classList.add('cart-item');
                    itemEl.innerHTML = \`
                        <div class="cart-item-details">
                            <div class="cart-item-name">\${item.name}</div>
                            <div class="cart-item-sub">\${item.quantity} x ₦\${item.price.toFixed(2)}</div>
                        </div>
                        <div class="cart-item-total">₦\${(item.quantity * item.price).toFixed(2)}</div>
                        <button class="cart-item-remove" data-id="\${item.id}" data-price="\${item.price}">&times;</button>
                    \`;
                    cartItemsContainer.appendChild(itemEl);
                });
            }
            cartTotalEl.textContent = \`₦\${data.total.toFixed(2)}\`;
        };

        const addToCart = async (isCarton) => {
            const quantity = parseInt(modalQuantityInput.value, 10);
            const price = parseFloat(modalPriceInput.value);

            if (isNaN(quantity) || quantity <= 0) { alert('Please enter a valid quantity.'); return; }
            if (isNaN(price) || price <= 0) { alert('Please enter a valid selling price.'); return; }

            const itemData = {
                id: currentProduct.id,
                name: isCarton ? \`\${currentProduct.name} (Carton)\` : currentProduct.name,
                quantity: isCarton ? (quantity * currentProduct.piecesPerCarton) : quantity,
                price: price
            };
            
            const result = await apiRequest('../api/cart.php', 'POST', itemData);
            if (result && result.success) {
                updateCartDisplay();
                closePosModal();
            }
        };

        const removeFromCart = async (id, price) => {
            await apiRequest('../api/cart.php', 'DELETE', { id, price });
            updateCartDisplay();
        };

        const clearCart = async () => {
            if (!confirm('Are you sure you want to clear the entire cart?')) return;
            await apiRequest('../api/cart.php', 'DELETE', { clear_all: true });
            updateCartDisplay();
        };

        const processSale = async () => {
            const customerId = customerIdInput.value;
            const paymentMethod = document.getElementById('payment_method').value;

            const result = await apiRequest('../api/sale.php', 'POST', {
                customer_id: customerId,
                payment_method: paymentMethod
            });

            if (result && result.success) {
                showReceipt(result.receipt);
            }
        };

        const showReceipt = (receiptData) => {
            // Populate on-screen modal
            let itemsHtml = `
                <h2>${receiptData.store_name}</h2>
                <p>Receipt ID: ${receiptData.id}</p>
                <p>Date: ${new Date(receiptData.date).toLocaleString()}</p>
                <table id="receipt-items-table">
                    <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
                    <tbody>`;
            receiptData.items.forEach(item => {
                itemsHtml += `<tr>
                    <td>${item.name}</td>
                    <td>${item.quantity}</td>
                    <td>${item.price.toFixed(2)}</td>
                    <td>${(item.quantity * item.price).toFixed(2)}</td>
                </tr>`;
            });
            itemsHtml += `</tbody></table><div id="receipt-total">Total: ₦${receiptData.total.toFixed(2)}</div>`;
            receiptBody.innerHTML = itemsHtml;

            // Populate printable receipt
            let printableHtml = `
                <h2>${receiptData.store_name}</h2>
                <p>Date: ${new Date(receiptData.date).toLocaleString()}</p>
                <p>Receipt: ${receiptData.id}</p>
                <p>--------------------------------</p>
                <table>
                    <thead><tr><th>SN</th><th>ITEMS</th><th>QTY</th><th>U/PRICE</th><th>MOUNT(N)</th></tr></thead>
                    <tbody>`;
            let sn = 1;
            receiptData.items.forEach((item) => {
                printableHtml += `<tr>
                    <td>${sn++}</td>
                    <td>${item.name}</td>
                    <td>${item.quantity}</td>
                    <td class="text-right">${item.price.toFixed(2)}</td>
                    <td class="text-right">${(item.quantity * item.price).toFixed(2)}</td>
                </tr>`;
            });
            printableHtml += `
                    <tr class="total-row">
                        <td colspan="4">TOTAL</td>
                        <td class="text-right">₦${receiptData.total.toFixed(2)}</td>
                    </tr>
                    </tbody></table>
                <p>--------------------------------</p>
                <p>Thank you for your patronage!</p>`;
            printableReceiptContainer.innerHTML = printableHtml;
            
            receiptModal.style.display = 'flex';
        };

        const printReceipt = () => {
            window.print();
        };

        // --- Modals ---
        const openPosModal = (productCard) => {
            currentProduct = {
                id: productCard.dataset.productId,
                name: productCard.dataset.productName,
                costPrice: parseFloat(productCard.dataset.costPrice),
                piecesPerCarton: parseInt(productCard.dataset.piecesPerCarton, 10) || 0
            };
            modalProductNameEl.textContent = currentProduct.name;
            modalCostPriceEl.textContent = \`₦\${currentProduct.costPrice.toFixed(2)}\`;
            modalQuantityInput.value = 1;
            modalPriceInput.value = '';
            addCartonBtn.style.display = currentProduct.piecesPerCarton > 0 ? 'inline-flex' : 'none';
            posModal.style.display = 'flex';
            modalQuantityInput.focus();
        };

        const closePosModal = () => {
            posModal.style.display = 'none';
        };
        
        const closeReceiptModal = () => {
            receiptModal.style.display = 'none';
            window.location.reload(); // Start fresh for next sale
        }

        // --- Event Listeners ---
        productCards.forEach(card => card.addEventListener('click', () => openPosModal(card)));
        posModalCloseBtns.forEach(btn => btn.addEventListener('click', closePosModal));
        receiptModalCloseBtns.forEach(btn => btn.addEventListener('click', closeReceiptModal));
        printReceiptBtn.addEventListener('click', printReceipt);
        addPiecesBtn.addEventListener('click', () => addToCart(false));
        addCartonBtn.addEventListener('click', () => addToCart(true));
        clearCartBtn.addEventListener('click', clearCart);
        saleForm.addEventListener('submit', (e) => {
            e.preventDefault();
            processSale();
        });

        cartItemsContainer.addEventListener('click', (e) => {
            if (e.target.classList.contains('cart-item-remove')) {
                const id = e.target.dataset.id;
                const price = e.target.dataset.price;
                removeFromCart(id, price);
            }
        });
        
        searchInput.addEventListener('keyup', () => {
            const filter = searchInput.value.toLowerCase();
            productCards.forEach(card => {
                const name = card.dataset.productName.toLowerCase();
                const barcode = card.dataset.productBarcode.toLowerCase();
                card.style.display = (name.includes(filter) || barcode.includes(filter)) ? "" : "none";
            });
        });
        
        customerInput.addEventListener('input', () => {
            const selectedOption = Array.from(customersDatalist.options).find(o => o.value === customerInput.value);
            customerIdInput.value = selectedOption ? selectedOption.dataset.id : '';
        });

        // --- Initial Load ---
        updateCartDisplay();
    });
    </script>
</body>
</html>
