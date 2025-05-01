<?php
if (isset($_GET['model'])) {
    $model = $_GET['model']; // e.g., "Linear_Regression"
    $file_path = "../predictions/" . $model . "_chart.json";

    if (file_exists($file_path)) {
        header('Content-Type: application/json');
        echo file_get_contents($file_path);
    } else {
        http_response_code(404);
        echo json_encode(["error" => "File not found"]);
    }
} else {
    http_response_code(400);
    echo json_encode(["error" => "Model not specified"]);
}
?>
