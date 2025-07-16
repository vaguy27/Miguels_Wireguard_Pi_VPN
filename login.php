<?php
session_start();
header('Content-Type: application/json');

// Configuration
$users_file = __DIR__ . '/users.json';
$rate_limit_file = '/tmp/login_attempts_' . $_SERVER['REMOTE_ADDR'];
$max_attempts = 5;
$time_window = 900; // 15 minutes

function checkRateLimit($file, $max_attempts, $time_window) {
    if (!file_exists($file)) {
        return true;
    }
    
    $attempts = json_decode(file_get_contents($file), true);
    $current_time = time();
    
    // Clean old attempts
    $attempts = array_filter($attempts, function($timestamp) use ($current_time, $time_window) {
        return ($current_time - $timestamp) < $time_window;
    });
    
    return count($attempts) < $max_attempts;
}

function recordAttempt($file) {
    $attempts = [];
    if (file_exists($file)) {
        $attempts = json_decode(file_get_contents($file), true);
    }
    $attempts[] = time();
    file_put_contents($file, json_encode($attempts));
}

function loadUsers($file) {
    if (!file_exists($file)) {
        return [];
    }
    
    $content = file_get_contents($file);
    $users = json_decode($content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Error reading users file: " . json_last_error_msg());
        return [];
    }
    
    return $users;
}

function updateUserLastLogin($file, $username) {
    $users = loadUsers($file);
    
    if (isset($users[$username])) {
        $users[$username]['last_login'] = date('Y-m-d H:i:s');
        
        if (file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT)) === false) {
            error_log("Failed to update last login for user: $username");
        }
    }
}

// Check rate limit
if (!checkRateLimit($rate_limit_file, $max_attempts, $time_window)) {
    echo json_encode(['success' => false, 'message' => 'Too many login attempts. Please try again later.']);
    exit;
}

// Validate input
if (!isset($_POST['username']) || !isset($_POST['password'])) {
    echo json_encode(['success' => false, 'message' => 'Username and password required']);
    exit;
}

$input_username = trim($_POST['username']);
$input_password = $_POST['password'];

// Input validation
if (empty($input_username) || empty($input_password)) {
    recordAttempt($rate_limit_file);
    echo json_encode(['success' => false, 'message' => 'Username and password cannot be empty']);
    exit;
}

// Username length and character validation
if (strlen($input_username) > 50 || !preg_match('/^[a-zA-Z0-9_]+$/', $input_username)) {
    recordAttempt($rate_limit_file);
    echo json_encode(['success' => false, 'message' => 'Invalid username format']);
    exit;
}

// Load users from file
$users = loadUsers($users_file);

if (empty($users)) {
    recordAttempt($rate_limit_file);
    echo json_encode(['success' => false, 'message' => 'Authentication system not configured']);
    exit;
}

// Check if user exists and is active
if (!isset($users[$input_username]) || !$users[$input_username]['is_active']) {
    recordAttempt($rate_limit_file);
    error_log("Failed login attempt for username: " . $input_username . " from IP: " . $_SERVER['REMOTE_ADDR']);
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    exit;
}

$user = $users[$input_username];

// Verify password
if (password_verify($input_password, $user['password_hash'])) {
    // Successful login
    session_regenerate_id(true); // Prevent session fixation attacks
    $_SESSION['authenticated'] = true;
    $_SESSION['username'] = $input_username;
    $_SESSION['login_time'] = time();
    
    // Update last login timestamp
    updateUserLastLogin($users_file, $input_username);
    
    // Clean up rate limit file on successful login
    if (file_exists($rate_limit_file)) {
        unlink($rate_limit_file);
    }
    
    echo json_encode(['success' => true]);
} else {
    // Failed login
    recordAttempt($rate_limit_file);
    
    // Log failed attempt
    error_log("Failed login attempt for username: " . $input_username . " from IP: " . $_SERVER['REMOTE_ADDR']);
    
    // Generic error message to prevent username enumeration
    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
}
?>
