<?php

function handleTransaction($pdo, $user_id, $type, $category, $amount)
{
    if ($type !== 'income' && $type !== 'expense') {
        return "Invalid transaction type.";
    }

    $valid_income_categories = ['employment', 'business', 'investment'];
    $valid_expense_categories = ['groceries', 'rent', 'clothing', 'food', 'transportation', 'phoneBill', 'selfCare', 'miscellaneous'];

    $validCategories = $type == 'income' ? $valid_income_categories : $valid_expense_categories;
    if (!in_array($category, $validCategories)) {
        return "Invalid category.";
    }

    // Insert the data into the database
    $query = "INSERT INTO transactions (user_id, amount, category, type) VALUES (:user_id, :amount, :category, :type)";
    $params = ['user_id' => $user_id, 'amount' => $amount, 'category' => $category, 'type' => $type];

    if (executeQuery($pdo, $query, $params)) {
        return true;  // Success
    }
    return "Error inserting transaction.";
}

function handleUndo($pdo, $user_id)
{
    $query = "SELECT id FROM transactions WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 1";
    $stmt = executeQuery($pdo, $query, ['user_id' => $user_id]);
    $transaction = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;

    if ($transaction) {
        $deleteQuery = "DELETE FROM transactions WHERE id = :id";
        $deleteStmt = executeQuery($pdo, $deleteQuery, ['id' => $transaction['id']]);
        return $deleteStmt ? true : "Error deleting transaction.";
    }
    return "No transactions found to undo.";
}
?>