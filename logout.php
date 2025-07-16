<?php
// logout.php
session_start();
header('Content-Type: application/json');

// Destroy session
session_destroy();

echo json_encode(['success' => true]);
?>
