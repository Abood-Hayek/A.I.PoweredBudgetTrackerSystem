<?php
// Include the database connection file
include('db_connection.php');

// Start session to check if the user is logged in
session_start();

// If not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Handle form submissions for adding income and expenses
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize the input values
    $amount = filter_var($_POST['amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $category = filter_var($_POST['category'], FILTER_SANITIZE_STRING);
    $type = $_POST['type']; // Income or Expense
    $user_id = $_SESSION['user_id']; // Get the user ID from session

    // Validate that amount is a positive number
    if ($amount <= 0) {
        echo "Error: Amount must be a positive number.";
        exit();
    }

    // Validate type (either 'income' or 'expense')
    if ($type !== 'income' && $type !== 'expense') {
        echo "Error: Invalid transaction type.";
        exit();
    }

    // Validate category (for income and expense categories)
    $valid_income_categories = ['employment', 'business', 'investment'];
    $valid_expense_categories = ['groceries', 'rent', 'clothing', 'food', 'transportation', 'phoneBill', 'selfCare', 'miscellaneous'];

    if ($type == 'income' && !in_array($category, $valid_income_categories)) {
        echo "Error: Invalid income category.";
        exit();
    } elseif ($type == 'expense' && !in_array($category, $valid_expense_categories)) {
        echo "Error: Invalid expense category.";
        exit();
    }

    // Insert the data into the database
    try {
        // Prepare the SQL query to include user_id
        $query = "INSERT INTO transactions (user_id, amount, category, type) VALUES (:user_id, :amount, :category, :type)";
        $stmt = $pdo->prepare($query);

        // Bind the values to the query parameters
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':type', $type);

        // Execute the query
        $stmt->execute();

        // Redirect or display a success message
        header("Location: transaction.php");
        exit();
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }


}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['undo'])) {
    try {
        // Fetch the most recent transaction for the user
        $query = "SELECT id FROM transactions WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 1";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        // Get the transaction ID of the most recent transaction
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($transaction) {
            // Delete the most recent transaction
            $deleteQuery = "DELETE FROM transactions WHERE id = :id";
            $deleteStmt = $pdo->prepare($deleteQuery);
            $deleteStmt->bindParam(':id', $transaction['id']);
            $deleteStmt->execute();

            // Reload the page to reflect the changes
            header("Location: transaction.php");
            exit();
        } else {
            echo "No transactions found to undo.";
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

// Query the database for income, expenses, and balance
$user_id = $_SESSION['user_id']; // Get the user ID from session
$income_total = 0;
$expense_total = 0;
$balance = 0;

// Get total income
$query = "SELECT SUM(amount) AS total_income FROM transactions WHERE user_id = :user_id AND type = 'income'";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$income = $stmt->fetch(PDO::FETCH_ASSOC);
$income_total = $income['total_income'] ? $income['total_income'] : 0; // Default to 0 if empty

// Get total expenses
$query = "SELECT SUM(amount) AS total_expenses FROM transactions WHERE user_id = :user_id AND type = 'expense'";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$expense = $stmt->fetch(PDO::FETCH_ASSOC);
$expense_total = $expense['total_expenses'] ? $expense['total_expenses'] : 0; // Default to 0 if empty

// Calculate balance
$balance = $income_total - $expense_total;

$query_income = "SELECT category, SUM(amount) AS total_income FROM transactions WHERE user_id = :user_id AND type = 'income' GROUP BY category";
$stmt_income = $pdo->prepare($query_income);
$stmt_income->bindParam(':user_id', $user_id);
$stmt_income->execute();
$income_data = $stmt_income->fetchAll(PDO::FETCH_ASSOC);

// Database query to get expense data
$query_expenses = "SELECT category, SUM(amount) AS total_expenses FROM transactions WHERE user_id = :user_id AND type = 'expense' GROUP BY category";
$stmt_expenses = $pdo->prepare($query_expenses);
$stmt_expenses->bindParam(':user_id', $user_id);
$stmt_expenses->execute();
$expense_data = $stmt_expenses->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for JavaScript
$expense_categories = [];
$income_categories = [];

foreach ($income_data as $row) {
    $income_categories[] = $row['category'];
    $income_totals[] = $row['total_income'];
}

foreach ($expense_data as $row) {
    $expense_categories[] = $row['category'];
    $expense_totals[] = $row['total_expenses'];
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Transactions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@700&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap');

        .raleway {
            font-family: "Raleway", serif;
            font-optical-sizing: auto;
            font-weight: 700;
            font-style: normal;
        }

        body.raleway {
            background-color: #f9f9f9;
        }

        .sidebar {
            background: linear-gradient(to bottom, #1ABC9C, #148F77);
            color: white;
            height: 100vh;
            padding: 20px;
        }

        .sidebar a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .sidebar a.active,
        .sidebar a:hover {
            background-color: #28c6a7;
        }

        .transaction-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #ddd;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .main-content {
            padding: 20px;
        }

        .btn-container {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 10px;
        }

        .btn-income {
            background-color: #1ABC9C;
            color: white;
            border: none;
        }

        .btn-expenses {
            background-color: #E74C3C;
            color: white;
            border: none;
        }

        .btn-undo {
            border: 1px solid #333;
            color: #333;
            background-color: transparent;
        }

        .modal-header {
            background-color: #1ABC9C;
            color: white;
        }

        .btn-confirm {
            background-color: #16A085;
            color: white;
        }

        .btn-cancel {
            background-color: #E74C3C;
            color: white;
        }

        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .chart-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 450px;
            padding: 20px;
            box-sizing: border-box;
            overflow: hidden;
        }

        .chartjs-legend {
            display: flex !important;
            justify-content: center;
            flex-wrap: nowrap;
            gap: 15px;
            overflow-x: auto;
            padding: 10px 0;
            max-width: 100%;
            box-sizing: border-box;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar">
            <h4>A.I Budget Tracker</h4>
            <a href="dashboard.php">Dashboard</a>
            <a href="transaction.php" class="active">Transactions</a>
        </div>

        <!-- Main Content -->
        <div class="flex-grow-1">
            <!-- Header -->
            <div class="transaction-header">
                <h2>Manage Transactions</h2>
                <a href="logout.php" class="btn btn-outline-primary">Log Out</a>
            </div>

            <!-- Main Section -->
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

                <!-- Buttons aligned to the right -->
                <div class="btn-container">
                    <button class="btn btn-income" data-bs-toggle="modal" data-bs-target="#incomeModal">Income</button>
                    <button class="btn btn-expenses" data-bs-toggle="modal"
                        data-bs-target="#expensesModal">Expenses</button>
                    <form action="transaction.php" method="POST" style="display:inline;">
                        <button class="btn btn-undo" name="undo" type="submit">Undo</button>
                    </form>
                </div>

                <!-- Income Modal -->
                <div class="modal fade" id="incomeModal" tabindex="-1" aria-labelledby="incomeModalLabel"
                    aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header" id="incomeModalHeader">
                                <h5 class="modal-title" id="incomeModalLabel">Add Income</h5>
                            </div>
                            <div class="modal-body">
                                <form action="transaction.php" method="POST">
                                    <div class="mb-3">
                                        <label for="incomeAmount" class="form-label">Amount:</label>
                                        <input type="number" class="form-control" id="incomeAmount" name="amount"
                                            placeholder="Enter amount" step="0.01" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="incomeCategory" class="form-label">Category:</label>
                                        <select class="form-select" id="incomeCategory" name="category" required>
                                            <option value="employment">Employment</option>
                                            <option value="business">Business</option>
                                            <option value="investment">Investment</option>
                                        </select>
                                    </div>
                                    <input type="hidden" name="type" value="income">
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-confirm">Confirm</button>
                                        <button type="button" class="btn btn-cancel"
                                            data-bs-dismiss="modal">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Expenses Modal -->
                <div class="modal fade" id="expensesModal" tabindex="-1" aria-labelledby="expensesModalLabel"
                    aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header" id="expensesModalHeader">
                                <h5 class="modal-title" id="expensesModalLabel">Add Expenses</h5>
                            </div>
                            <div class="modal-body">
                                <form action="transaction.php" method="POST">
                                    <div class="mb-3">
                                        <label for="expenseAmount" class="form-label">Amount:</label>
                                        <input type="number" class="form-control" id="expenseAmount" name="amount"
                                            placeholder="Enter amount" step="0.01" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="expenseCategory" class="form-label">Category:</label>
                                        <select class="form-select" id="expenseCategory" name="category" required>
                                            <option value="groceries">Groceries</option>
                                            <option value="rent">Rent</option>
                                            <option value="clothing">Clothing</option>
                                            <option value="food">Food</option>
                                            <option value="transportation">Transportation</option>
                                            <option value="phoneBill">Phone Bill</option>
                                            <option value="selfCare">Self-Care</option>
                                            <option value="miscellaneous">Miscellaneous</option>
                                        </select>
                                    </div>
                                    <input type="hidden" name="type" value="expense">
                                    <div class="modal-footer">
                                        <button type="submit" class="btn btn-confirm">Confirm</button>
                                        <button type="button" class="btn btn-cancel"
                                            data-bs-dismiss="modal">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chart -->
                <div class="card chart-container">
                    <h3>Expenses Chart</h3>
                    <canvas id="myPieChart" width="400" height="400"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Data passed from PHP to JavaScript
            const expenseCategories = <?php echo json_encode($expense_categories); ?>;
            const expenseTotals = <?php echo json_encode($expense_totals); ?>;

            console.log("Expense Categories: ", expenseCategories);
            console.log("Expense Totals: ", expenseTotals);

            const categoryDisplayNames = {
                groceries: "Groceries",
                rent: "Rent",
                clothing: "Clothing",
                food: "Food",
                transportation: "Transportation",
                phoneBill: "Phone Bill",
                selfCare: "Self-Care",
                miscellaneous: "Misc"
            };

            const expenseColors = {
                groceries: "#36d555",
                rent: "#2E8B57",
                clothing: "#FFD700",
                food: "#f6472b",
                transportation: "#060118",
                phoneBill: "#00BFFF",
                selfCare: "#c623cd",
                miscellaneous: "#808080"
            };

            const backgroundColors = expenseCategories.map(category =>
                expenseColors[category] || "#808080" // Default to gray if not found
            );

            const friendlyLabels = expenseCategories.map(category =>
                categoryDisplayNames[category] || category
            );

            const ctx = document.getElementById('myPieChart').getContext('2d');
            const myPieChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: friendlyLabels,
                    datasets: [{
                        label: 'Expenses',
                        data: expenseTotals,
                        backgroundColor: backgroundColors,
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            align: 'center',
                            labels: {
                                boxWidth: 15,
                                padding: 10,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (tooltipItem) {
                                    const displayName = categoryDisplayNames[tooltipItem.label] || tooltipItem.label;
                                    const value = parseFloat(tooltipItem.raw);
                                    return !isNaN(value)
                                        ? `${displayName}: $${value.toFixed(2)}`
                                        : `${displayName}: ${tooltipItem.raw}`;
                                }
                            }
                        }
                    },
                    layout: {
                        padding: { top: 20 }
                    }
                }
            });

            // Add inline CSS for legend styling
            const style = document.createElement('style');
            style.innerHTML = `
            .chartjs-legend {
                display: flex !important;
                justify-content: center;
                flex-wrap: nowrap;
                gap: 15px;
                overflow-x: auto;
            }`;
            document.head.appendChild(style);
        });
    </script>


    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>