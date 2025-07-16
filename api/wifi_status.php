<?php
// api/wifi_status.php
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
    exec($command . ' 2>&1', $output, $returnCode);
    return [
        'output' => $output, 
        'returnCode' => $returnCode,
        'outputString' => implode("\n", $output)
    ];
}

function getWiFiStatus() {
    $status = [
        'device_status' => 'unknown',
        'connection_name' => null,
        'ssid' => null,
        'mode' => 'unknown',
        'channel' => null,
        'signal' => null,
        'ip_address' => null,
        'is_hotspot' => false,
        'is_connected' => false
    ];

    // Get device status
    $result = executeCommand('nmcli device status | grep wlan0');
    if ($result['returnCode'] === 0 && !empty($result['output'])) {
        $deviceInfo = preg_split('/\s+/', trim($result['output'][0]));
        if (count($deviceInfo) >= 3) {
            $status['device_status'] = $deviceInfo[2];
            $status['connection_name'] = $deviceInfo[3] ?? null;
            $status['is_connected'] = $deviceInfo[2] === 'connected';
        }
    }

    if ($status['is_connected'] && $status['connection_name']) {
        // Get connection details
        $result = executeCommand("nmcli connection show '{$status['connection_name']}'");
        if ($result['returnCode'] === 0) {
            $connectionDetails = $result['outputString'];
            
            // Extract SSID
            if (preg_match('/802-11-wireless\.ssid:\s+(.+)/', $connectionDetails, $matches)) {
                $status['ssid'] = trim($matches[1]);
            }
            
            // Extract mode (ap or infrastructure)
            if (preg_match('/802-11-wireless\.mode:\s+(.+)/', $connectionDetails, $matches)) {
                $status['mode'] = trim($matches[1]);
                $status['is_hotspot'] = (trim($matches[1]) === 'ap');
            }
            
            // Extract IP address
            if (preg_match('/IP4\.ADDRESS\[1\]:\s+([0-9.]+)\/\d+/', $connectionDetails, $matches)) {
                $status['ip_address'] = $matches[1];
            }
        }
        
        // Get WiFi specific info if available
        $result = executeCommand('nmcli device wifi list | grep "^\\*"');
        if ($result['returnCode'] === 0 && !empty($result['output'])) {
            $wifiInfo = preg_split('/\s+/', trim($result['output'][0]));
            if (count($wifiInfo) >= 8) {
                $status['channel'] = $wifiInfo[5] ?? null;
                $status['signal'] = $wifiInfo[7] ?? null;
            }
        }
    }

    return $status;
}

try {
    $wifiStatus = getWiFiStatus();
    
    echo json_encode([
        'success' => true,
        'wifi_status' => $wifiStatus,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("WiFi status check error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to check WiFi status',
        'error' => $e->getMessage()
    ]);
}
?>