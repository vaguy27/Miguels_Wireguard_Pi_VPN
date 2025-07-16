<?php
// api/toggle.php
header('Content-Type: application/json');
session_start();

// Check if user is authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$action = isset($input['action']) ? $input['action'] : '';

if (!in_array($action, ['start', 'stop'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action. Use "start" or "stop".']);
    exit;
}

function executeCommand($command) {
    $output = [];
    $returnCode = 0;
    exec($command . ' 2>&1', $output, $returnCode);
    return [
        'output' => $output, 
        'returnCode' => $returnCode,
        'outputString' => implode("\n", $output)
    ];
}

function startWireGuard() {
    // Check if config file exists
    if (!file_exists('/etc/wireguard/wg0.conf')) {
        return [
            'success' => false, 
            'message' => 'WireGuard configuration file not found. Please upload a configuration first.'
        ];
    }

    // Try to start WireGuard
    $result = executeCommand('sudo wg-quick up wg0');
    
    if ($result['returnCode'] === 0) {
        return [
            'success' => true,
            'message' => 'WireGuard started successfully'
        ];
    } else {
        // Check if it's already running
        if (strpos($result['outputString'], 'already exists') !== false) {
            return [
                'success' => true,
                'message' => 'WireGuard is already running'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to start WireGuard: ' . $result['outputString']
        ];
    }
}

function stopWireGuard() {
    // Try to stop WireGuard
    $result = executeCommand('sudo wg-quick down wg0');
    
    if ($result['returnCode'] === 0) {
        return [
            'success' => true,
            'message' => 'WireGuard stopped successfully'
        ];
    } else {
        // Check if it's already stopped
        if (strpos($result['outputString'], 'is not a WireGuard interface') !== false ||
            strpos($result['outputString'], 'does not exist') !== false) {
            return [
                'success' => true,
                'message' => 'WireGuard is already stopped'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to stop WireGuard: ' . $result['outputString']
        ];
    }
}

try {
    if ($action === 'start') {
        $result = startWireGuard();
    } else {
        $result = stopWireGuard();
    }
    
    // Log the action
    error_log("WireGuard {$action} action by user: " . ($_SESSION['username'] ?? 'unknown'));
    
    echo json_encode($result);

} catch (Exception $e) {
    error_log("WireGuard toggle error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}
?>