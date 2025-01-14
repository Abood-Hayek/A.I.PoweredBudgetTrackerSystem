<?php
// Connect to the database
$host = 'localhost'; // Replace with your database host
$dbname = 'budgettracking'; // Replace with your database name
$username = 'root'; // Replace with your database username
$password = ''; // Replace with your database password

try {
    $pdo = new PDO(dsn: "mysql:host=$host;dbname=$dbname;charset=utf8", username: $username, password: $password);
    $pdo->setAttribute(attribute: PDO::ATTR_ERRMODE, value: PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
