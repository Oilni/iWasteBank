<?php
require_once 'config.php';

$API_KEY = 'IWASTEBANK_TELEGRAM_2026';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$apiKey = $_POST['api_key'] ?? '';
if (!hash_equals($API_KEY, $apiKey)) {
    http_response_code(403);
    exit('Invalid API Key');
}

$binName = trim($_POST['bin_name'] ?? '');
$message = trim($_POST['message'] ?? '');
$status = trim($_POST['status'] ?? 'ส่งสำเร็จ');

if ($binName === '' || $message === '') {
    http_response_code(400);
    exit('Missing data');
}

$binName = mb_substr($binName, 0, 100);
$message = mb_substr($message, 0, 1000);
$status = mb_substr($status, 0, 30);

$stmt = $conn->prepare(
    "INSERT INTO telegram_logs (bin_name, message, status) VALUES (?, ?, ?)"
);

if (!$stmt) {
    http_response_code(500);
    exit('Prepare failed: ' . $conn->error);
}

$stmt->bind_param("sss", $binName, $message, $status);

if ($stmt->execute()) {
    http_response_code(200);
    echo 'log saved';
} else {
    http_response_code(500);
    echo 'Database error: ' . $stmt->error;
}

$stmt->close();
?>
