<?php
// admin/suppliers.php
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

    // Basic validation
    if (empty($name)) $errors[] = "Supplier name is required.";
    if (empty($address)) $errors[] = "Address is required.";
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (empty($errors)) {
        if ($id) {
            // Update Supplier
            $stmt = $conn->prepare("UPDATE suppliers SET supplier_name = ?, supplier_address = ?, phone = ?, email = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $address, $phone, $email, $id);
            if ($stmt->execute()) {
                $success = "Supplier updated successfully!";
            } else {
                $errors[] = "Error updating supplier: " . $stmt->error;
            }
            $stmt->close();
        } else {
            // Add Supplier
            $stmt = $conn->prepare("INSERT INTO suppliers (supplier_name, supplier_address, phone, email) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $address, $phone, $email);
            if ($stmt->execute()) {
                $success = "Supplier added successfully!";
            } else {
                $errors[] = "Error adding supplier: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Fetch all suppliers with search functionality
$sql = "SELECT * FROM suppliers";
$params = [];
$types = '';

if ($search_term) {
    $sql .= " WHERE supplier_name LIKE ? OR supplier_address LIKE ? OR phone LIKE ? OR email LIKE ?";
    $like_search_term = "%" . $search_term . "%";
    $params = [$like_search_term, $like_search_term, $like_search_term, $like_search_term];
    $types = 'ssss';
}
$sql .= " ORDER BY supplier_name ASC";

$stmt = $conn->prepare($sql);
if ($search_term) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$suppliers_result = $stmt->get_result();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Management - Remkon Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Using the same styles from the original file for consistency */
        :root { --primary: #2c3e50; --secondary: #3498db; --accent: #e74c3c; --success: #27ae60; --warning: #f39c12; --light: #ecf0f1; --dark: #2c3e50; --gray: #95a5a6; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); color: #333; min-height: 100vh; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        header { background: linear-gradient(to right, var(--primary), var(--dark)); color: white; padding: 25px; border-radius: 15px; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        header h1 { font-size: 2.2rem; display: flex; align-items: center; gap: 15px; }
        .btn-dashboard { background: var(--secondary); color: white; padding: 12px 20px; text-decoration: none; border-radius: 8px; transition: background 0.3s; font-weight: 600; }
        .btn-dashboard:hover { background: #2980b9; }
        .card { background: white; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); overflow: hidden; margin-bottom: 30px; }
        .card-header { background: var(--primary); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        .card-header h2 { font-size: 1.4rem; display: flex; align-items: center; gap: 10px; }
        .card-body { padding: 25px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--dark); display: flex; align-items: center; gap: 8px; }
        .form-control { width: 100%; padding: 14px; border: 2px solid #e1e5eb; border-radius: 10px; font-size: 1rem; }
        .btn-group { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 20px; }
        .btn { padding: 14px 28px; border: none; border-radius: 10px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-update { background: var(--success); color: white; }
        .btn-secondary { background: var(--gray); color: white; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 16px; text-align: left; border-bottom: 1px solid #e1e5eb; }
        th { background: var(--primary); color: white; position: sticky; top: 0; }
        .action-cell { display: flex; gap: 10px; }
        .btn-action { padding: 8px 12px; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; font-size: 0.9rem; }
        .btn-edit { background: #3498db; color: white; }
        .btn-delete { background: #e74c3c; color: white; }
        .notification { padding: 15px; margin: 15px 0; border-radius: 8px; font-weight: 500; }
        .success { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
        .search-bar { display: flex; gap: 10px; margin-bottom: 20px; }
        .search-bar input { flex-grow: 1; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><i class="fas fa-truck"></i> Supplier Management</h1>
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
                <h2 id="form-title"><i class="fas fa-plus-circle"></i> Add New Supplier</h2>
            </div>
            <div class="card-body">
                <form method="POST" id="supplier-form" action="suppliers.php">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name"><i class="fas fa-signature"></i> Supplier Name *</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="phone"><i class="fas fa-phone"></i> Phone</label>
                            <input type="text" id="phone" name="phone" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" id="email" name="email" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="address"><i class="fas fa-map-marker-alt"></i> Address *</label>
                        <textarea id="address" name="address" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="btn-group">
                        <button type="submit" id="add-btn" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Add Supplier</button>
                        <button type="submit" id="update-btn" class="btn btn-update" style="display:none;"><i class="fas fa-save"></i> Update Supplier</button>
                        <button type="button" onclick="resetForm()" class="btn btn-secondary"><i class="fas fa-redo"></i> Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> Supplier List</h2>
            </div>
            <div class="card-body">
                <div class="search-bar">
                    <form method="GET" action="suppliers.php" style="display: flex; width: 100%; gap: 10px;">
                        <input type="text" name="search" class="form-control" placeholder="Search by name, address, phone, email..." value="<?= htmlspecialchars($search_term) ?>">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    </form>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Address</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($suppliers_result->num_rows > 0): ?>
                                <?php while($row = $suppliers_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= htmlspecialchars($row['supplier_name']) ?></td>
                                    <td><?= htmlspecialchars($row['supplier_address']) ?></td>
                                    <td><?= htmlspecialchars($row['phone']) ?></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td class="action-cell">
                                        <button class="btn-action btn-edit" onclick='editSupplier(<?= json_encode($row) ?>)'>
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <a href="?delete=<?= $row['id'] ?>&search=<?= htmlspecialchars($search_term)?>" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to delete this supplier?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">No suppliers found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('supplier-form');
        const formTitle = document.getElementById('form-title');
        const editIdInput = document.getElementById('edit_id');
        const nameInput = document.getElementById('name');
        const addressInput = document.getElementById('address');
        const phoneInput = document.getElementById('phone');
        const emailInput = document.getElementById('email');
        const addBtn = document.getElementById('add-btn');
        const updateBtn = document.getElementById('update-btn');

        function editSupplier(supplier) {
            form.scrollIntoView({ behavior: 'smooth' });
            formTitle.innerHTML = '<i class="fas fa-edit"></i> Edit Supplier';
            
            editIdInput.value = supplier.id;
            nameInput.value = supplier.supplier_name;
            addressInput.value = supplier.supplier_address;
            phoneInput.value = supplier.phone || '';
            emailInput.value = supplier.email || '';

            addBtn.style.display = 'none';
            updateBtn.style.display = 'inline-flex';
        }

        function resetForm() {
            form.reset();
            editIdInput.value = '';
            formTitle.innerHTML = '<i class="fas fa-plus-circle"></i> Add New Supplier';
            addBtn.style.display = 'inline-flex';
            updateBtn.style.display = 'none';
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
