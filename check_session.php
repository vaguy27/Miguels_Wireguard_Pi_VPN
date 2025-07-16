<?php
// check_session.php
session_start();
header('Content-Type: application/json');

echo json_encode([
    'authenticated' => isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true,
    'username' => $_SESSION['username'] ?? null,
    'login_time' => $_SESSION['login_time'] ?? null
]);
?>
