<?php
require('../fpdf/fpdf.php');

function handleTransaction($pdo, $user_id, $transaction_id, $type, $category, $amount)
{
    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, transaction_id, type, category, amount) 
                           VALUES (:user_id, :transaction_id, :type, :category, :amount)");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':transaction_id', $transaction_id);
    $stmt->bindParam(':type', $type);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':amount', $amount);

    if ($stmt->execute()) {
        return true;
    } else {
        return "Error: Could not insert transaction.";
    }
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

function getNextTransactionID($pdo, $user_id)
{
    // Fetch the last transaction ID for the user
    $query = "SELECT transaction_id FROM transactions WHERE user_id = :user_id ORDER BY transaction_id DESC LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['user_id' => $user_id]);
    $last_transaction = $stmt->fetchColumn();

    // If no transactions exist for the user, start with 'T001'
    if (!$last_transaction) {
        return $user_id . '-T001';
    }

    // Extract the numeric part of the transaction ID (e.g., 001 from 'U1-T001')
    preg_match('/T(\d+)/', $last_transaction, $matches);
    $last_number = (int) $matches[1];  // Get the last number part

    // Increment the transaction number
    $new_number = str_pad($last_number + 1, 3, '0', STR_PAD_LEFT);

    // Return the new transaction ID in the format 'U1-TXXX'
    return $user_id . '-T' . $new_number;
}

function generatePDFReport($transactions, $sort, $category, $start_date, $end_date)
{
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);

    // Set custom colors based on theme
    $headerColor = [26, 82, 118];  // Deep Blue
    $rowColor1 = [255, 255, 255]; // White
    $rowColor2 = [230, 245, 241]; // Light Green 
    $textColor = [21, 67, 96];    // Dark Text Blue
    $titleColor = [26, 188, 156]; // Green

    // Title Section
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor($headerColor[0], $headerColor[1], $headerColor[2]);
    $pdf->Cell(190, 10, 'Transaction Report', 0, 1, 'C');
    $pdf->Ln(5);

    // Summary Section
    $pdf->SetFont('Arial', '', 12);
    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
    $pdf->Cell(50, 8, 'Generated On:', 0, 0);
    $pdf->Cell(50, 8, date('Y-m-d H:i:s'), 0, 1);
    $pdf->Ln(5);

    // Table Header
    $pdf->SetFillColor($headerColor[0], $headerColor[1], $headerColor[2]);
    $pdf->SetTextColor(255, 255, 255); // White Text
    $pdf->SetFont('Arial', 'B', 12);

    $pdf->Cell(30, 10, 'Transaction ID', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Type', 1, 0, 'C', true);
    $pdf->Cell(50, 10, 'Category', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Amount', 1, 0, 'C', true);
    $pdf->Cell(50, 10, 'Date', 1, 1, 'C', true);

    // Table Rows
    $pdf->SetFont('Arial', '', 12);
    $fill = false; // Alternate row colors
    foreach ($transactions as $transaction) {
        $pdf->SetFillColor(
            $fill ? $rowColor2[0] : $rowColor1[0],
            $fill ? $rowColor2[1] : $rowColor1[1],
            $fill ? $rowColor2[2] : $rowColor1[2]
        );
        $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);

        $pdf->Cell(30, 10, $transaction['transaction_id'], 1, 0, 'C', $fill);
        $pdf->Cell(30, 10, ucfirst($transaction['type']), 1, 0, 'C', $fill);
        $pdf->Cell(50, 10, ucfirst($transaction['category']), 1, 0, 'C', $fill);
        $pdf->Cell(30, 10, number_format($transaction['amount'], 2), 1, 0, 'C', $fill);
        $pdf->Cell(50, 10, $transaction['created_at'], 1, 1, 'C', $fill);

        $fill = !$fill; // Toggle fill
    }

    // Footer Section
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
    $pdf->Cell(190, 10, 'Thank you for using our budget tracking system!', 0, 1, 'C');

    // Output the PDF
    $pdf->Output('D', 'Transaction_Report.pdf'); // Forces a download with the name 'Transaction_Report.pdf'
}
?>