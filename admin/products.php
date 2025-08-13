<?php
// admin/products.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (!is_logged_in() || !has_role('admin')) {
    header("Location: ../index.php");
    exit();
}

$errors = [];
$success = '';

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

    if (empty($name) || empty($category) || $cost_price <= 0 || $price <= 0 || $stock_quantity < 0) {
        $errors[] = "Please fill all required fields with valid data.";
    } else {
        if ($id) {
            $stmt = $conn->prepare("UPDATE products SET product_name = ?, product_description = ?, category = ?, cost_price = ?, price = ?, stock_quantity = ?, barcode = ? WHERE id = ?");
            $stmt->bind_param("sssdissi", $name, $desc, $category, $cost_price, $price, $stock_quantity, $barcode, $id);
            if ($stmt->execute()) {
                $success = "Product updated successfully!";
            } else {
                $errors[] = "Error updating product: " . $stmt->error;
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO products (product_name, product_description, category, cost_price, price, stock_quantity, barcode) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssdiss", $name, $desc, $category, $cost_price, $price, $stock_quantity, $barcode);
            if ($stmt->execute()) {
                $success = "Product added successfully!";
            } else {
                $errors[] = "Error adding product: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

// Initial fetch of all products
$products_result = $conn->query("SELECT * FROM products ORDER BY product_name ASC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Manager - Remkon Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #2c3e50; --secondary: #3498db; --success: #27ae60; --light: #ecf0f1; }
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; padding: 20px; color: #333; }
        .container { max-width: 1400px; margin: 0 auto; }
        .card { background: white; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 30px; }
        .card-header { background: var(--primary); color: white; padding: 20px; border-radius: 15px 15px 0 0; display: flex; justify-content: space-between; align-items: center; }
        .card-header h2 { margin: 0; font-size: 1.5rem; }
        .btn-dashboard { background: var(--secondary); color: white; padding: 10px 15px; text-decoration: none; border-radius: 8px; }
        .card-body { padding: 25px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; }
        .btn { padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-update { background: var(--success); color: white; }
        .btn-secondary { background: #bdc3c7; color: #fff; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; }
        .action-cell { display: flex; gap: 10px; }
        .btn-action { color: white; text-decoration: none; padding: 8px 12px; border-radius: 6px; }
        .btn-edit { background: var(--secondary); }
        .btn-delete { background: #e74c3c; }
        .notification { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .search-bar { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2 id="form-title"><i class="fas fa-edit"></i> Add/Edit Product</h2>
                <a href="dashboard.php" class="btn-dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </div>
            <div class="card-body">
                <form method="POST" id="product-form" action="products.php">
                    <input type="hidden" name="id" id="edit_id">
                    <!-- Form content remains the same -->
                    <div class="form-grid">
                        <div class="form-group"><label for="name">Product Name *</label><input type="text" id="name" name="name" class="form-control" required></div>
                        <div class="form-group"><label for="category">Category *</label><input type="text" id="category" name="category" class="form-control" required></div>
                        <div class="form-group"><label for="cost_price">Cost Price *</label><input type="number" id="cost_price" name="cost_price" class="form-control" min="0.01" step="0.01" required></div>
                        <div class="form-group"><label for="price">Selling Price *</label><input type="number" id="price" name="price" class="form-control" min="0.01" step="0.01" required></div>
                        <div class="form-group"><label for="stock_quantity">Stock Quantity *</label><input type="number" id="stock_quantity" name="stock_quantity" class="form-control" min="0" step="1" required></div>
                        <div class="form-group"><label for="barcode">Barcode</label><input type="text" id="barcode" name="barcode" class="form-control"></div>
                    </div>
                    <div class="form-group"><label for="description">Description</label><textarea id="description" name="description" class="form-control" rows="3"></textarea></div>
                    <button type="submit" id="save-btn" class="btn btn-primary">Save Product</button>
                    <button type="button" onclick="resetForm()" class="btn btn-secondary">Cancel</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2><i class="fas fa-list"></i> Product List</h2></div>
            <div class="card-body">
                <div class="search-bar">
                    <input type="text" id="live-search-input" class="form-control" placeholder="Search by name or barcode...">
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th><th>Name</th><th>Category</th><th>Cost Price</th><th>Selling Price</th><th>Stock</th><th>Barcode</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="products-table-body">
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
                                    <td class="action-cell">
                                        <button class="btn-action btn-edit" onclick='editProduct(<?= json_encode($row) ?>)'>Edit</button>
                                        <a href="?delete=<?= $row['id'] ?>" class="btn-action btn-delete" onclick="return confirm('Are you sure?')">Delete</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="8" style="text-align: center;">No products found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editProduct(product) {
            document.getElementById('form-title').innerText = 'Edit Product';
            document.getElementById('edit_id').value = product.id;
            document.getElementById('name').value = product.product_name;
            document.getElementById('description').value = product.product_description;
            document.getElementById('category').value = product.category;
            document.getElementById('cost_price').value = product.cost_price;
            document.getElementById('price').value = product.price;
            document.getElementById('stock_quantity').value = product.stock_quantity;
            document.getElementById('barcode').value = product.barcode;
            document.getElementById('save-btn').innerText = 'Update Product';
            window.scrollTo(0, 0);
        }

        function resetForm() {
            document.getElementById('product-form').reset();
            document.getElementById('form-title').innerText = 'Add New Product';
            document.getElementById('edit_id').value = '';
            document.getElementById('save-btn').innerText = 'Save Product';
        }

        const searchInput = document.getElementById('live-search-input');
        const tableBody = document.getElementById('products-table-body');
        let debounceTimer;

        searchInput.addEventListener('keyup', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const searchTerm = searchInput.value.trim();
                fetch(`../api/live_search.php?table=products&term=${encodeURIComponent(searchTerm)}`)
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            renderTable(result.data);
                        } else {
                            console.error('Search failed:', result.error);
                            tableBody.innerHTML = `<tr><td colspan="8" style="text-align: center;">Search failed.</td></tr>`;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching search results:', error);
                        tableBody.innerHTML = `<tr><td colspan="8" style="text-align: center;">An error occurred.</td></tr>`;
                    });
            }, 300); // 300ms debounce
        });

        function renderTable(data) {
            tableBody.innerHTML = '';
            if (data.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="8" style="text-align: center;">No products found.</td></tr>`;
                return;
            }

            data.forEach(product => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${product.id}</td>
                    <td>${escapeHTML(product.product_name)}</td>
                    <td>${escapeHTML(product.category)}</td>
                    <td>₦${parseFloat(product.cost_price).toFixed(2)}</td>
                    <td>₦${parseFloat(product.price).toFixed(2)}</td>
                    <td>${product.stock_quantity}</td>
                    <td>${escapeHTML(product.barcode)}</td>
                    <td class="action-cell">
                        <button class="btn-action btn-edit" onclick='editProduct(${JSON.stringify(product)})'>Edit</button>
                        <a href="?delete=${product.id}" class="btn-action btn-delete" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                `;
                tableBody.appendChild(row);
            });
        }

        function escapeHTML(str) {
            if (str === null || str === undefined) return '';
            return str.toString().replace(/[&<>"']/g, function(match) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                }[match];
            });
        }
    </script>
</body>
</html>
