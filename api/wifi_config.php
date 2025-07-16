<?php
// api/wifi_config.php
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

if (!isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Action parameter required']);
    exit;
}

$action = $input['action'];

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

function validateSSID($ssid) {
    // SSID length: 1-32 characters
    if (strlen($ssid) < 1 || strlen($ssid) > 32) {
        return false;
    }
    // Basic character validation (allow most printable characters)
    return preg_match('/^[\x20-\x7E]+$/', $ssid);
}

function validatePassword($password) {
    // WPA password: 8-63 characters
    if (strlen($password) < 8 || strlen($password) > 63) {
        return false;
    }
    // Basic character validation
    return preg_match('/^[\x20-\x7E]+$/', $password);
}

function updateHotspotConfig($ssid, $password) {
    // First, get current connection name
    $result = executeCommand('sudo nmcli device status | grep wlan0');
    if ($result['returnCode'] !== 0) {
        return ['success' => false, 'message' => 'Failed to get WiFi device status'];
    }
    
    $deviceInfo = preg_split('/\s+/', trim($result['output'][0]));
    $currentConnection = $deviceInfo[3] ?? 'hotspot';
    
    // Delete existing hotspot connection if it exists
    $result = executeCommand("sudo nmcli connection delete '{$currentConnection}' 2>/dev/null || true");
    
    // Create new hotspot connection
    $command = sprintf(
        "sudo nmcli connection add type wifi ifname wlan0 con-name hotspot autoconnect yes ssid %s",
        escapeshellarg($ssid)
    );
    
    $result = executeCommand($command);
    if ($result['returnCode'] !== 0) {
        return [
            'success' => false, 
            'message' => 'Failed to create hotspot connection: ' . $result['outputString']
        ];
    }
    
    // Configure as access point
    $result = executeCommand("sudo nmcli connection modify hotspot 802-11-wireless.mode ap");

    if ($result['returnCode'] !== 0) {
        return [
            'success' => false, 
            'message' => 'Failed to set AP mode: ' . $result['outputString']
        ];
    }
    
    // Set band to 2.4GHz for better compatibility
    $result = executeCommand("sudo nmcli connection modify hotspot 802-11-wireless.band bg");
    if ($result['returnCode'] !== 0) {
        return [
            'success' => false, 
            'message' => 'Failed to set band: ' . $result['outputString']
        ];
    }
    
    // Configure IP sharing
    $result = executeCommand("sudo nmcli connection modify hotspot ipv4.method shared");
    if ($result['returnCode'] !== 0) {
        return [
            'success' => false, 
            'message' => 'Failed to set IP sharing: ' . $result['outputString']
        ];
    }
    
    // Set WPA2 security
    $result = executeCommand("sudo nmcli connection modify hotspot 802-11-wireless-security.key-mgmt wpa-psk");
    if ($result['returnCode'] !== 0) {
        return [
            'success' => false, 
            'message' => 'Failed to set security type: ' . $result['outputString']
        ];
    }
    
    // Set password
    $result = executeCommand(sprintf(
        "sudo nmcli connection modify hotspot 802-11-wireless-security.psk %s",
        escapeshellarg($password)
    ));
    if ($result['returnCode'] !== 0) {
        return [
            'success' => false, 
            'message' => 'Failed to set password: ' . $result['outputString']
        ];
    }
    
    // Bring up the connection
    $result = executeCommand("sudo nmcli connection up hotspot");
    if ($result['returnCode'] !== 0) {
        return [
            'success' => false, 
            'message' => 'Failed to activate hotspot: ' . $result['outputString']
        ];
    }
    
    return [
        'success' => true, 
        'message' => "Hotspot '{$ssid}' configured and activated successfully"
    ];
}

function restartWiFi() {
    // Get current connection
    $result = executeCommand('sudo nmcli device status | grep wlan0');
    if ($result['returnCode'] !== 0) {
        return ['success' => false, 'message' => 'Failed to get WiFi device status'];
    }
    
    $deviceInfo = preg_split('/\s+/', trim($result['output'][0]));
    $currentConnection = $deviceInfo[3] ?? null;
    
    if ($currentConnection && $currentConnection !== '--') {
        // Restart the connection
        $result = executeCommand("sudo nmcli connection down '{$currentConnection}' && sudo nmcli connection up '{$currentConnection}'");

        if ($result['returnCode'] === 0) {
            return ['success' => true, 'message' => 'WiFi connection restarted successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to restart WiFi: ' . $result['outputString']];
        }
    } else {
        return ['success' => false, 'message' => 'No active WiFi connection found'];
    }
}

try {
    switch ($action) {
        case 'configure_hotspot':
            if (!isset($input['ssid']) || !isset($input['password'])) {
                echo json_encode(['success' => false, 'message' => 'SSID and password required']);
                exit;
            }
            
            $ssid = trim($input['ssid']);
            $password = trim($input['password']);
            
            // Validate inputs
            if (!validateSSID($ssid)) {
                echo json_encode(['success' => false, 'message' => 'Invalid SSID. Must be 1-32 printable characters.']);
                exit;
            }
            
            if (!validatePassword($password)) {
                echo json_encode(['success' => false, 'message' => 'Invalid password. Must be 8-63 characters.']);
                exit;
            }
            
            $result = updateHotspotConfig($ssid, $password);
            
            // Log the action
            error_log("WiFi hotspot configured by user: " . ($_SESSION['username'] ?? 'unknown') . " - SSID: {$ssid}");
            
            echo json_encode($result);
            break;
            
        case 'restart':
            $result = restartWiFi();
            
            // Log the action
            error_log("WiFi restarted by user: " . ($_SESSION['username'] ?? 'unknown'));
            
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

} catch (Exception $e) {
    error_log("WiFi configuration error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}
?>
