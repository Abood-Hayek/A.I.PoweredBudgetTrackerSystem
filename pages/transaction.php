<?php
include('../database/db_connection.php');
include('../database/db_helper.php');
include('../database/handleTransaction.php');

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$sort = $_GET['sort'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Build query for transactions
$query_transactions = "SELECT * FROM transactions WHERE user_id = :user_id";

// Add date range filter if selected
if (!empty($start_date) && !empty($end_date)) {
    $query_transactions .= " AND created_at BETWEEN :start_date AND :end_date";
}

// Add sorting
switch ($sort) {
    case 'amount_asc':
        $query_transactions .= " ORDER BY amount ASC";
        break;
    case 'amount_desc':
        $query_transactions .= " ORDER BY amount DESC";
        break;
    case 'category':
        $query_transactions .= " ORDER BY category ASC";
        break;
    case 'type':
        $query_transactions .= " ORDER BY type ASC";
        break;
    default:
        $query_transactions .= " ORDER BY created_at DESC";
}

// Prepare query parameters
$params_transactions = ['user_id' => $user_id];
if (!empty($start_date) && !empty($end_date)) {
    $params_transactions['start_date'] = $start_date;
    $params_transactions['end_date'] = $end_date;
}

// Fetch transactions
$transactions = fetchData($pdo, $query_transactions, $params_transactions);

//Income and expense handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['undo'])) {
        $result = handleUndo($pdo, $user_id);
        if ($result === true) {
            header("Location: transaction.php");
            exit();
        } else {
            echo $result;  // Show error if any
        }
    } else {
        // Handle income/expense form submission
        $amount = filter_var($_POST['amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $category = filter_var($_POST['category'], FILTER_SANITIZE_STRING);
        $type = $_POST['type'];

        if ($amount <= 0) {
            echo "Error: Amount must be a positive number.";
            exit();
        }

        $result = handleTransaction($pdo, $user_id, $type, $category, $amount);
        if ($result === true) {
            header("Location: transaction.php");
            exit();
        } else {
            echo $result;  // Show error if any
        }
    }
}

// Query the database for income, expenses, and balance
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
    <link rel="stylesheet" href="../assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Transaction Header */
        .transaction-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #ddd;
        }

        /* Main Content */
        .main-content {
            padding: 20px;
        }

        /* Chart Container */
        .chart-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 450px;
            padding: 20px;
            margin-top: 20px;
            margin-bottom: 20px;
            box-sizing: border-box;
            overflow: hidden;
        }

        .filter-form {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .filter-form label {
            font-weight: bold;
        }

        .filter-form select,
        .filter-form input {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .filter-form button {
            background-color: #1ABC9C;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
        }

        .filter-form button:hover {
            background-color: #148F77;
        }


        /* Button Container */
        .btn-container {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 20px;

        }

        /* Button Styles */
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

        /* Modal Styles */
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

        /* Remove spin button for number inputs */
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
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

            <div class="card chart-container">
                <h3>Expenses Chart</h3>
                <canvas id="myPieChart" width="400" height="400"></canvas>
            </div>
            <!-- Main Section -->
            <div class="container my-4">
                <h3 style="text-align: center; margin-top: 10px; margin-bottom: 20px;">Transaction History</h3>
                <!-- Buttons -->
                <div class="d-flex justify-content-between align-items-center">

                    <!--Sorting buttons-->
                    <form method="GET" action="transaction.php" class="filter-form">
                        <label for="sort">Sort By:</label>
                        <select name="sort" id="sort">
                            <option value="">-- Select --</option>
                            <option value="amount_asc" <?= isset($_GET['sort']) && $_GET['sort'] === 'amount_asc' ? 'selected' : '' ?>>Amount (Low to High)</option>
                            <option value="amount_desc" <?= isset($_GET['sort']) && $_GET['sort'] === 'amount_desc' ? 'selected' : '' ?>>Amount (High to Low)</option>
                            <option value="category" <?= isset($_GET['sort']) && $_GET['sort'] === 'category' ? 'selected' : '' ?>>Category</option>
                            <option value="type" <?= isset($_GET['sort']) && $_GET['sort'] === 'type' ? 'selected' : '' ?>>
                                Type
                            </option>
                        </select>

                        <label for="start_date">Start Date:</label>
                        <input type="date" name="start_date" id="start_date" value="<?= $_GET['start_date'] ?? '' ?>">

                        <label for="end_date">End Date:</label>
                        <input type="date" name="end_date" id="end_date" value="<?= $_GET['end_date'] ?? '' ?>">

                        <button type="submit">Apply</button>
                    </form>

                    <div class="btn-container">
                        <button class="btn btn-income" data-bs-toggle="modal"
                            data-bs-target="#incomeModal">Income</button>
                        <button class="btn btn-expenses" data-bs-toggle="modal"
                            data-bs-target="#expensesModal">Expenses</button>
                        <form action="transaction.php" method="POST" style="display:inline;">
                            <button class="btn btn-undo" name="undo" type="submit">Undo</button>
                        </form>
                    </div>
                </div>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($transactions) > 0): ?>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($transaction['category']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['type']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['amount']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">No transactions available.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

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
                miscellaneous: "#f2ca35"
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