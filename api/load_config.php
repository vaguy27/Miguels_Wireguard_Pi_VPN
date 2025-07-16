<?php
header('Content-Type: application/json');

// Check if user is authenticated (implement your auth logic here)
session_start();
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$configPath = '/etc/wireguard/wg0.conf';

if (file_exists($configPath)) {
    $configContent = file_get_contents($configPath);
    if ($configContent !== false) {
        echo json_encode([
            'success' => true,
            'config' => $configContent,
            'message' => 'Configuration loaded from /etc/wireguard/wg0.conf'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error reading configuration file'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Configuration file not found'
    ]);
}
?>
