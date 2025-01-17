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