<?php
session_start();

include('../database/db_connection.php');
include('../database/db_helper.php');

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id']; // Get the user ID from session

$command = escapeshellcmd("python script_name.py $user_id");
$output = shell_exec($command);
echo $output;


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

// Prepare query parameters
$params_transactions = ['user_id' => $user_id];

// Get filter and sorting parameters
$sort = $_GET['sort'] ?? '';
$category = $_GET['category'] ?? ''; // Default to empty if not set
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build query for transactions
$query_transactions = "SELECT * FROM transactions WHERE user_id = :user_id";


// Fetch the transactions based on the final query
$transactions = fetchData($pdo, $query_transactions, $params_transactions);

// Prepare transaction data
$transaction_data = [];
foreach ($transactions as $transaction) {
    $transaction_data[] = [
        'date' => $transaction['date'],
        'amount' => $transaction['amount'],
    ];
}

// Save transaction data to a JSON file
file_put_contents('transaction_data.json', json_encode($transaction_data));


if (isset($_GET['model'])) {
    $model = $_GET['model']; // e.g., "Linear_Regression"
    $file_path = "./predictions/" . $model . "_chart.json";

    if (file_exists($file_path)) {
        header('Content-Type: application/json');
        echo file_get_contents($file_path);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "File not found"]);
    }
} else {
    http_response_code(400);
}
?>




<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <script src="app.js"></script>
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
        }

        .apply-btn {
            background-color: #1ABC9C;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            width: auto;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .apply-btn:hover {
            background-color: white;
            color: #1ABC9C;
        }


        .reset-btn {
            background-color: transparent;
            color: #1ABC9C;
            border: 1px solid #1ABC9C;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            width: auto;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .reset-btn:hover {
            background-color: #1ABC9C;
            color: white;
        }
    </style>
</head>

<body>
    <div class="d-flex" style="height: 100vh;">
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
                                    <?php if ($balance < 0): ?>
                                        <h3 style="font-family: sans-serif; color: rgb(255, 0, 0); font-weight: bold;">
                                            $<?php echo number_format($balance, 2); ?>
                                        </h3>
                                    <?php else: ?>
                                        <h3 style="font-family: sans-serif; color: #1A5276; font-weight: bold;">
                                            $<?php echo number_format($balance, 2); ?>
                                        </h3>
                                    <?php endif; ?>
                                </div>
                                <div class="icon-box">
                                    <img src="../assets/icons/balance_icon.svg" alt="Balance Icon" class="icon">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Income -->
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

                <!-- Chart Section -->
                <div class="chart-container" style="padding-top: 60px; padding-bottom: 30px;">
                    <h3>Predictive chart For Expenses</h3>
                    <?php if (count($transactions) > 0): ?>
                        <div class="d-flex justify-content-between align-items-center filter-form">
                            <label for="modelSelect">Select Model:</label>
                            <select id="modelSelect">
                                <option value="Linear_Regression">Linear Regression</option>
                                <option value="Random_Forest">Random Forest</option>
                                <option value="SVR">Support Vector Regression (SVR)</option>
                            </select>
                            <button id="runScriptButton" class="form-actions apply-btn">Confirm</button>
                        </div>
                        <div style="position: relative; margin-top: 20px;">
                            <canvas id="predictionChart" width="400" height="200"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="no-transactions">
                            <p>No transactions have been added yet. Add your income and expenses to see the chart!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const modelSelect = document.getElementById("modelSelect");
            const runScriptButton = document.getElementById("runScriptButton");
            const ctx = document.getElementById("predictionChart").getContext("2d");
            let chart = null;

            // Function to fetch and display data
            async function fetchAndDisplayChart(model) {
                try {
                    const response = await fetch(`load_predictions.php?model=${model}`);
                    if (!response.ok) throw new Error("Failed to load data");

                    const data = await response.json();

                    // Extract data for the chart
                    const categories = data.map(item => item.category);
                    const predictions = data.map(item => item.prediction);

                    // Exclude certain categories
                    const excludedCategories = ["business", "investment", "employment"];
                    const filteredCategories = categories.filter((category, index) => !excludedCategories.includes(category));
                    const filteredPredictions = predictions.filter((_, index) => !excludedCategories.includes(categories[index]));

                    // Update or create the chart
                    if (chart) {
                        chart.data.labels = filteredCategories;
                        chart.data.datasets[0].data = filteredPredictions;
                        chart.update();
                    } else {
                        chart = new Chart(ctx, {
                            type: "bar",
                            data: {
                                labels: filteredCategories,
                                datasets: [{
                                    label: "Prediction",
                                    data: filteredPredictions,
                                    backgroundColor: "rgba(75, 192, 192, 0.2)",
                                    borderColor: "rgba(75, 192, 192, 1)",
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false, // Ensures proper layout
                                scales: {
                                    x: {
                                        title: {
                                            display: true, // Enable the title
                                            text: "Expense Category" // X-axis label
                                        }
                                    },
                                    y: {
                                        beginAtZero: true,
                                        title: {
                                            display: true, // Enable the title
                                            text: "Amount" // Y-axis label
                                        }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        position: "top" // Position of the dataset label
                                    }
                                }
                            }
                        });
                    }
                } catch (error) {
                    console.error(error);
                    alert("Failed to load chart data.");
                }
            }

            // Function to run Python script
            async function runPythonScriptAndFetchData() {
                try {
                    // Call the Python script through a backend endpoint (e.g., run_script.php)
                    const response = await fetch("run_script.php");
                    if (!response.ok) throw new Error("Failed to execute Python script");

                    // Wait for the Python script to complete and fetch updated data
                    fetchAndDisplayChart(modelSelect.value);
                } catch (error) {
                    console.error(error);
                    alert("Failed to execute Python script.");
                }
            }

            // Add event listener to the button
            runScriptButton.addEventListener("click", () => {
                runPythonScriptAndFetchData();
            });
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>