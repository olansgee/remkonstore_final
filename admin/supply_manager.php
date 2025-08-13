<?php
// admin/product_orders.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (!is_logged_in() || !has_role('admin')) {
    header("Location: ../index.php");
    exit();
}

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ADD SUPPLY RELATIONSHIP
    if (isset($_POST['add_supply'])) {
        $supplier_id = (int)$_POST['supplier_id'];
        $product_id = (int)$_POST['product_id'];
        
        $sql = "INSERT INTO supplier_products (supplier_id, product_id)
                VALUES ($supplier_id, $product_id)";
        $conn->query($sql);
    }
    
    // DELETE SUPPLY RELATIONSHIP
    if (isset($_GET['delete_supply'])) {
        $id = (int)$_GET['delete_supply'];
        $sql = "DELETE FROM supplier_products WHERE id = $id";
        $conn->query($sql);
    }
}

// Fetch all suppliers
$suppliers = $conn->query("SELECT * FROM suppliers");

// Fetch all products
$products = $conn->query("SELECT * FROM products");

// Fetch existing supply relationships
$supply_relationships = $conn->query("
    SELECT sp.id, s.supplier_name, p.product_name 
    FROM supplier_products sp
    JOIN suppliers s ON sp.supplier_id = s.id
    JOIN products p ON sp.product_id = p.id
");

// Fetch suppliers with their products
$suppliers_with_products = $conn->query("
    SELECT s.id, s.supplier_name, 
           GROUP_CONCAT(p.product_name SEPARATOR ', ') AS products_supplied
    FROM suppliers s
    LEFT JOIN supplier_products sp ON s.id = sp.supplier_id
    LEFT JOIN products p ON sp.product_id = p.id
    GROUP BY s.id
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Supply Manager - Remkon Store</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        header h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        header p {
            opacity: 0.9;
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
        
        .form-control {
            width: 100%;
            padding: 14px;
            border: 2px solid #e1e5eb;
            border-radius: 10px;
            font-size: 1rem;
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
        
        .btn-delete {
            background: var(--accent);
            color: white;
            padding: 8px 15px;
            font-size: 0.9rem;
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
        
        .product-badge {
            display: inline-block;
            background: #e1f5fe;
            color: #0288d1;
            padding: 5px 12px;
            border-radius: 20px;
            margin: 3px;
            font-size: 0.85rem;
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
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-handshake"></i> Products Supply Manager</h1>
            <a href="dashboard.php" class="btn btn-primary"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        </header>
        
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-link"></i> Add Supply Relationship</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="supplier_id"><i class="fas fa-truck"></i> Supplier</label>
                            <select id="supplier_id" name="supplier_id" class="form-control" required>
                                <option value="">-- Select Supplier --</option>
                                <?php while ($supplier = $suppliers->fetch_assoc()): ?>
                                    <option value="<?php echo $supplier['id']; ?>">
                                        <?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                    </option>
                                <?php endwhile; 
                                $suppliers->data_seek(0); // Reset pointer
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="product_id"><i class="fas fa-wine-bottle"></i> Product</label>
                            <select id="product_id" name="product_id" class="form-control" required>
                                <option value="">-- Select Product --</option>
                                <?php while ($product = $products->fetch_assoc()): ?>
                                    <option value="<?php echo $product['id']; ?>">
                                        <?php echo htmlspecialchars($product['product_name']); ?>
                                    </option>
                                <?php endwhile; 
                                $products->data_seek(0); // Reset pointer
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_supply" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Add Relationship
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> Current Supply Relationships</h2>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Supplier</th>
                                <th>Product</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($supply_relationships->num_rows > 0): ?>
                                <?php while($relation = $supply_relationships->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $relation['id'] ?></td>
                                        <td><?= htmlspecialchars($relation['supplier_name']) ?></td>
                                        <td><?= htmlspecialchars($relation['product_name']) ?></td>
                                        <td>
                                            <a href="?delete_supply=<?= $relation['id'] ?>" class="btn-delete" 
                                               onclick="return confirm('Are you sure?')">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">No supply relationships found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-truck-loading"></i> Suppliers and Products</h2>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Supplier Name</th>
                                <th>Products Supplied</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($suppliers_with_products->num_rows > 0): ?>
                                <?php while($supplier = $suppliers_with_products->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= $supplier['id'] ?></td>
                                        <td><?= htmlspecialchars($supplier['supplier_name']) ?></td>
                                        <td>
                                            <?php if ($supplier['products_supplied']): ?>
                                                <?php $products_list = explode(', ', $supplier['products_supplied']); ?>
                                                <?php foreach ($products_list as $product): ?>
                                                    <span class="product-badge"><?= htmlspecialchars($product) ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="product-badge">No products</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center;">No suppliers found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>