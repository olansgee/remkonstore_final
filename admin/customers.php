<?php
// admin/customers.php
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
    // You might want to add checks here to ensure a customer with transactions isn't easily deleted.
    $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success = "Customer deleted successfully!";
    } else {
        $errors[] = "Error deleting customer: " . $stmt->error;
    }
    $stmt->close();
}

// Handle form submissions for Add/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);
    $id = isset($_POST['id']) && !empty($_POST['id']) ? (int)$_POST['id'] : null;

    // Basic validation
    if (empty($name)) $errors[] = "Customer name is required.";
    if (empty($phone)) $errors[] = "Phone number is required.";
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (empty($errors)) {
        if ($id) {
            // Update Customer
            $stmt = $conn->prepare("UPDATE customers SET name = ?, phone = ?, email = ?, address = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $name, $phone, $email, $address, $id);
            if ($stmt->execute()) {
                $success = "Customer updated successfully!";
            } else {
                $errors[] = "Error updating customer: " . $stmt->error;
            }
            $stmt->close();
        } else {
            // Add Customer
            $stmt = $conn->prepare("INSERT INTO customers (name, phone, email, address) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $name, $phone, $email, $address);
            if ($stmt->execute()) {
                $success = "Customer added successfully!";
            } else {
                $errors[] = "Error adding customer: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Fetch all customers with search functionality
$sql = "SELECT c.id, c.name, c.phone, c.email, c.address, COUNT(s.id) as transaction_count 
        FROM customers c
        LEFT JOIN sales s ON c.id = s.customer_id"; // Assuming a customer_id in sales table
$params = [];
$types = '';

if ($search_term) {
    $sql .= " WHERE c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?";
    $like_search_term = "%" . $search_term . "%";
    $params = [$like_search_term, $like_search_term, $like_search_term];
    $types = 'sss';
}

$sql .= " GROUP BY c.id, c.name, c.phone, c.email, c.address ORDER BY c.name ASC";

$stmt = $conn->prepare($sql);
if ($search_term) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$customers_result = $stmt->get_result();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - Remkon Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
        .btn-view { background-color: var(--success); color: white; }
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
            <h1><i class="fas fa-users"></i> Customer Management</h1>
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
                <h2 id="form-title"><i class="fas fa-user-plus"></i> Add New Customer</h2>
            </div>
            <div class="card-body">
                <form method="POST" id="customer-form" action="customers.php">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name"><i class="fas fa-user"></i> Full Name *</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="phone"><i class="fas fa-phone"></i> Phone Number *</label>
                            <input type="text" id="phone" name="phone" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-grid">
                         <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" id="email" name="email" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="address"><i class="fas fa-map-marker-alt"></i> Address</label>
                            <textarea id="address" name="address" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="btn-group">
                        <button type="submit" id="add-btn" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Add Customer</button>
                        <button type="submit" id="update-btn" class="btn btn-update" style="display:none;"><i class="fas fa-save"></i> Update Customer</button>
                        <button type="button" onclick="resetForm()" class="btn btn-secondary"><i class="fas fa-redo"></i> Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> Customer Directory</h2>
            </div>
            <div class="card-body">
                <div class="search-bar">
                    <form method="GET" action="customers.php" style="display: flex; width: 100%; gap: 10px;">
                        <input type="text" name="search" class="form-control" placeholder="Search by name, phone, or email..." value="<?= htmlspecialchars($search_term) ?>">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    </form>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Address</th>
                                <th>Transactions</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($customers_result->num_rows > 0): ?>
                                <?php while($row = $customers_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['name']) ?></td>
                                    <td><?= htmlspecialchars($row['phone']) ?></td>
                                    <td><?= htmlspecialchars($row['email']) ?></td>
                                    <td><?= htmlspecialchars($row['address']) ?></td>
                                    <td>
                                        <a href="customer_transactions.php?id=<?= $row['id'] ?>" class="btn-action btn-view">
                                            <i class="fas fa-receipt"></i> View (<?= $row['transaction_count'] ?>)
                                        </a>
                                    </td>
                                    <td class="action-cell">
                                        <button class="btn-action btn-edit" onclick='editCustomer(<?= json_encode($row) ?>)'>
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <a href="?delete=<?= $row['id'] ?>&search=<?= htmlspecialchars($search_term)?>" class="btn-action btn-delete" onclick="return confirm('Are you sure you want to delete this customer?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">No customers found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('customer-form');
        const formTitle = document.getElementById('form-title');
        const editIdInput = document.getElementById('edit_id');
        const nameInput = document.getElementById('name');
        const phoneInput = document.getElementById('phone');
        const emailInput = document.getElementById('email');
        const addressInput = document.getElementById('address');
        const addBtn = document.getElementById('add-btn');
        const updateBtn = document.getElementById('update-btn');

        function editCustomer(customer) {
            form.scrollIntoView({ behavior: 'smooth' });
            formTitle.innerHTML = '<i class="fas fa-user-edit"></i> Edit Customer';
            
            editIdInput.value = customer.id;
            nameInput.value = customer.name;
            phoneInput.value = customer.phone;
            emailInput.value = customer.email || '';
            addressInput.value = customer.address || '';

            addBtn.style.display = 'none';
            updateBtn.style.display = 'inline-flex';
        }

        function resetForm() {
            form.reset();
            editIdInput.value = '';
            formTitle.innerHTML = '<i class="fas fa-user-plus"></i> Add New Customer';
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
