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
    // Process product transfer
    if (isset($_POST['transfer_products'])) {
        $product_id = (int)$_POST['product_id'];
        $from_store_id = (int)$_POST['from_store_id'];
        $to_store_id = (int)$_POST['to_store_id'];
        $quantity = (int)$_POST['quantity'];
        
        // Check if stores are different
        if ($from_store_id == $to_store_id) {
            $error = "Cannot transfer to the same store!";
        } else {
            // Check if from store has enough inventory
            $checkInventory = $conn->prepare("SELECT quantity FROM store_inventory 
                                              WHERE store_id = ? AND product_id = ?");
            $checkInventory->bind_param("ii", $from_store_id, $product_id);
            $checkInventory->execute();
            $inventoryResult = $checkInventory->get_result();
            
            if ($inventoryResult->num_rows > 0) {
                $row = $inventoryResult->fetch_assoc();
                $current_quantity = $row['quantity'];
                
                if ($current_quantity >= $quantity) {
                    // Start transaction
                    $conn->begin_transaction();
                    
                    try {
                        // Deduct from source store
                        $deductSQL = "UPDATE store_inventory 
                                     SET quantity = quantity - ? 
                                     WHERE store_id = ? AND product_id = ?";
                        $stmt = $conn->prepare($deductSQL);
                        $stmt->bind_param("iii", $quantity, $from_store_id, $product_id);
                        $stmt->execute();
                        
                        // Add to destination store
                        // Check if destination has the product
                        $checkDest = $conn->prepare("SELECT id FROM store_inventory 
                                                    WHERE store_id = ? AND product_id = ?");
                        $checkDest->bind_param("ii", $to_store_id, $product_id);
                        $checkDest->execute();
                        $destResult = $checkDest->get_result();
                        
                        if ($destResult->num_rows > 0) {
                            // Update existing inventory
                            $addSQL = "UPDATE store_inventory 
                                      SET quantity = quantity + ? 
                                      WHERE store_id = ? AND product_id = ?";
                            $stmt = $conn->prepare($addSQL);
                            $stmt->bind_param("iii", $quantity, $to_store_id, $product_id);
                        } else {
                            // Insert new inventory record
                            $addSQL = "INSERT INTO store_inventory (store_id, product_id, quantity)
                                      VALUES (?, ?, ?)";
                            $stmt = $conn->prepare($addSQL);
                            $stmt->bind_param("iii", $to_store_id, $product_id, $quantity);
                        }
                        $stmt->execute();
                        
                        // Record the transfer
                        $transferSQL = "INSERT INTO store_transfers 
                                       (product_id, from_store_id, to_store_id, quantity)
                                       VALUES (?, ?, ?, ?)";
                        $stmt = $conn->prepare($transferSQL);
                        $stmt->bind_param("iiii", $product_id, $from_store_id, $to_store_id, $quantity);
                        $stmt->execute();
                        
                        // Commit transaction
                        $conn->commit();
                        $success = "Product transfer completed successfully!";
                    } catch (Exception $e) {
                        // Rollback on error
                        $conn->rollback();
                        $error = "Error processing transfer: " . $e->getMessage();
                    }
                } else {
                    $error = "Insufficient inventory in source store!";
                }
            } else {
                $error = "Product not found in source store inventory!";
            }
        }
    }
}

// Fetch data for forms and tables
$stores = $conn->query("SELECT * FROM stores");
$products = $conn->query("SELECT * FROM products");

// Get current inventory
$inventory = [];
$inventoryQuery = $conn->query("
    SELECT si.store_id, si.product_id, si.quantity, 
           s.store_name, p.product_name 
    FROM store_inventory si
    JOIN stores s ON si.store_id = s.id
    JOIN products p ON si.product_id = p.id
    ORDER BY s.store_name, p.product_name
");

while ($row = $inventoryQuery->fetch_assoc()) {
    $inventory[$row['store_id']][$row['product_id']] = $row;
}

// Get transfer history
$transfers = $conn->query("
    SELECT t.*, 
           p.product_name,
           f.store_name AS from_store,
           f.store_location AS from_location,
           tos.store_name AS to_store,
           tos.store_location AS to_location
    FROM store_transfers t
    JOIN products p ON t.product_id = p.id
    JOIN stores f ON t.from_store_id = f.id
    JOIN stores tos ON t.to_store_id = tos.id
    ORDER BY t.transfer_date DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inter-Store Product Transfer | Remkon Store Network</title>
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
        
        .status-completed {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .action-cell {
            display: flex;
            gap: 10px;
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
        
        .inventory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .inventory-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            padding: 20px;
            transition: all 0.3s;
            border-left: 4px solid var(--secondary);
        }
        
        .inventory-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        
        .store-name {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .store-location {
            color: var(--gray);
            margin-bottom: 15px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .inventory-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .inventory-item:last-child {
            border-bottom: none;
        }
        
        .product-name {
            font-weight: 500;
        }
        
        .product-quantity {
            font-weight: bold;
            color: var(--dark);
        }
        
        .transfer-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
            border-left: 4px solid var(--success);
        }
        
        .transfer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .transfer-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--dark);
        }
        
        .transfer-date {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        .transfer-details {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .transfer-direction {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 1.1rem;
            color: var(--primary);
            font-weight: 500;
        }
        
        .transfer-arrow {
            color: var(--accent);
            font-size: 1.5rem;
        }
        
        .transfer-store {
            background: #e3f2fd;
            padding: 10px 15px;
            border-radius: 8px;
            min-width: 200px;
        }
        
        .store-label {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 5px;
        }
        
        .store-value {
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .transfer-product {
            display: flex;
            justify-content: space-between;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
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
            
            .transfer-details {
                flex-direction: column;
                gap: 10px;
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
                        <i class="fas fa-warehouse"></i>
                    </div>
                    <div class="logo-text">
                        <h1>Remkon Store Network</h1>
                        <p>Inter-Store Product Transfer System</p>
                    </div>
                </div>
                <div class="stats">
                    <?php
                    $total_stores = $stores->num_rows;
                    $total_products = $products->num_rows;
                    $total_transfers = $transfers->num_rows;
                    ?>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $total_stores; ?></div>
                        <div class="stat-label">Stores</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $total_products; ?></div>
                        <div class="stat-label">Products</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $total_transfers; ?></div>
                        <div class="stat-label">Transfers</div>
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
            <div class="tab active" data-tab="inventory">Current Inventory</div>
            <div class="tab" data-tab="transfer">Transfer Products</div>
            <div class="tab" data-tab="history">Transfer History</div>
        </div>
        
        <!-- Inventory Tab -->
        <div id="inventory-tab" class="tab-content active">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-boxes"></i> Store Inventory Overview</h2>
                </div>
                <div class="card-body">
                    <?php if ($stores->num_rows > 0 && $products->num_rows > 0): ?>
                        <div class="inventory-grid">
                            <?php while ($store = $stores->fetch_assoc()): ?>
                                <div class="inventory-card">
                                    <div class="store-name">
                                        <i class="fas fa-store"></i>
                                        <?php echo htmlspecialchars($store['store_name']); ?>
                                    </div>
                                    <div class="store-location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars($store['store_location']); ?>
                                    </div>
                                    
                                    <?php if (isset($inventory[$store['id']])): ?>
                                        <?php foreach ($inventory[$store['id']] as $product): ?>
                                            <div class="inventory-item">
                                                <div class="product-name">
                                                    <i class="fas fa-wine-bottle"></i>
                                                    <?php echo htmlspecialchars($product['product_name']); ?>
                                                </div>
                                                <div class="product-quantity">
                                                    <?php echo $product['quantity']; ?> units
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="inventory-item">
                                            <p>No inventory recorded for this store</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; 
                            $stores->data_seek(0); // Reset pointer
                            ?>
                        </div>
                    <?php else: ?>
                        <p>No stores or products found. Please add stores and products first.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Transfer Tab -->
        <div id="transfer-tab" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-exchange-alt"></i> Transfer Products Between Stores</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="from_store_id"><i class="fas fa-arrow-circle-up"></i> From Store</label>
                                <select id="from_store_id" name="from_store_id" class="form-control" required>
                                    <option value="">Select Source Store</option>
                                    <?php while ($store = $stores->fetch_assoc()): ?>
                                        <option value="<?php echo $store['id']; ?>">
                                            <?php echo htmlspecialchars($store['store_name']); ?> (<?php echo htmlspecialchars($store['store_location']); ?>)
                                        </option>
                                    <?php endwhile; 
                                    $stores->data_seek(0); // Reset pointer
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="to_store_id"><i class="fas fa-arrow-circle-down"></i> To Store</label>
                                <select id="to_store_id" name="to_store_id" class="form-control" required>
                                    <option value="">Select Destination Store</option>
                                    <?php while ($store = $stores->fetch_assoc()): ?>
                                        <option value="<?php echo $store['id']; ?>">
                                            <?php echo htmlspecialchars($store['store_name']); ?> (<?php echo htmlspecialchars($store['store_location']); ?>)
                                        </option>
                                    <?php endwhile; 
                                    $stores->data_seek(0); // Reset pointer
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="product_id"><i class="fas fa-wine-bottle"></i> Product</label>
                                <select id="product_id" name="product_id" class="form-control" required>
                                    <option value="">Select Product to Transfer</option>
                                    <?php while ($product = $products->fetch_assoc()): ?>
                                        <option value="<?php echo $product['id']; ?>">
                                            <?php echo htmlspecialchars($product['product_name']); ?>
                                        </option>
                                    <?php endwhile; 
                                    $products->data_seek(0); // Reset pointer
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="quantity"><i class="fas fa-cubes"></i> Quantity</label>
                                <input type="number" id="quantity" name="quantity" class="form-control" 
                                       min="1" value="1" required>
                            </div>
                        </div>
                        
                        <div class="btn-group">
                            <button type="submit" name="transfer_products" class="btn btn-primary">
                                <i class="fas fa-exchange-alt"></i> Transfer Products
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- History Tab -->
        <div id="history-tab" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Product Transfer History</h2>
                </div>
                <div class="card-body">
                    <?php if ($transfers->num_rows > 0): ?>
                        <?php while ($transfer = $transfers->fetch_assoc()): ?>
                            <div class="transfer-card">
                                <div class="transfer-header">
                                    <div class="transfer-title">
                                        Product Transfer #<?php echo $transfer['id']; ?>
                                    </div>
                                    <div class="transfer-date">
                                        <i class="far fa-calendar-alt"></i> 
                                        <?php echo date('M d, Y h:i A', strtotime($transfer['transfer_date'])); ?>
                                    </div>
                                </div>
                                
                                <div class="transfer-details">
                                    <div class="transfer-store">
                                        <div class="store-label">From Store:</div>
                                        <div class="store-value">
                                            <?php echo htmlspecialchars($transfer['from_store']); ?>
                                        </div>
                                        <div class="store-location">
                                            <i class="fas fa-map-marker-alt"></i> 
                                            <?php echo htmlspecialchars($transfer['from_location']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="transfer-direction">
                                        <i class="fas fa-long-arrow-alt-right transfer-arrow"></i>
                                        Transfer
                                    </div>
                                    
                                    <div class="transfer-store">
                                        <div class="store-label">To Store:</div>
                                        <div class="store-value">
                                            <?php echo htmlspecialchars($transfer['to_store']); ?>
                                        </div>
                                        <div class="store-location">
                                            <i class="fas fa-map-marker-alt"></i> 
                                            <?php echo htmlspecialchars($transfer['to_location']); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="transfer-product">
                                    <div>
                                        <div class="store-label">Product:</div>
                                        <div class="store-value">
                                            <i class="fas fa-wine-bottle"></i> 
                                            <?php echo htmlspecialchars($transfer['product_name']); ?>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="store-label">Quantity:</div>
                                        <div class="store-value">
                                            <?php echo $transfer['quantity']; ?> units
                                        </div>
                                    </div>
                                    <div>
                                        <div class="store-label">Status:</div>
                                        <div class="status-badge status-<?php echo $transfer['status']; ?>">
                                            <?php echo ucfirst($transfer['status']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No transfer history found. Transfer products to see history here.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Remkon Store Network - Inter-Store Transfer System</p>
            <p>Database: <?php echo $dbname; ?> | Tables: stores, products, store_inventory, store_transfers</p>
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
        
        // Form validation for transfer
        const transferForm = document.querySelector('form');
        if (transferForm) {
            transferForm.addEventListener('submit', function(e) {
                const fromStore = document.getElementById('from_store_id');
                const toStore = document.getElementById('to_store_id');
                
                if (fromStore.value === toStore.value) {
                    e.preventDefault();
                    alert('Cannot transfer to the same store! Please select different stores.');
                }
            });
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>