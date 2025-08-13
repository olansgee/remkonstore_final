<?php
// admin/suppliers.php
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
    $stmt = $conn->prepare("DELETE FROM suppliers WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success = "Supplier deleted successfully!";
    } else {
        $errors[] = "Error deleting supplier: " . $stmt->error;
    }
    $stmt->close();
}

// Handle form submissions for Add/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $id = isset($_POST['id']) && !empty($_POST['id']) ? (int)$_POST['id'] : null;

    if (empty($name) || empty($address)) {
        $errors[] = "Supplier name and address are required.";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        if ($id) {
            $stmt = $conn->prepare("UPDATE suppliers SET supplier_name = ?, supplier_address = ?, phone = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $address, $phone, $email, $id);
            if ($stmt->execute()) {
                $success = "Supplier updated successfully!";
            } else {
                $errors[] = "Error updating supplier: " . $stmt->error;
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO suppliers (supplier_name, supplier_address, phone, email) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $address, $phone, $email);
            if ($stmt->execute()) {
                $success = "Supplier added successfully!";
            } else {
                $errors[] = "Error adding supplier: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

// Initial fetch of all suppliers
$suppliers_result = $conn->query("SELECT * FROM suppliers ORDER BY supplier_name ASC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Management - Remkon Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #2c3e50; --secondary: #3498db; --success: #27ae60; --light: #ecf0f1; }
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; padding: 20px; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); margin-bottom: 30px; }
        .card-header { background: var(--primary); color: white; padding: 20px; border-radius: 15px 15px 0 0; display: flex; justify-content: space-between; align-items: center; }
        .card-header h2 { margin: 0; font-size: 1.5rem; }
        .btn-dashboard { background: var(--secondary); color: white; padding: 10px 15px; text-decoration: none; border-radius: 8px; }
        .card-body { padding: 25px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; }
        .btn { padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-primary { background: var(--primary); color: white; }
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
                <h2 id="form-title"><i class="fas fa-plus-circle"></i> Add/Edit Supplier</h2>
                <a href="dashboard.php" class="btn-dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            </div>
            <div class="card-body">
                <form method="POST" id="supplier-form" action="suppliers.php">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="form-grid">
                        <div class="form-group"><label for="name">Supplier Name *</label><input type="text" id="name" name="name" class="form-control" required></div>
                        <div class="form-group"><label for="phone">Phone</label><input type="text" id="phone" name="phone" class="form-control"></div>
                    </div>
                    <div class="form-group"><label for="email">Email</label><input type="email" id="email" name="email" class="form-control"></div>
                    <div class="form-group"><label for="address">Address *</label><textarea id="address" name="address" class="form-control" rows="3" required></textarea></div>
                    <button type="submit" id="save-btn" class="btn btn-primary">Save Supplier</button>
                    <button type="button" onclick="resetForm()" class="btn btn-secondary">Cancel</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2><i class="fas fa-list"></i> Supplier List</h2></div>
            <div class="card-body">
                <div class="search-bar">
                    <input type="text" id="live-search-input" class="form-control" placeholder="Search by name, phone, or email...">
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th><th>Name</th><th>Address</th><th>Phone</th><th>Email</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="suppliers-table-body">
                            <?php if ($suppliers_result->num_rows > 0): ?>
                                <?php while($row = $suppliers_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                                    <td><?= htmlspecialchars($row['supplier_address']) ?></td>
                                    <td><?= htmlspecialchars($row['phone']) ?></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td class="action-cell">
                                        <button class="btn-action btn-edit" onclick='editSupplier(<?= json_encode($row) ?>)'>Edit</button>
                                        <a href="?delete=<?= $row['id'] ?>" class="btn-action btn-delete" onclick="return confirm('Are you sure?')">Delete</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align: center;">No suppliers found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        function editSupplier(supplier) {
            document.getElementById('form-title').innerText = 'Edit Supplier';
            document.getElementById('edit_id').value = supplier.id;
            document.getElementById('name').value = supplier.supplier_name;
            document.getElementById('address').value = supplier.supplier_address;
            document.getElementById('phone').value = supplier.phone || '';
            document.getElementById('email').value = supplier.email || '';
            document.getElementById('save-btn').innerText = 'Update Supplier';
            window.scrollTo(0, 0);
        }

        function resetForm() {
            document.getElementById('supplier-form').reset();
            document.getElementById('form-title').innerText = 'Add New Supplier';
            document.getElementById('edit_id').value = '';
            document.getElementById('save-btn').innerText = 'Save Supplier';
        }

        const searchInput = document.getElementById('live-search-input');
        const tableBody = document.getElementById('suppliers-table-body');
        let debounceTimer;

        searchInput.addEventListener('keyup', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                const searchTerm = searchInput.value.trim();
                fetch(`../api/live_search.php?table=suppliers&term=${encodeURIComponent(searchTerm)}`)
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            renderTable(result.data);
                        } else {
                            console.error('Search failed:', result.error);
                            tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">Search failed.</td></tr>`;
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching search results:', error);
                        tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">An error occurred.</td></tr>`;
                    });
            }, 300);
        });

        function renderTable(data) {
            tableBody.innerHTML = '';
            if (data.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">No suppliers found.</td></tr>`;
                return;
            }

            data.forEach(supplier => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${supplier.id}</td>
                    <td>${escapeHTML(supplier.supplier_name)}</td>
                    <td>${escapeHTML(supplier.supplier_address)}</td>
                    <td>${escapeHTML(supplier.phone)}</td>
                    <td>${escapeHTML(supplier.email)}</td>
                    <td class="action-cell">
                        <button class="btn-action btn-edit" onclick='editSupplier(${JSON.stringify(supplier)})'>Edit</button>
                        <a href="?delete=${supplier.id}" class="btn-action btn-delete" onclick="return confirm('Are you sure?')">Delete</a>
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
