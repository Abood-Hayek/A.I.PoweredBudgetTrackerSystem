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

// Get total income
$query_income = "SELECT SUM(amount) AS total_income FROM transactions WHERE user_id = :user_id AND type = 'income'";
$params_income = ['user_id' => $user_id];
$income_result = fetchData($pdo, $query_income, $params_income);
$income_total = $income_result[0]['total_income'] ? $income_result[0]['total_income'] : 0;

// Get total expenses
$query_expenses = "SELECT SUM(amount) AS total_expenses FROM transactions WHERE user_id = :user_id AND type = 'expense'";
$params_expenses = ['user_id' => $user_id];
$expense_result = fetchData($pdo, $query_expenses, $params_expenses);
$expense_total = $expense_result[0]['total_expenses'] ? $expense_result[0]['total_expenses'] : 0;

// Calculate balance
$balance = $income_total - $expense_total;

// Get income by category
$query_income_category = "SELECT category, SUM(amount) AS total_income FROM transactions WHERE user_id = :user_id AND type = 'income' GROUP BY category";
$params_income_category = ['user_id' => $user_id];
$income_data = fetchData($pdo, $query_income_category, $params_income_category);

// Get expenses by category
$query_expenses_category = "SELECT category, SUM(amount) AS total_expenses FROM transactions WHERE user_id = :user_id AND type = 'expense' GROUP BY category";
$params_expenses_category = ['user_id' => $user_id];
$expense_data = fetchData($pdo, $query_expenses_category, $params_expenses_category);
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

        /* Chart Placeholder */
        .chart-placeholder {
            height: 300px;
            background: #e6f7ff;
            border-radius: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #007BFF;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar">
            <h4>A.I Budget Tracker</h4>
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="transaction.php">Transactions</a>
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
                        <div class="card text-center p-3">
                            <h5>Total Balance:</h5>
                            <h3 class="text-success" style="font-family: sans-serif;">
                                $<?php echo number_format($balance, 2); ?>
                            </h3>
                        </div>
                    </div>

                    <!-- Total Income (Profit) -->
                    <div class="col-md-4">
                        <div class="card text-center p-3">
                            <h5>Total Profit:</h5>
                            <h3 class="text-primary" style="font-family: sans-serif;">
                                $<?php echo number_format($income_total, 2); ?>
                            </h3>
                        </div>
                    </div>

                    <!-- Total Expenses -->
                    <div class="col-md-4">
                        <div class="card text-center p-3">
                            <h5>Total Expenses:</h5>
                            <h3 class="text-danger" style="font-family: sans-serif;">
                                $<?php echo number_format($expense_total, 2); ?>
                            </h3>
                        </div>
                    </div>
                </div>

                <!-- Chart Placeholder -->
                <div class="chart-placeholder mt-4">
                    Chart Placeholder
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>