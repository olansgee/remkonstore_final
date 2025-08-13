<?php
// admin/create_cashier.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

// Initialize variables
$success = '';
$error = '';
$editing = false;
$edit_data = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle delete action
    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $sql = "DELETE FROM users WHERE id = $id AND role = 'cashier'";
        if ($conn->query($sql)) {
            $success = "Cashier deleted successfully!";
        } else {
            $error = "Error deleting cashier: " . $conn->error;
        }
    } 
    // Handle update action
    else if (isset($_POST['edit_id'])) {
        $id = (int)$_POST['edit_id'];
        $username = $conn->real_escape_string($_POST['username']);
        $email = $conn->real_escape_string($_POST['email']);
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $store_id = (int)$_POST['store_id'];
        
        // Update password only if provided
        $password_update = '';
        if (!empty($_POST['password'])) {
            $password = password_hash($conn->real_escape_string($_POST['password']), PASSWORD_DEFAULT);
            $password_update = ", password = '$password'";
        }
        
        $sql = "UPDATE users SET 
                username = '$username',
                email = '$email',
                full_name = '$full_name',
                store_id = $store_id
                $password_update
                WHERE id = $id";
        
        if ($conn->query($sql)) {
            $success = "Cashier updated successfully!";
            $editing = false;
        } else {
            $error = "Error updating cashier: " . $conn->error;
            $editing = true;
            $edit_data = $_POST;
        }
    }
    // Handle create action
    else {
        $username = $conn->real_escape_string($_POST['username']);
        $password = password_hash($conn->real_escape_string($_POST['password']), PASSWORD_DEFAULT);
        $email = $conn->real_escape_string($_POST['email']);
        $full_name = $conn->real_escape_string($_POST['full_name']);
        $store_id = (int)$_POST['store_id'];
        
        $sql = "INSERT INTO users (username, password, email, full_name, role, store_id) 
                VALUES ('$username', '$password', '$email', '$full_name', 'cashier', $store_id)";
        
        if ($conn->query($sql)) {
            $success = "Cashier account created successfully!";
        } else {
            $error = "Error creating cashier: " . $conn->error;
        }
    }
}

// Handle edit request
if (isset($_GET['edit_id'])) {
    $id = (int)$_GET['edit_id'];
    $result = $conn->query("SELECT * FROM users WHERE id = $id AND role = 'cashier'");
    if ($result->num_rows > 0) {
        $editing = true;
        $edit_data = $result->fetch_assoc();
    } else {
        $error = "Cashier not found!";
    }
}

// Fetch stores for dropdown
$stores = $conn->query("SELECT id, store_name FROM stores");

// Fetch cashiers for listing
$cashiers = $conn->query("
    SELECT u.id, u.username, u.email, u.full_name, s.store_name 
    FROM users u 
    JOIN stores s ON u.store_id = s.id 
    WHERE u.role = 'cashier'
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $editing ? 'Update' : 'Create' ?> Cashier | Remkon Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Existing styles remain unchanged */
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
            max-width: 1200px;
            margin: 0 auto;
        }
        
        header {
            background: linear-gradient(to right, var(--primary), var(--dark));
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .header-content {
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
        
        .user-info {
            text-align: right;
        }
        
        .user-info p {
            margin-bottom: 5px;
        }
        
        .logout-btn {
            color: var(--warning);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s;
            color: inherit;
            margin-bottom: 30px;
        }
        
        .card-header {
            background: var(--primary);
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .card-icon {
            font-size: 1.5rem;
        }
        
        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        .card-body {
            padding: 25px;
        }
        
        /* Form styling */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
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
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--secondary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(to right, var(--secondary), #2980b9);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(to right, #2980b9, var(--secondary));
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: linear-gradient(to right, var(--warning), #e67e22);
            color: white;
        }
        
        .btn-warning:hover {
            background: linear-gradient(to right, #e67e22, var(--warning));
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: linear-gradient(to right, var(--accent), #c0392b);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(to right, #c0392b, var(--accent));
            transform: translateY(-2px);
        }
        
        .btn-cancel {
            background: linear-gradient(to right, var(--gray), #7f8c8d);
            color: white;
            margin-left: 10px;
        }
        
        .btn-cancel:hover {
            background: linear-gradient(to right, #7f8c8d, var(--gray));
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Table styling */
        .table-container {
            overflow-x: auto;
            margin-top: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background-color: var(--primary);
            color: white;
            font-weight: 600;
        }
        
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        tr:hover {
            background-color: #e9f7fe;
        }
        
        .action-cell {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .action-form {
            display: inline;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .user-info {
                text-align: center;
            }
            
            .action-cell {
                flex-direction: column;
                gap: 5px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-store"></i>
                    <div class="logo-text">
                        <h1>Remkon Store</h1>
                        <p><?= $editing ? 'Update Cashier' : 'Create Cashier Account' ?></p>
                    </div>
                </div>
                <div class="user-info">
                    <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
                    <a href="dashboard.php" class="logout-btn" style="margin-right: 15px;"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                    <a href="../logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </header>
        
        <div class="dashboard-card">
            <div class="card-header">
                <i class="fas fa-user-tie card-icon"></i>
                <div class="card-title"><?= $editing ? 'Update Cashier' : 'Create New Cashier' ?></div>
            </div>
            <div class="card-body">
                <?php if(isset($success) && $success): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($error) && $error): ?>
                    <div class="alert alert-error">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <?php if($editing): ?>
                        <input type="hidden" name="edit_id" value="<?= $edit_data['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="username"><i class="fas fa-user"></i> Username</label>
                            <input type="text" id="username" name="username" class="form-control" required
                                value="<?= $editing ? htmlspecialchars($edit_data['username']) : '' ?>">
                        </div>
                        <div class="form-group">
                            <label for="password"><i class="fas fa-lock"></i> Password</label>
                            <input type="password" id="password" name="password" class="form-control" 
                                <?= $editing ? 'placeholder="Leave blank to keep current password"' : 'required' ?>>
                            <?php if($editing): ?>
                                <small class="text-muted">Leave blank to keep current password</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="full_name"><i class="fas fa-id-card"></i> Full Name</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" required
                                value="<?= $editing ? htmlspecialchars($edit_data['full_name']) : '' ?>">
                        </div>
                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" id="email" name="email" class="form-control" required
                                value="<?= $editing ? htmlspecialchars($edit_data['email']) : '' ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="store_id"><i class="fas fa-store"></i> Assigned Store</label>
                        <select id="store_id" name="store_id" class="form-control" required>
                            <option value="">Select a store</option>
                            <?php while($store = $stores->fetch_assoc()): ?>
                                <option value="<?= $store['id'] ?>"
                                    <?= ($editing && $edit_data['store_id'] == $store['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($store['store_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-<?= $editing ? 'warning' : 'primary' ?>">
                        <i class="fas fa-<?= $editing ? 'save' : 'user-plus' ?>"></i>
                        <?= $editing ? 'Update Cashier' : 'Create Cashier Account' ?>
                    </button>
                    
                    <?php if($editing): ?>
                        <a href="create_cashier.php" class="btn btn-cancel">
                            <i class="fas fa-times"></i> Cancel Edit
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <div class="dashboard-card">
            <div class="card-header">
                <i class="fas fa-list card-icon"></i>
                <div class="card-title">Existing Cashiers</div>
            </div>
            <div class="card-body">
                <?php if($cashiers->num_rows > 0): ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Store</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($cashier = $cashiers->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($cashier['username']) ?></td>
                                        <td><?= htmlspecialchars($cashier['full_name']) ?></td>
                                        <td><?= htmlspecialchars($cashier['email']) ?></td>
                                        <td><?= htmlspecialchars($cashier['store_name']) ?></td>
                                        <td class="action-cell">
                                            <a href="create_cashier.php?edit_id=<?= $cashier['id'] ?>" class="btn btn-warning">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <form method="POST" class="action-form" onsubmit="return confirm('Are you sure you want to delete this cashier?');">
                                                <input type="hidden" name="delete_id" value="<?= $cashier['id'] ?>">
                                                <button type="submit" class="btn btn-danger">
                                                    <i class="fas fa-trash-alt"></i> Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No cashiers found. Create your first cashier above.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>