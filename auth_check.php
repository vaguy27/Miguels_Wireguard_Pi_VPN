<?php
// auth_check.php - Include this at the top of protected pages
session_start();

function requireAuth() {
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    // Check session timeout (30 minutes)
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 1800) {
        session_destroy();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired']);
        exit;
    }
}
?>
