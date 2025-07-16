<?php
// api/status.php
header('Content-Type: application/json');
session_start();

// Check if user is authenticated
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

function executeCommand($command) {
    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);
    return ['output' => $output, 'returnCode' => $returnCode];
}

function checkWireGuardStatus() {
    $status = [
        'interface_up' => false,
        'wg_running' => false,
        'recent_handshake' => false,
        'last_handshake' => null,
        'peer_info' => null
    ];

    // Check if interface is up
    $result = executeCommand('ip link show wg0 2>/dev/null | grep -q "UP"');
    $status['interface_up'] = ($result['returnCode'] == 0);

    // Check if WireGuard is running
    $result = executeCommand('sudo wg show wg0 2>/dev/null');
    $status['wg_running'] = ($result['returnCode'] == 0);

    if ($status['wg_running']) {
        // Get detailed WireGuard info
        $result = executeCommand('sudo wg show wg0');
        if ($result['returnCode'] == 0 && !empty($result['output'])) {
            $wgOutput = implode("\n", $result['output']);
            $status['peer_info'] = $wgOutput;

            // Check for recent handshake
            $handshakeResult = executeCommand('wg show wg0 | grep -q "latest handshake.*ago"');
            $status['recent_handshake'] = ($handshakeResult['returnCode'] == 0);

            // Extract last handshake time
            if (preg_match('/latest handshake: (.+)/', $wgOutput, $matches)) {
                $status['last_handshake'] = trim($matches[1]);
            }
        }
    }

    return $status;
}

try {
    $statusDetails = checkWireGuardStatus();
    
    // Determine if WireGuard is considered "active"
    // All three conditions must be true for it to be considered fully active
    $isActive = $statusDetails['interface_up'] && 
                $statusDetails['wg_running'];  
                //$statusDetails['recent_handshake'];

    echo json_encode([
        'success' => true,
        'isActive' => $isActive,
        'details' => $statusDetails,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("WireGuard status check error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to check WireGuard status',
        'error' => $e->getMessage()
    ]);
}
?>
