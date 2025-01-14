<?php
// db_helper.php

function executeQuery($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt;
    } catch (PDOException $e) {
        // Log the error message to a log file or display a generic message
        echo "Error: " . $e->getMessage();
        exit();
    }
}

function fetchData($pdo, $query, $params = []) {
    try {
        $stmt = executeQuery($pdo, $query, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
        exit();
    }
}
?>