<?php
// admin/reports.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_role('admin');

// Get report parameters with defaults
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$store_id = isset($_GET['store_id']) ? (int)$_GET['store_id'] : 0;

// Build query conditions
$conditions = ["s.sale_date BETWEEN ? AND ?"];
$params = [$start_date, $end_date . ' 23:59:59'];

if ($store_id > 0) {
    $conditions[] = "s.store_id = ?";
    $params[] = $store_id;
}

$where_clause = "WHERE " . implode(" AND ", $conditions);

// Fetch sales report data
$report_query = $conn->prepare("
    SELECT s.id, s.sale_date, s.total_amount, s.payment_method, 
           c.name AS customer_name, st.store_name,
           GROUP_CONCAT(p.product_name SEPARATOR ', ') AS products
    FROM sales s
    JOIN customers c ON s.customer_id = c.id
    JOIN stores st ON s.store_id = st.id
    JOIN sale_items si ON s.id = si.sale_id
    JOIN products p ON si.product_id = p.id
    $where_clause
    GROUP BY s.id
    ORDER BY s.sale_date DESC
");

// Bind parameters
$types = str_repeat('s', count($params));
$report_query->bind_param($types, ...$params);
$report_query->execute();
$report_data = $report_query->get_result();

// Fetch stores for filter
$stores = $conn->query("SELECT id, store_name FROM stores");

// Calculate totals
$total_sales = 0;
$total_customers = 0;
$total_products = 0;
$payment_methods = ['cash' => 0, 'credit' => 0, 'mobile' => 0, 'other' => 0];

while ($row = $report_data->fetch_assoc()) {
    $total_sales += $row['total_amount'];
    $total_customers++;
    $total_products += substr_count($row['products'], ',') + 1;
    
    // Count payment methods
    $method = strtolower($row['payment_method']);
    if (isset($payment_methods[$method])) {
        $payment_methods[$method] += $row['total_amount'];
    } else {
        $payment_methods['other'] += $row['total_amount'];
    }
}

// Reset pointer for display
$report_data->data_seek(0);

// Fetch sales data for chart - FIXED: Added table alias to match conditions
$daily_sales = [];
$daily_query = $conn->prepare("
    SELECT DATE(s.sale_date) AS sale_day, SUM(s.total_amount) AS daily_total
    FROM sales s
    $where_clause
    GROUP BY DATE(s.sale_date)
    ORDER BY s.sale_date
");
$daily_query->bind_param($types, ...$params);
$daily_query->execute();
$daily_result = $daily_query->get_result();

while ($day = $daily_result->fetch_assoc()) {
    $daily_sales[$day['sale_day']] = $day['daily_total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | Remkon Store</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
            align-items: end;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .btn {
            padding: 10px 20px;
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
        
        .btn-success {
            background: linear-gradient(to right, var(--success), #219653);
            color: white;
        }
        
        .btn-success:hover {
            background: linear-gradient(to right, #219653, var(--success));
            transform: translateY(-2px);
        }
        
        .report-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 20px;
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary);
            margin: 10px 0;
        }
        
        .stat-label {
            font-size: 1rem;
            color: var(--gray);
        }
        
        .chart-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 20px;
            transition: transform 0.3s;
        }
        
        .chart-card:hover {
            transform: translateY(-3px);
        }
        
        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .report-table th {
            background-color: var(--primary);
            color: white;
            text-align: left;
            padding: 15px;
            font-weight: 600;
        }
        
        .report-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .report-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .report-table tr:hover {
            background-color: #f1f7fd;
        }
        
        .text-right {
            text-align: right;
        }
        
        .product-list {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .export-actions {
            display: flex;
            gap: 15px;
            margin: 20px 0;
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
            
            .chart-container {
                grid-template-columns: 1fr;
            }
            
            .report-table {
                display: block;
                overflow-x: auto;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .export-actions {
                flex-direction: column;
            }
            @font-face {
                font-family: 'Naira';
                src: local('Arial');
                unicode-range: U+20A6;
            }

            body {
                font-family: 'Naira', sans-serif;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-chart-pie"></i>
                    <div class="logo-text">
                        <h1>Remkon Store</h1>
                        <p>Sales Reports & Analytics</p>
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
                <i class="fas fa-filter card-icon"></i>
                <div class="card-title">Report Filters</div>
            </div>
            <div class="card-body">
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label for="start_date"><i class="fas fa-calendar-start"></i> Start Date</label>
                        <input type="date" id="start_date" name="start_date" 
                               value="<?= $start_date ?>" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date"><i class="fas fa-calendar-end"></i> End Date</label>
                        <input type="date" id="end_date" name="end_date" 
                               value="<?= $end_date ?>" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="store_id"><i class="fas fa-store"></i> Store</label>
                        <select id="store_id" name="store_id" class="form-control">
                            <option value="0">All Stores</option>
                            <?php 
                            $stores->data_seek(0); // Reset pointer
                            while($store = $stores->fetch_assoc()): ?>
                                <option value="<?= $store['id'] ?>" <?= $store_id == $store['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($store['store_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Generate Report
                        </button>
                    </div>
                </form>
                
                <div class="report-stats">
                    <div class="stat-card">
                        <i class="fas fa-receipt fa-2x"></i>
                        <div class="stat-value"><?= $report_data->num_rows ?></div>
                        <div class="stat-label">Transactions</div>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-dollar-sign fa-2x"></i>
                        <div class="stat-value">₦<?= number_format($total_sales, 2) ?></div>
                        <div class="stat-label">Total Sales</div>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-users fa-2x"></i>
                        <div class="stat-value"><?= $total_customers ?></div>
                        <div class="stat-label">Customers</div>
                    </div>
                    
                    <div class="stat-card">
                        <i class="fas fa-box fa-2x"></i>
                        <div class="stat-value"><?= $total_products ?></div>
                        <div class="stat-label">Products Sold</div>
                    </div>
                </div>
                
                <div class="chart-container">
                    <div class="chart-card">
                        <div class="chart-title">
                            <i class="fas fa-credit-card"></i>
                            Sales by Payment Method
                        </div>
                        <canvas id="paymentChart" height="250"></canvas>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-title">
                            <i class="fas fa-chart-line"></i>
                            Daily Sales Trend
                        </div>
                        <canvas id="salesChart" height="250"></canvas>
                    </div>
                </div>
                
                <div class="export-actions">
                    <button class="btn btn-success">
                        <i class="fas fa-file-pdf"></i> Export to PDF
                    </button>
                    <button class="btn btn-primary">
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </button>
                    <button class="btn" style="background: var(--dark); color: white;">
                        <i class="fas fa-print"></i> Print Report
                    </button>
                </div>
                
                <h3><i class="fas fa-list"></i> Detailed Transaction Report</h3>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Store</th>
                            <th>Products</th>
                            <th class="text-right">Amount</th>
                            <th>Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $report_data->data_seek(0); // Reset pointer
                        while($row = $report_data->fetch_assoc()): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($row['sale_date'])) ?></td>
                            <td><?= htmlspecialchars($row['customer_name']) ?></td>
                            <td><?= htmlspecialchars($row['store_name']) ?></td>
                            <td class="product-list" title="<?= htmlspecialchars($row['products']) ?>">
                                <?= htmlspecialchars($row['products']) ?>
                            </td>
                            <td class="text-right">₦<?= number_format($row['total_amount'], 2) ?></td>
                            <td><?= ucfirst($row['payment_method']) ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if($report_data->num_rows === 0): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px;">
                                <i class="fas fa-inbox" style="font-size: 3rem; color: var(--gray); opacity: 0.5;"></i>
                                <p style="margin-top: 15px;">No transactions found for the selected period</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Payment Method Chart
        const paymentCtx = document.getElementById('paymentChart').getContext('2d');
        const paymentChart = new Chart(paymentCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    'Cash', 
                    'Credit Card', 
                    'Mobile Payment', 
                    'Other'
                ],
                datasets: [{
                    data: [
                        <?= $payment_methods['cash'] ?>,
                        <?= $payment_methods['credit'] ?>,
                        <?= $payment_methods['mobile'] ?>,
                        <?= $payment_methods['other'] ?>
                    ],
                    backgroundColor: [
                        '#3498db',
                        '#2ecc71',
                        '#9b59b6',
                        '#e74c3c'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.chart.getDatasetMeta(0).total;
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ₦${value.toFixed(2)} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // Sales Trend Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        
        // Generate dates between start and end
        const startDate = new Date('<?= $start_date ?>');
        const endDate = new Date('<?= $end_date ?>');
        const dateArray = [];
        let currentDate = new Date(startDate);
        
        while (currentDate <= endDate) {
            dateArray.push(new Date(currentDate).toISOString().split('T')[0]);
            currentDate.setDate(currentDate.getDate() + 1);
        }
        
        // Create sales data array with zeros
        const salesData = dateArray.map(date => {
            return typeof daily_sales[date] !== 'undefined' ? daily_sales[date] : 0;
        });
        
        // Format dates for display
        const formattedDates = dateArray.map(date => {
            const d = new Date(date);
            return `${d.getDate()} ${d.toLocaleString('default', { month: 'short' })}`;
        });
        
        const salesChart = new Chart(salesCtx, {
            type: 'bar',
            data: {
                labels: formattedDates,
                datasets: [{
                    label: 'Daily Sales (₦)',
                    data: salesData,
                    backgroundColor: 'rgba(52, 152, 219, 0.7)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>