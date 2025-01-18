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

// Get filter and sorting parameters
$sort = $_GET['sort'] ?? '';
$category = $_GET['category'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

// Pagination configuration
$per_page = intval($_GET['per_page'] ?? 10);
$page = intval($_GET['page'] ?? 1); // Default to 1 if no page is provided
$offset = ($page - 1) * $per_page;

// Build query for transactions
$query_transactions = "SELECT * FROM transactions WHERE user_id = :user_id";
$params_transactions = ['user_id' => $user_id];

// Add category filter if selected
if (!empty($category)) {
    $query_transactions .= " AND category = :category";
    $params_transactions['category'] = $category;
}

// Add date range filter if selected
if (!empty($start_date) && !empty($end_date)) {
    $query_transactions .= " AND created_at BETWEEN :start_date AND :end_date";
    $params_transactions['start_date'] = $start_date;
    $params_transactions['end_date'] = $end_date;
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

// Add pagination
$query_transactions .= " LIMIT $per_page OFFSET $offset";

// Fetch transactions
$transactions = fetchData($pdo, $query_transactions, $params_transactions);

// Fetch total count of transactions for pagination
$query_count = "SELECT COUNT(*) AS total FROM transactions WHERE user_id = :user_id";
if (!empty($category)) {
    $query_count .= " AND category = :category";
}
if (!empty($start_date) && !empty($end_date)) {
    $query_count .= " AND created_at BETWEEN :start_date AND :end_date";
}

$stmt_count = $pdo->prepare($query_count);
$stmt_count->bindParam(':user_id', $user_id);
if (!empty($category)) {
    $stmt_count->bindParam(':category', $category);
}
if (!empty($start_date) && !empty($end_date)) {
    $stmt_count->bindParam(':start_date', $start_date);
    $stmt_count->bindParam(':end_date', $end_date);
}
$stmt_count->execute();
$total_transactions = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];

// Calculate total number of pages
$total_pages = ceil($total_transactions / $per_page);

// Handle income/expense actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['undo'])) {
        $result = handleUndo($pdo, $user_id);
        if ($result === true) {
            header("Location: transaction.php");
            exit();
        } else {
            echo $result;
        }
    } elseif (isset($_POST['print'])) {
        generatePDFReport($transactions, $sort, $category, $start_date, $end_date);
    } elseif (isset($_POST['amount'], $_POST['category'], $_POST['type'])) {
        $amount = filter_var($_POST['amount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $category = filter_var($_POST['category'], FILTER_SANITIZE_STRING);
        $type = $_POST['type'];

        if ($amount > 0) {
            $transaction_id = getNextTransactionID($pdo, $user_id);

            $result = handleTransaction($pdo, $user_id, $transaction_id, $type, $category, $amount);
            if ($result === true) {
                header("Location: transaction.php");
                exit();
            } else {
                echo $result;
            }
        } else {
            echo "Error: Amount must be a positive number.";
        }
    } else {
        echo "Error: Invalid or incomplete form submission.";
    }
}

// Fetch categorized income and expense data
$query_category_totals = "
    SELECT category, type, SUM(amount) AS total 
    FROM transactions 
    WHERE user_id = :user_id 
    GROUP BY category, type";

$stmt_category_totals = $pdo->prepare($query_category_totals);
$stmt_category_totals->bindParam(':user_id', $user_id);
$stmt_category_totals->execute();
$category_data = $stmt_category_totals->fetchAll(PDO::FETCH_ASSOC);

$income_data = array_filter($category_data, fn($row) => $row['type'] === 'income');
$expense_data = array_filter($category_data, fn($row) => $row['type'] === 'expense');

$income_categories = array_column($income_data, 'category');
$income_totals = array_column($income_data, 'total');
$expense_categories = array_column($expense_data, 'category');
$expense_totals = array_column($expense_data, 'total');
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

        a {
            text-decoration: none;
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
            background-color: white;
            color: #1ABC9C;
            border: 1px solid #1ABC9C;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .btn-undo:hover {
            background-color: #1ABC9C;
            color: white;
        }

        .btn-print {
            background-color: #0d6efd;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .btn-print:hover {
            background-color: white;
            color: #0d6efd;
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

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .pagination a {
            text-decoration: none;
            padding: 5px 10px;
            background-color: #007bff;
            color: white;
            border-radius: 5px;
            font-weight: bold;
        }

        .pagination a:hover {
            background-color: #0056b3;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar">
            <h4>A.I Budget Tracker</h4>
            <a href="dashboard.php">
                <img class="sidebar-icons" src="../assets/icons/dashboard_icon.svg" alt="Dashboard Icon">Dashboard
            </a>
            <a href="transaction.php" class="active">
                <img class="sidebar-icons" src="../assets/icons/transaction_icon.svg"
                    alt="Transactions Icon">Transactions
            </a>
        </div>

        <!-- Main Content -->
        <div class="flex-grow-1">
            <!-- Header -->
            <div class="transaction-header">
                <h2>Manage Transactions</h2>
                <a href="logout.php" class="btn btn-outline-primary">Log Out</a>
            </div>

            <div class="card chart-container" style="margin: 40px;">
                <h3>Expenses Chart</h3>
                <?php if (count($transactions) > 0): ?>
                    <canvas id="myPieChart" width="400" height="400"></canvas>
                <?php else: ?>
                    <div class="no-transactions">
                        <p>No transactions have been added yet. Add your income or expenses to see the chart!</p>
                    </div>
                <?php endif; ?>
            </div>
            <!-- Main Section -->
            <div class="container my-4">
                <h3 style="text-align: center; margin-top: 10px; margin-bottom: 20px;">Transaction History</h3>
                <!-- Buttons -->
                <div class="d-flex justify-content-between align-items-center">

                    <!--Sorting buttons-->
                    <form method="GET" action="transaction.php" class="filter-form">
                        <div class="form-group">
                            <label for="category">Sort By:</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">-- Select Category --</option>
                                <option value="groceries" <?= isset($_GET['category']) && $_GET['category'] === 'groceries' ? 'selected' : '' ?>>Groceries</option>
                                <option value="rent" <?= isset($_GET['category']) && $_GET['category'] === 'rent' ? 'selected' : '' ?>>Rent</option>
                                <option value="clothing" <?= isset($_GET['category']) && $_GET['category'] === 'clothing' ? 'selected' : '' ?>>Clothing</option>
                                <option value="food" <?= isset($_GET['category']) && $_GET['category'] === 'food' ? 'selected' : '' ?>>Food</option>
                                <option value="transportation" <?= isset($_GET['category']) && $_GET['category'] === 'transportation' ? 'selected' : '' ?>>Transportation</option>
                                <option value="phoneBill" <?= isset($_GET['category']) && $_GET['category'] === 'phoneBill' ? 'selected' : '' ?>>Phone Bill</option>
                                <option value="selfCare" <?= isset($_GET['category']) && $_GET['category'] === 'selfCare' ? 'selected' : '' ?>>Self-Care</option>
                                <option value="miscellaneous" <?= isset($_GET['category']) && $_GET['category'] === 'miscellaneous' ? 'selected' : '' ?>>Miscellaneous</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="start_date">Start Date:</label>
                            <input type="date" name="start_date" id="start_date"
                                value="<?= $_GET['start_date'] ?? '' ?>">
                        </div>

                        <div class="form-group">
                            <label for="end_date">End Date:</label>
                            <input type="date" name="end_date" id="end_date" value="<?= $_GET['end_date'] ?? '' ?>">
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="apply-btn">Apply</button>
                            <button type="button" class="reset-btn" onclick="resetForm()">Reset</button>
                        </div>
                    </form>

                    <div class="btn-container">
                        <button class="btn btn-income" data-bs-toggle="modal"
                            data-bs-target="#incomeModal">Income</button>
                        <button class="btn btn-expenses" data-bs-toggle="modal"
                            data-bs-target="#expensesModal">Expenses</button>
                        <form action="transaction.php" method="POST" style="display:inline;">
                            <button class="btn-undo" name="undo" type="submit">Undo</button>
                            <button class="btn-print" type="submit" name="print">Print</button>
                        </form>
                    </div>
                </div>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Type</th>
                            <th>
                                Amount
                                <span class="sorter">
                                    <a href="?sort=amount_asc"> &#9650; <!-- Up Arrow --> </a>
                                    <a href="?sort=amount_desc"> &#9660; <!-- Down Arrow --> </a>
                                </span>
                            </th>
                            <th style="justify-content: space-between;">
                                Date
                                <span class="sorter">
                                    <a href="?sort=date_asc"> &#9650; <!-- Up Arrow --> </a>
                                    <a href="?sort=date_desc"> &#9660; <!-- Down Arrow --> </a>
                                </span>
                            </th>
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
                                <td colspan="4" style="text-align: center;">No transactions available.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a
                            href="?page=<?= $i ?>&category=<?= htmlspecialchars($category) ?>&sort=<?= htmlspecialchars($sort) ?>&start_date=<?= htmlspecialchars($start_date) ?>&end_date=<?= htmlspecialchars($end_date) ?>"><?= $i ?></a>
                    <?php endfor; ?>
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
            </div>
        </div>
    </div>

    <script>
        function resetForm() {
            // Reset form fields
            document.querySelector('.filter-form').reset();

            // Remove the query parameters from the URL to reset the filters
            const url = new URL(window.location.href);
            url.searchParams.delete('category');
            url.searchParams.delete('start_date');
            url.searchParams.delete('end_date');

            // Reload the page with the filters removed
            window.location.href = url.toString();
        }


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
                groceries: "#35f238",
                rent: "#5446ff",
                clothing: "#f23582",
                food: "#f23538",
                transportation: "#0b0047",
                phoneBill: "#00BFFF",
                selfCare: "#35F295",
                miscellaneous: "#F2DE35"
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
            document.head.appendChild(style);
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>