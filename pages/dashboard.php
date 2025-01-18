<?php
// Start the session to check if the user is logged in
session_start();

// Include the database connection and db_helper file
include('../database/db_connection.php');
include('../database/db_helper.php');

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if the user is not logged in
    header('Location: login.php');
    exit();
}

// Get user information from the session (if needed)
$user_id = $_SESSION['user_id']; // Get the user ID from session

// Calculate income, expenses, and balance
$query_totals = "SELECT 
    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS total_income,
    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS total_expenses 
    FROM transactions WHERE user_id = :user_id";

$stmt = $pdo->prepare($query_totals);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$totals = $stmt->fetch(PDO::FETCH_ASSOC);

$income_total = $totals['total_income'] ?? 0;
$expense_total = $totals['total_expenses'] ?? 0;
$balance = $income_total - $expense_total;


//Prepare query parameters
$params_transactions = ['user_id' => $user_id];

// Get filter and sorting parameters
$sort = $_GET['sort'] ?? '';
$category = $_GET['category'] ?? ''; // Default to empty if not set
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build query for transactions
$query_transactions = "SELECT * FROM transactions WHERE user_id = :user_id";

// Add category filter if selected
if (!empty($category)) {
    $query_transactions .= " AND category = :category";
}

// Add date range filter if selected
if (!empty($start_date) && !empty($end_date)) {
    $query_transactions .= " AND created_at BETWEEN :start_date AND :end_date";
}

// Add sorting
$sorting_options = [
    'amount_asc' => 'amount ASC',
    'amount_desc' => 'amount DESC',
    'category' => 'category ASC',
    'type' => 'type ASC',
    'date_asc' => 'created_at ASC',
    'date_desc' => 'created_at DESC',
];

$query_transactions .= isset($sorting_options[$sort]) ? " ORDER BY {$sorting_options[$sort]}" : " ORDER BY created_at DESC";

// Add the category filter parameter if a category is selected
if (!empty($category)) {
    $params_transactions['category'] = $category;
}

// Add date range parameters if specified
if (!empty($start_date) && !empty($end_date)) {
    $params_transactions['start_date'] = $start_date;
    $params_transactions['end_date'] = $end_date;
}

// Fetch the transactions based on the final query
$transactions = fetchData($pdo, $query_transactions, $params_transactions);


?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        /* Dashboard Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #ddd;
        }

        .balance-card {
            border: 2px solid #1A5276;
            background-color: rgba(26, 82, 118, 0.1);
        }

        .income-card {
            border: 2px solid #1ABC9C;
            background-color: rgba(26, 188, 156, 0.1);
        }

        .expense-card {
            border: 2px solid red;
            background-color: rgba(255, 0, 0, 0.1);
        }

        .totals-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .totals-headers {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: left;
        }


        .icon-box {
            background-color: white;
            padding: 5px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            /* Subtle drop shadow */
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 15px;
            /* Creates some space between the number and the icon */
        }
    </style>
</head>

<body>

    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar">
            <h4>A.I Budget Tracker</h4>
            <a href="dashboard.php" class="active">
                <img class="sidebar-icons" src="../assets/icons/dashboard_icon.svg" alt="Dashboard Icon">Dashboard
            </a>
            <a href="transaction.php">
                <img class="sidebar-icons" src="../assets/icons/transaction_icon.svg"
                    alt="Transactions Icon">Transactions
            </a>
        </div>
        <!-- Main Content -->
        <div class="flex-grow-1">
            <!-- Header -->
            <div class="dashboard-header">
                <h2>Dashboard</h2>
                <a href="logout.php" class="btn btn-outline-primary">Log Out</a>
            </div>

            <!-- Cards -->
            <div class="container my-4">
                <div class="row g-4">
                    <!-- Total Balance -->

                    <div class="col-md-4">
                        <div class="card balance-card text-center p-3">
                            <div class="totals-container">
                                <div class="totals-headers">
                                    <h5>Total Balance:</h5>
                                    <h3 style="font-family: sans-serif; color: #1A5276; font-weight: bold;">
                                        $<?php echo number_format($balance, 2); ?>
                                    </h3>
                                </div>
                                <div class="icon-box">
                                    <img src="../assets/icons/balance_icon.svg" alt="Balance Icon" class="icon">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Total Income (Profit) -->
                    <div class="col-md-4">
                        <div class="card income-card text-center p-3">
                            <div class="totals-container">
                                <div class="totals-headers">
                                    <h5>Total Income:</h5>
                                    <h3 style="font-family: sans-serif; color: #1ABC9C; font-weight: bold;">
                                        $<?php echo number_format($income_total, 2); ?>
                                    </h3>
                                </div>
                                <div class="icon-box">
                                    <img src="../assets/icons/income_icon.svg" alt="Income Icon" class="icon">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Total Expenses -->
                    <div class="col-md-4">
                        <div class="card expense-card text-center p-3">
                            <div class="totals-container">
                                <div class="totals-headers">
                                    <h5>Total Expenses:</h5>
                                    <h3 class="text-danger" style="font-family: sans-serif; font-weight: bold;">
                                        $<?php echo number_format($expense_total, 2); ?>
                                    </h3>
                                </div>
                                <div class="icon-box">
                                    <img src="../assets/icons/expense_icon.svg" alt="Expense Icon" class="icon">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card chart-container" style="margin-top: 40px;">
                    <h3>Expenses Chart</h3>
                    <?php if (count($transactions) > 0): ?>
                        <canvas id="myPieChart" width="400" height="400"></canvas>
                    <?php else: ?>
                        <div class="no-transactions">
                            <p>No transactions have been added yet. Add your income or expenses to see the chart!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>