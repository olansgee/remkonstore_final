<?php
// admin/products.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Ensure the user is logged in and is an admin
if (!is_logged_in() || !has_role('admin')) {
    header("Location: ../index.php");
    exit();
}

$errors = [];
$success = '';
$search_term = '';

// Handle search
if (isset($_GET['search'])) {
    $search_term = trim($_GET['search']);
}

// Handle Delete Action
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success = "Product deleted successfully!";
    } else {
        $errors[] = "Error deleting product: " . $stmt->error;
    }
    $stmt->close();
}

// Handle form submissions for Add/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $category = trim($_POST['category']);
    $cost_price = floatval($_POST['cost_price']);
    $price = floatval($_POST['price']);
    $stock_quantity = intval($_POST['stock_quantity']);
    $barcode = trim($_POST['barcode']);
    $id = isset($_POST['id']) && !empty($_POST['id']) ? (int)$_POST['id'] : null;

    // Basic validation
    if (empty($name)) $errors[] = "Product name is required.";
    if (empty($category)) $errors[] = "Category is required.";
    if ($cost_price <= 0) $errors[] = "Cost price must be greater than zero.";
    if ($price <= 0) $errors[] = "Selling price must be greater than zero.";
    if ($stock_quantity < 0) $errors[] = "Stock quantity cannot be negative.";

    if (empty($errors)) {
        if ($id) {
            // Update Product
            $stmt = $conn->prepare("UPDATE products SET product_name = ?, product_description = ?, category = ?, cost_price = ?, price = ?, stock_quantity = ?, barcode = ? WHERE id = ?");
            $stmt->bind_param("sssdissi", $name, $desc, $category, $cost_price, $price, $stock_quantity, $barcode, $id);
            if ($stmt->execute()) {
                $success = "Product updated successfully!";
            } else {
                $errors[] = "Error updating product: " . $stmt->error;
            }
            $stmt->close();
        } else {
            // Add Product
            $stmt = $conn->prepare("INSERT INTO products (product_name, product_description, category, cost_price, price, stock_quantity, barcode) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssdiss", $name, $desc, $category, $cost_price, $price, $stock_quantity, $barcode);
            if ($stmt->execute()) {
                $success = "Product added successfully!";
            } else {
                $errors[] = "Error adding product: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Fetch all products with search functionality
$sql = "SELECT * FROM products";
$params = [];
$types = '';

if ($search_term) {
    $sql .= " WHERE product_name LIKE ? OR category LIKE ? OR product_description LIKE ? OR barcode LIKE ?";
    $like_search_term = "%" . $search_term . "%";
    $params = [$like_search_term, $like_search_term, $like_search_term, $like_search_term];
    $types = 'ssss';
}
$sql .= " ORDER BY product_name ASC";

$stmt = $conn->prepare($sql);
if ($search_term) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products_result = $stmt->get_result();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Manager - Remkon Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Using the same styles from the original file for consistency */
        :root { --primary: #2c3e50; --secondary: #3498db; --accent: #e74c3c; --success: #27ae60; --warning: #f39c12; --light: #ecf0f1; --dark: #2c3e50; --gray: #95a5a6; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); color: #333; min-height: 100vh; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        header { background: linear-gradient(to right, var(--primary), var(--dark)); color: white; padding: 25px; border-radius: 15px; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        header h1 { font-size: 2.2rem; display: flex; align-items: center; gap: 15px; }
        .btn-dashboard { background: var(--secondary); color: white; padding: 12px 20px; text-decoration: none; border-radius: 8px; transition: background 0.3s; font-weight: 600; }
        .btn-dashboard:hover { background: #2980b9; }
        .card { background: white; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 30px; }
        .card-header { background: var(--primary); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        .card-header h2 { font-size: 1.4rem; display: flex; align-items: center; gap: 10px; }
        .card-body { padding: 25px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--dark); display: flex; align-items: center; gap: 8px; }
        .form-control { width: 100%; padding: 14px; border: 2px solid #e1e5eb; border-radius: 10px; font-size: 1rem; }
        .btn-group { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 20px; }
        .btn { padding: 14px 28px; border: none; border-radius: 10px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #1a252f; transform: translateY(-2px); }
        .btn-update { background: var(--success); color: white; }
        .btn-update:hover { background: #219653; transform: translateY(-2px); }
        .btn-secondary { background: var(--gray); color: white; }
        .btn-secondary:hover { background: #7f8c8d; transform: translateY(-2px); }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 16px; text-align: left; border-bottom: 1px solid #e1e5eb; }
        th { background: var(--primary); color: white; position: sticky; top: 0; }
        tr:nth-child(even) { background-color: #f8f9fa; }
        tr:hover { background-color: #e9f7fe; }
        .action-cell { display: flex; gap: 10px; }
        .btn-action { padding: 8px 12px; border-radius: 6px; cursor: pointer; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; font-size: 0.9rem; }
        .btn-edit { background: #3498db; color: white; }
        .btn-edit:hover { background: #2980b9; }
        .btn-delete { background: #e74c3c; color: white; }
        .btn-delete:hover { background: #c0392b; }
        .notification { padding: 15px; margin: 15px 0; border-radius: 8px; font-weight: 500; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .search-bar { display: flex; gap: 10px; margin-bottom: 20px; }
        .search-bar input { flex-grow: 1; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-wine-bottle"></i> Product Manager</h1>
            <a href="dashboard.php" class="btn-dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        </header>

        <!-- Notifications -->
        <?php if (!empty($errors)): ?>
            <div class="notification error">
                <?php foreach ($errors as $error): ?><p><?= htmlspecialchars($error) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="notification success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2 id="form-title"><i class="fas fa-edit"></i> Add New Product</h2>
            </div>
            <div class="card-body">
                <form method="POST" id="product-form" action="products.php">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name"><i class="fas fa-signature"></i> Product Name *</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="category"><i class="fas fa-tags"></i> Category *</label>
                            <select id="category" name="category" class="form-control" required>
                                <option value="">-- Select Category --</option>
                                <option value="Gin">Gin</option>
                                <option value="Wine">Wine</option>
                                <option value="Soda">Soda</option>
                                <option value="Juice">Juice</option>
                                <option value="Spirit">Spirit</option>
                                <option value="Water">Water</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="cost_price"><i class="fas fa-dollar-sign"></i> Cost Price *</label>
                            <input type="number" id="cost_price" name="cost_price" class="form-control" min="0.01" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="price"><i class="fas fa-tag"></i> Selling Price *</label>
                            <input type="number" id="price" name="price" class="form-control" min="0.01" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="stock_quantity"><i class="fas fa-boxes"></i> Stock Quantity *</label>
                            <input type="number" id="stock_quantity" name="stock_quantity" class="form-control" min="0" step="1" required>
                        </div>
                         <div class="form-group">
                            <label for="barcode"><i class="fas fa-barcode"></i> Barcode</label>
                            <input type="text" id="barcode" name="barcode" class="form-control">
                        </div>
                    </div>
                     <div class="form-group">
                        <label for="description"><i class="fas fa-align-left"></i> Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="btn-group">
                        <button type="submit" id="add-btn" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Add Product</button>
                        <button type="submit" id="update-btn" class="btn btn-update" style="display:none;"><i class="fas fa-save"></i> Update Product</button>
                        <button type="button" onclick="resetForm()" class="btn btn-secondary"><i class="fas fa-redo"></i> Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> Product List</h2>
            </div>
            <div class="card-body">
                <div class="search-bar">
                    <form method="GET" action="products.php" style="display: flex; width: 100%; gap: 10px;">
                        <input type="text" name="search" class="form-control" placeholder="Search by name, category, description, barcode..." value="<?= htmlspecialchars($search_term) ?>">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    </form>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Cost Price</th>
                                <th>Selling Price</th>
                                <th>Stock</th>
                                <th>Barcode</th>
                                <th>Description</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($products_result->num_rows > 0): ?>
                                <?php while($row = $products_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                                    <td><?= htmlspecialchars($row['category']) ?></td>
                                    <td>₦<?= number_format($row['cost_price'], 2) ?></td>
                                    <td>₦<?= number_format($row['price'], 2) ?></td>
                                    <td><?= $row['stock_quantity'] ?></td>
                                    <td><?= htmlspecialchars($row['barcode']) ?></td>
                                    <td><?= htmlspecialchars($row['product_description']) ?></td>
                                    <td class="action-cell">
                                        <button class="btn-action btn-edit" onclick='editProduct(<?= json_encode($row) ?>)'>
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <a href="?delete=<?= $row['id'] ?>&search=<?= htmlspecialchars($search_term)?>" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to delete this product?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align: center;">No products found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('product-form');
        const formTitle = document.getElementById('form-title');
        const editIdInput = document.getElementById('edit_id');
        const nameInput = document.getElementById('name');
        const categoryInput = document.getElementById('category');
        const costPriceInput = document.getElementById('cost_price');
        const priceInput = document.getElementById('price');
        const stockQuantityInput = document.getElementById('stock_quantity');
        const barcodeInput = document.getElementById('barcode');
        const descriptionInput = document.getElementById('description');
        const addBtn = document.getElementById('add-btn');
        const updateBtn = document.getElementById('update-btn');

        function editProduct(product) {
            form.scrollIntoView({ behavior: 'smooth' });
            formTitle.innerHTML = '<i class="fas fa-edit"></i> Edit Product';
            
            editIdInput.value = product.id;
            nameInput.value = product.product_name;
            categoryInput.value = product.category;
            costPriceInput.value = product.cost_price;
            priceInput.value = product.price;
            stockQuantityInput.value = product.stock_quantity;
            barcodeInput.value = product.barcode || '';
            descriptionInput.value = product.product_description;

            addBtn.style.display = 'none';
            updateBtn.style.display = 'inline-flex';
        }

        function resetForm() {
            form.reset();
            editIdInput.value = '';
            formTitle.innerHTML = '<i class="fas fa-edit"></i> Add New Product';
            addBtn.style.display = 'inline-flex';
            updateBtn.style.display = 'none';
            // Clear search query from URL
            const url = new URL(window.location);
            url.searchParams.delete('search');
            window.history.replaceState({}, '', url);
        }

        // Preserve search query on form submission
        form.addEventListener('submit', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const search = urlParams.get('search');
            if (search) {
                const searchInput = document.createElement('input');
                searchInput.type = 'hidden';
                searchInput.name = 'search';
                searchInput.value = search;
                form.appendChild(searchInput);
            }
        });
    </script>
</body>
</html>
