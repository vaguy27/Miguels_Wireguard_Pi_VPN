<?php
header('Content-Type: application/json');
session_start();

// Check if user is authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get the JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['config']) || empty($input['config'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No configuration data provided'
    ]);
    exit;
}

$configPath = '/etc/wireguard/wg0.conf';
$configContent = $input['config'];

// Basic WireGuard configuration validation
if (!preg_match('/^\s*\[Interface\].*\[Peer\]/s', $configContent)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid WireGuard configuration format. Must contain [Interface] and [Peer] sections.'
    ]);
    exit;
}

// Validate that required fields are present
if (!preg_match('/PrivateKey\s*=/', $configContent) || !preg_match('/PublicKey\s*=/', $configContent)) {
    echo json_encode([
        'success' => false,
        'message' => 'Configuration must contain PrivateKey in [Interface] and PublicKey in [Peer] sections.'
    ]);
    exit;
}

// Create directory if it doesn't exist
$configDir = dirname($configPath);
if (!is_dir($configDir)) {
    if (!mkdir($configDir, 0755, true)) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create wireguard directory'
        ]);
        exit;
    }
}

// Save the configuration file
if (file_put_contents($configPath, $configContent) !== false) {
    // Set appropriate permissions
    chmod($configPath, 0600);
    
    // Log the successful configuration save
    error_log("WireGuard configuration saved by user: " . ($_SESSION['username'] ?? 'unknown'));
    
    echo json_encode([
        'success' => true,
        'message' => 'Configuration saved to /etc/wireguard/wg0.conf'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to write configuration file'
    ]);
}
?>
