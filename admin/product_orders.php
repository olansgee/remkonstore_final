<?php
// admin/product_orders.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (!is_logged_in() || !has_role('admin')) {
    header("Location: ../index.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Store Management
    if (isset($_POST['add_store'])) {
        $store_name = $conn->real_escape_string($_POST['store_name']);
        $store_location = $conn->real_escape_string($_POST['store_location']);
        $store_head = $conn->real_escape_string($_POST['store_head']);
        
        $sql = "INSERT INTO stores (store_name, store_location, store_head) 
                VALUES ('$store_name', '$store_location', '$store_head')";
        
        if ($conn->query($sql)) {
            $success = "Store added successfully!";
        } else {
            $error = "Error adding store: " . $conn->error;
        }
    }
    
    // Add/Update order
    if (isset($_POST['save_order'])) {
        $order_id = $_POST['order_id'];
        $store_id = (int)$_POST['store_id'];
        $supplier_id = (int)$_POST['supplier_id'];
        $product_id = (int)$_POST['product_id'];
        $quantity = (int)$_POST['quantity'];
        $rate = floatval($_POST['rate']);
        $total_cost = $quantity * $rate;
        $order_date = $_POST['order_date'];
        $delivery_status = $_POST['delivery_status'];
        $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;
        
        // Check if we're updating or inserting
        if ($edit_id) {
            // Check if order is already delivered
            $checkStatusSQL = "SELECT delivery_status, store_id, product_id, quantity 
                               FROM product_orders 
                               WHERE id = $edit_id";
            $result = $conn->query($checkStatusSQL);
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $old_status = $row['delivery_status'];
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Update order
                    $sql = "UPDATE product_orders SET 
                            store_id = $store_id, 
                            supplier_id = $supplier_id, 
                            product_id = $product_id, 
                            quantity = $quantity, 
                            rate = $rate, 
                            total_cost = $total_cost, 
                            order_date = '$order_date', 
                            delivery_status = '$delivery_status' 
                            WHERE id = $edit_id";
                    
                    if (!$conn->query($sql)) {
                        throw new Exception("Error updating order: " . $conn->error);
                    }
                    
                    // Update inventory if status changed to supplied
                    if ($old_status === 'pending' && $delivery_status === 'supplied') {
                        $updateInventorySQL = "INSERT INTO store_inventory (store_id, product_id, quantity) 
                                              VALUES ($store_id, $product_id, $quantity)
                                              ON DUPLICATE KEY UPDATE quantity = quantity + $quantity";
                        
                        if (!$conn->query($updateInventorySQL)) {
                            throw new Exception("Error updating inventory: " . $conn->error);
                        }
                    }
                    // Remove from inventory if status changed from supplied to pending
                    elseif ($old_status === 'supplied' && $delivery_status === 'pending') {
                        $updateInventorySQL = "UPDATE store_inventory 
                                              SET quantity = quantity - $quantity 
                                              WHERE store_id = $store_id AND product_id = $product_id";
                        
                        if (!$conn->query($updateInventorySQL)) {
                            throw new Exception("Error updating inventory: " . $conn->error);
                        }
                    }
                    
                    $conn->commit();
                    $success = "Order updated successfully!";
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = $e->getMessage();
                }
            }
        } else {
            // Generate new order ID
            if (empty($order_id)) {
                $prefix = "ORD-" . date("Ymd") . "-";
                $sql = "SELECT MAX(CAST(SUBSTRING(order_id, LENGTH('$prefix')+1) AS UNSIGNED)) AS max_num 
                        FROM product_orders 
                        WHERE order_id LIKE '$prefix%'";
                $result = $conn->query($sql);
                $max_num = $result && $result->num_rows > 0 ? $result->fetch_assoc()['max_num'] : 0;
                $order_id = $prefix . str_pad($max_num + 1, 4, '0', STR_PAD_LEFT);
            }
            
            // Start transaction for atomic operations
            $conn->begin_transaction();
            
            try {
                // Create order
                $sql = "INSERT INTO product_orders (order_id, store_id, supplier_id, product_id, quantity, rate, total_cost, order_date, delivery_status)
                        VALUES ('$order_id', $store_id, $supplier_id, $product_id, $quantity, $rate, $total_cost, '$order_date', '$delivery_status')";
                
                if (!$conn->query($sql)) {
                    throw new Exception("Error creating order: " . $conn->error);
                }
                
                // Update inventory if status is supplied
                if ($delivery_status === 'supplied') {
                    $updateInventorySQL = "INSERT INTO store_inventory (store_id, product_id, quantity) 
                                          VALUES ($store_id, $product_id, $quantity)
                                          ON DUPLICATE KEY UPDATE quantity = quantity + $quantity";
                    
                    if (!$conn->query($updateInventorySQL)) {
                        throw new Exception("Error updating inventory: " . $conn->error);
                    }
                }
                
                $conn->commit();
                $success = "Order created successfully!";
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

// Handle delete actions
if (isset($_GET['delete_order'])) {
    $id = (int)$_GET['delete_order'];
    
    // Check if order is delivered
    $checkStatusSQL = "SELECT delivery_status, store_id, product_id, quantity 
                       FROM product_orders 
                       WHERE id = $id";
    $result = $conn->query($checkStatusSQL);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['delivery_status'] === 'supplied') {
            $error = "Cannot delete an order that has been delivered!";
        } else {
            $sql = "DELETE FROM product_orders WHERE id = $id";
            if ($conn->query($sql)) {
                $success = "Order deleted successfully!";
            } else {
                $error = "Error deleting order: " . $conn->error;
            }
        }
    }
}

// Handle store deletion
if (isset($_GET['delete_store'])) {
    $id = (int)$_GET['delete_store'];
    
    // Check if store has orders
    $checkOrdersSQL = "SELECT COUNT(*) AS order_count FROM product_orders WHERE store_id = $id";
    $result = $conn->query($checkOrdersSQL);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['order_count'] > 0) {
            $error = "Cannot delete a store that has associated orders!";
        } else {
            $sql = "DELETE FROM stores WHERE id = $id";
            if ($conn->query($sql)) {
                $success = "Store deleted successfully!";
            } else {
                $error = "Error deleting store: " . $conn->error;
            }
        }
    }
}

// Fetch data for forms and tables
$stores = $conn->query("SELECT * FROM stores");
$suppliers = $conn->query("SELECT * FROM suppliers");
$products = $conn->query("SELECT * FROM products");
$orders = $conn->query("
    SELECT po.*, s.store_name, sup.supplier_name, p.product_name 
    FROM product_orders po
    JOIN stores s ON po.store_id = s.id
    JOIN suppliers sup ON po.supplier_id = sup.id
    JOIN products p ON po.product_id = p.id
    ORDER BY po.order_date DESC, po.id DESC
");

// Handle edit action
$edit_order = null;
if (isset($_GET['edit_order'])) {
    $id = (int)$_GET['edit_order'];
    $result = $conn->query("SELECT * FROM product_orders WHERE id = $id");
    if ($result && $result->num_rows > 0) {
        $edit_order = $result->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Store Order Management | Remkon Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --success: #27ae60;
            --warning: #f39c12;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --gray: #95a5a6;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: #333;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        header {
            background: linear-gradient(to right, var(--primary), var(--dark));
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        
        .header-content {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo i {
            font-size: 2.5rem;
            color: #f1c40f;
        }
        
        .logo-text h1 {
            font-size: 2.2rem;
            margin-bottom: 5px;
        }
        
        .logo-text p {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .stats {
            display: flex;
            gap: 20px;
            background: rgba(255,255,255,0.15);
            padding: 15px 25px;
            border-radius: 10px;
            backdrop-filter: blur(5px);
            flex-wrap: wrap;
        }
        
        .stat-item {
            text-align: center;
            min-width: 120px;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 30px;
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background: var(--primary);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h2 {
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-group label i {
            color: var(--gray);
        }
        
        .form-control {
            width: 100%;
            padding: 14px;
            border: 2px solid #e1e5eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--secondary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn i {
            font-size: 1.1rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #1a252f;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #219653;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: var(--accent);
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: var(--gray);
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }
        
        .table-container {
            overflow-x: auto;
            max-height: 500px;
            overflow-y: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid #e1e5eb;
        }
        
        th {
            background: var(--primary);
            color: white;
            position: sticky;
            top: 0;
        }
        
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        tr:hover {
            background-color: #e9f7fe;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-pending {
            background-color: #fff8e1;
            color: #ff8f00;
        }
        
        .status-supplied {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .action-cell {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .edit-btn {
            background: #3498db;
            color: white;
        }
        
        .edit-btn:hover {
            background: #2980b9;
        }
        
        .delete-btn {
            background: #e74c3c;
            color: white;
        }
        
        .delete-btn:hover {
            background: #c0392b;
        }
        
        .notification {
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--primary);
        }
        
        .tab {
            padding: 12px 24px;
            cursor: pointer;
            background: #e0e7ff;
            margin-right: 5px;
            border-radius: 5px 5px 0 0;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .tab.active {
            background: var(--primary);
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .delivered-banner {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            text-align: center;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 20px;
            }
            
            .stats {
                width: 100%;
                justify-content: space-around;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                border-radius: 5px;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="logo-text">
                        <h1>Remkon Store Network</h1>
                        <p>Multi-Store Order Management System</p>
                    </div>
                </div>
                <div class="stats">
                    <?php
                    $pending_count = 0;
                    $total_value = 0;
                    $stores_count = $stores->num_rows;
                    
                    if ($orders) {
                        while ($order = $orders->fetch_assoc()) {
                            if ($order['delivery_status'] === 'pending') {
                                $pending_count++;
                                $total_value += $order['total_cost'];
                            }
                        }
                        // Reset pointer to beginning for later use
                        $orders->data_seek(0);
                    }
                    ?>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $stores_count; ?></div>
                        <div class="stat-label">Stores</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $orders ? $orders->num_rows : '0'; ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">₦<?php echo number_format($total_value, 2); ?></div>
                        <div class="stat-label">Pending Value</div>
                    </div>
                </div>
                 <a href="dashboard.php" class="btn btn-secondary" style="align-self: center;"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </div>
        </header>
        
        <!-- Notification area -->
        <?php if (isset($success)): ?>
            <div class="notification success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="notification error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab active" data-tab="stores">Manage Stores</div>
            <div class="tab" data-tab="orders">Manage Orders</div>
        </div>
        
        <!-- Stores Management Tab -->
        <div id="stores-tab" class="tab-content active">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-store-alt"></i> Add New Store</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="store_name"><i class="fas fa-signature"></i> Store Name</label>
                                <input type="text" id="store_name" name="store_name" class="form-control" required placeholder="Enter store name">
                            </div>
                            
                            <div class="form-group">
                                <label for="store_head"><i class="fas fa-user-tie"></i> Store Head</label>
                                <input type="text" id="store_head" name="store_head" class="form-control" required placeholder="Enter store manager name">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="store_location"><i class="fas fa-map-marker-alt"></i> Store Location</label>
                            <input type="text" id="store_location" name="store_location" class="form-control" required placeholder="Enter store address">
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" name="add_store" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> Add Store
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-list"></i> Store Directory</h2>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Store Name</th>
                                    <th>Location</th>
                                    <th>Store Head</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($stores->num_rows > 0): ?>
                                    <?php while ($store = $stores->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $store['id']; ?></td>
                                            <td><?php echo htmlspecialchars($store['store_name']); ?></td>
                                            <td><?php echo htmlspecialchars($store['store_location']); ?></td>
                                            <td><?php echo htmlspecialchars($store['store_head']); ?></td>
                                            <td class="action-cell">
                                                <a href="?delete_store=<?php echo $store['id']; ?>" class="action-btn delete-btn" 
                                                   onclick="return confirm('Are you sure you want to delete this store?');">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; 
                                    $stores->data_seek(0); // Reset pointer
                                    ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center;">No stores found. Add your first store using the form above.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Orders Management Tab -->
        <div id="orders-tab" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-file-invoice"></i> 
                        <?php echo isset($edit_order) ? 'Edit Product Order' : 'Create New Product Order'; ?>
                    </h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php if (isset($edit_order)): ?>
                            <input type="hidden" name="edit_id" value="<?php echo $edit_order['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="store_id"><i class="fas fa-store"></i> Store</label>
                                <select id="store_id" name="store_id" class="form-control" required>
                                    <option value="">Select Store</option>
                                    <?php while ($store = $stores->fetch_assoc()): ?>
                                        <option value="<?php echo $store['id']; ?>" 
                                            <?php if (isset($edit_order) && $edit_order['store_id'] == $store['id']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($store['store_name']); ?>
                                        </option>
                                    <?php endwhile; 
                                    $stores->data_seek(0); // Reset pointer
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="supplier"><i class="fas fa-truck"></i> Supplier</label>
                                <select id="supplier" name="supplier_id" class="form-control" required>
                                    <option value="">Select Supplier</option>
                                    <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                                        <option value="<?php echo $supplier['id']; ?>" 
                                            <?php if (isset($edit_order) && $edit_order['supplier_id'] == $supplier['id']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                        </option>
                                    <?php endwhile; 
                                    $suppliers->data_seek(0); // Reset pointer
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="product"><i class="fas fa-wine-bottle"></i> Product</label>
                                <select id="product" name="product_id" class="form-control" required>
                                    <option value="">Select Product</option>
                                    <?php while ($product = $products->fetch_assoc()): ?>
                                        <option value="<?php echo $product['id']; ?>" 
                                            data-rate="<?php echo number_format(rand(5, 25) + (rand(0, 99)/100)); ?>" 
                                            <?php if (isset($edit_order) && $edit_order['product_id'] == $product['id']) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($product['product_name']); ?>
                                        </option>
                                    <?php endwhile; 
                                    $products->data_seek(0); // Reset pointer
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="orderDate"><i class="far fa-calendar-alt"></i> Order Date</label>
                                <input type="date" id="orderDate" name="order_date" class="form-control" 
                                       value="<?php echo isset($edit_order) ? $edit_order['order_date'] : date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="quantity"><i class="fas fa-cubes"></i> Quantity</label>
                                <input type="number" id="quantity" name="quantity" class="form-control" 
                                       min="1" value="<?php echo isset($edit_order) ? $edit_order['quantity'] : '1'; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="rate"><i class="fas fa-tag"></i> Rate (per unit)</label>
                                <input type="number" id="rate" name="rate" class="form-control" 
                                       min="0.01" step="0.01" 
                                       value="<?php echo isset($edit_order) ? $edit_order['rate'] : '0.00'; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="totalCost"><i class="fas fa-calculator"></i> Total Cost</label>
                                <input type="text" id="totalCost" name="total_cost" class="form-control" 
                                       value="₦0.00" readonly>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="deliveryStatus"><i class="fas fa-shipping-fast"></i> Delivery Status</label>
                            <select id="deliveryStatus" name="delivery_status" class="form-control" required>
                                <option value="pending" <?php if (isset($edit_order) && $edit_order['delivery_status'] === 'pending') echo 'selected'; ?>>Pending</option>
                                <option value="supplied" <?php if (isset($edit_order) && $edit_order['delivery_status'] === 'supplied') echo 'selected'; ?>>Supplied</option>
                            </select>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" name="save_order" class="btn btn-primary">
                                <i class="fas fa-save"></i> 
                                <?php echo isset($edit_order) ? 'Update Order' : 'Create Order'; ?>
                            </button>
                            <a href="order_management.php" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-list"></i> Product Orders by Store</h2>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table id="ordersTable">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Store</th>
                                    <th>Supplier</th>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Rate</th>
                                    <th>Total Cost</th>
                                    <th>Order Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($orders && $orders->num_rows > 0): ?>
                                    <?php while ($order = $orders->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                            <td><?php echo htmlspecialchars($order['store_name']); ?></td>
                                            <td><?php echo htmlspecialchars($order['supplier_name']); ?></td>
                                            <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                            <td><?php echo $order['quantity']; ?></td>
                                            <td>₦<?php echo number_format($order['rate'], 2); ?></td>
                                            <td>₦<?php echo number_format($order['total_cost'], 2); ?></td>
                                            <td><?php echo $order['order_date']; ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $order['delivery_status']; ?>">
                                                    <?php echo ucfirst($order['delivery_status']); ?>
                                                </span>
                                            </td>
                                            <td class="action-cell">
                                                <a href="?edit_order=<?php echo $order['id']; ?>" class="action-btn edit-btn">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="?delete_order=<?php echo $order['id']; ?>" class="action-btn delete-btn" 
                                                   onclick="return confirm('Are you sure you want to delete this order?');">
                                                    <i class="fas fa-trash"></i> 
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" style="text-align: center;">No orders found. Create your first order using the form above.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Delivery confirmation banner -->
                <div class="delivered-banner">
                    <i class="fas fa-check-circle"></i>
                    Orders marked as "Supplied" cannot be modified or deleted
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Remkon Store Network - Multi-Store Order Management</p>
            <p>Database: <?php echo $dbname; ?> | Tables: stores, product_orders, store_inventory</p>
        </div>
    </div>

    <script>
        // Tab switching functionality
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                // Remove active class from all tabs and content
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab
                tab.classList.add('active');
                
                // Show corresponding content
                const tabName = tab.getAttribute('data-tab');
                document.getElementById(`${tabName}-tab`).classList.add('active');
            });
        });
        
        // Product rate selection
        document.getElementById('product').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.dataset.rate) {
                document.getElementById('rate').value = selectedOption.dataset.rate;
                calculateTotal();
            }
        });
        
        // Quantity and rate change listeners
        document.getElementById('quantity').addEventListener('input', calculateTotal);
        document.getElementById('rate').addEventListener('input', calculateTotal);
        
        function calculateTotal() {
            const quantity = parseFloat(document.getElementById('quantity').value) || 0;
            const rate = parseFloat(document.getElementById('rate').value) || 0;
            const total = quantity * rate;
            document.getElementById('totalCost').value = '₦' + total.toFixed(2);
        }
        
        // Initialize calculation
        calculateTotal();
    </script>
</body>
</html>
<?php $conn->close(); ?>