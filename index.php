<?php
session_start();
// This should be in a separate, non-web-accessible file
// For this project, we'll keep it here.
require_once __DIR__ . '/includes/db.php';


// The database connection is now in db.php, so we don't need to redefine it.

// Create users table if not exists and add default users
// This logic should ideally be in a separate setup/install script
$checkTable = $conn->query("SHOW TABLES LIKE 'users'");
if ($checkTable->num_rows == 0) {
    $createUsersTable = "CREATE TABLE IF NOT EXISTS users (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'cashier') NOT NULL DEFAULT 'cashier',
        full_name VARCHAR(100),
        store_id INT(11),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    if (!$conn->query($createUsersTable)) {
        die("Error creating users table: " . $conn->error);
    }

    // Add default admin user
    $hashed_password_admin = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (username, password, role, full_name) VALUES ('admin', '$hashed_password_admin', 'admin', 'Default Admin')");

    // Add default cashier user
    $hashed_password_cashier = password_hash('cashier123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (username, password, role, full_name, store_id) VALUES ('cashier', '$hashed_password_cashier', 'cashier', 'Default Cashier', 1)");
}


// Handle login
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'] ?? '';
            $_SESSION['store_id'] = $user['store_id'] ?? null;
            
            // Redirect to appropriate page based on role
            if ($user['role'] === 'admin') {
                header("Location: admin/dashboard.php");
                exit();
            } else {
                header("Location: cashier/pos.php");
                exit();
            }
        } else {
            $login_error = "Invalid username or password";
        }
    } else {
        $login_error = "Invalid username or password";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remkon Store - Login</title>
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
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #1a2a6c);
            color: #333;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .login-container {
            max-width: 450px;
            width: 100%;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
            overflow: hidden;
            transition: transform 0.4s ease;
        }
        
        .login-card:hover {
            transform: translateY(-10px);
        }
        
        .login-header {
            background: linear-gradient(to right, var(--primary), var(--dark));
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .logo {
            font-size: 3.5rem;
            margin-bottom: 15px;
            color: #ffcc00;
        }
        
        .login-header h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
        }
        
        .login-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .login-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-control {
            width: 100%;
            padding: 16px;
            border: 2px solid #e1e5eb;
            border-radius: 12px;
            font-size: 1.05rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--secondary);
            outline: none;
            box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.2);
        }
        
        .password-container {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--gray);
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #1a252f;
            transform: translateY(-2px);
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .role-info {
            display: flex;
            justify-content: space-between;
            margin-top: 25px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
            font-size: 0.95rem;
        }
        
        .role-item {
            text-align: center;
            padding: 10px;
            flex: 1;
        }
        
        .role-item i {
            font-size: 1.5rem;
            margin-bottom: 8px;
            color: var(--primary);
        }
        
        .admin-role {
            border-right: 1px solid #e1e5eb;
        }
        
        .footer {
            text-align: center;
            color: white;
            margin-top: 30px;
            font-size: 0.95rem;
            opacity: 0.8;
        }
        
        @media (max-width: 500px) {
            .login-container {
                max-width: 100%;
            }
            
            .role-info {
                flex-direction: column;
                gap: 15px;
            }
            
            .admin-role {
                border-right: none;
                border-bottom: 1px solid #e1e5eb;
                padding-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <i class="fas fa-store"></i>
                </div>
                <h1>Remkon Store</h1>
                <p>Inventory & Sales Management System</p>
            </div>
            
            <div class="login-body">
                <?php if ($login_error): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $login_error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="username"><i class="fas fa-user"></i> Username</label>
                        <input type="text" id="username" name="username" class="form-control" required placeholder="Enter your username">
                    </div>
                    
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Password</label>
                        <div class="password-container">
                            <input type="password" id="password" name="password" class="form-control" required placeholder="Enter your password">
                            <span class="toggle-password" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </span>
                        </div>
                    </div>
                    
                    <button type="submit" name="login" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                
                <div class="role-info">
                    <div class="role-item admin-role">
                        <i class="fas fa-user-shield"></i>
                        <div>Admin: Full Access</div>
                    </div>
                    <div class="role-item">
                        <i class="fas fa-cash-register"></i>
                        <div>Cashier: POS Only</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Remkon Store Management System</p>
            <p>Default Admin: admin/admin123 | Cashier: cashier/cashier123</p>
        </div>
    </div>

    <script>
        // Toggle password visibility
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
        
        // Focus on username field when page loads
        document.getElementById('username').focus();
    </script>
</body>
</html>
