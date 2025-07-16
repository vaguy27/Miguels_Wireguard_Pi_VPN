<?php
// manage_users.php - Script to create/update/delete users in local hash file

$users_file = __DIR__ . '/users.json';

function loadUsers($file) {
    if (!file_exists($file)) {
        return [];
    }
    
    $content = file_get_contents($file);
    $users = json_decode($content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }
    
    return $users;
}

function saveUsers($file, $users) {
    $json = json_encode($users, JSON_PRETTY_PRINT);
    if (file_put_contents($file, $json) === false) {
        return false;
    }
    
    // Set restrictive permissions on the users file
    chmod($file, 0600);
    return true;
}

function createUser($file, $username, $password) {
    // Validate username
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username) || strlen($username) > 50) {
        return "Invalid username format (alphanumeric and underscore only, max 50 chars)";
    }
    
    // Validate password strength
    if (strlen($password) < 8) {
        return "Password must be at least 8 characters long";
    }
    
    $users = loadUsers($file);
    
    // Check if user already exists
    if (isset($users[$username])) {
        return "Username '$username' already exists";
    }
    
    // Create new user
    $users[$username] = [
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'is_active' => true,
        'created_at' => date('Y-m-d H:i:s'),
        'last_login' => null
    ];
    
    if (saveUsers($file, $users)) {
        return "User '$username' created successfully";
    } else {
        return "Error: Could not save users file";
    }
}

function updateUserPassword($file, $username, $new_password) {
    if (strlen($new_password) < 8) {
        return "Password must be at least 8 characters long";
    }
    
    $users = loadUsers($file);
    
    if (!isset($users[$username])) {
        return "User '$username' not found";
    }
    
    $users[$username]['password_hash'] = password_hash($new_password, PASSWORD_DEFAULT);
    $users[$username]['updated_at'] = date('Y-m-d H:i:s');
    
    if (saveUsers($file, $users)) {
        return "Password updated for user '$username'";
    } else {
        return "Error: Could not save users file";
    }
}

function toggleUserStatus($file, $username, $active = true) {
    $users = loadUsers($file);
    
    if (!isset($users[$username])) {
        return "User '$username' not found";
    }
    
    $users[$username]['is_active'] = $active;
    $users[$username]['updated_at'] = date('Y-m-d H:i:s');
    
    $status = $active ? 'activated' : 'deactivated';
    
    if (saveUsers($file, $users)) {
        return "User '$username' $status successfully";
    } else {
        return "Error: Could not save users file";
    }
}

function deleteUser($file, $username) {
    $users = loadUsers($file);
    
    if (!isset($users[$username])) {
        return "User '$username' not found";
    }
    
    unset($users[$username]);
    
    if (saveUsers($file, $users)) {
        return "User '$username' deleted successfully";
    } else {
        return "Error: Could not save users file";
    }
}

function listUsers($file) {
    $users = loadUsers($file);
    
    if (empty($users)) {
        return "No users found";
    }
    
    $output = "Users:\n";
    $output .= str_pad("Username", 20) . str_pad("Status", 10) . str_pad("Created", 20) . "Last Login\n";
    $output .= str_repeat("-", 70) . "\n";
    
    foreach ($users as $username => $user) {
        $status = $user['is_active'] ? 'Active' : 'Inactive';
        $created = $user['created_at'] ?? 'Unknown';
        $last_login = $user['last_login'] ?? 'Never';
        
        $output .= str_pad($username, 20) . str_pad($status, 10) . str_pad($created, 20) . $last_login . "\n";
    }
    
    return $output;
}

// Command line interface
if (php_sapi_name() === 'cli') {
    if ($argc < 2) {
        echo "Usage:\n";
        echo "  php manage_users.php create <username> <password>\n";
        echo "  php manage_users.php update <username> <new_password>\n";
        echo "  php manage_users.php activate <username>\n";
        echo "  php manage_users.php deactivate <username>\n";
        echo "  php manage_users.php delete <username>\n";
        echo "  php manage_users.php list\n";
        exit(1);
    }
    
    $action = $argv[1];
    
    switch ($action) {
        case 'create':
            if ($argc !== 4) {
                echo "Usage: php manage_users.php create <username> <password>\n";
                exit(1);
            }
            echo createUser($users_file, $argv[2], $argv[3]) . "\n";
            break;
            
        case 'update':
            if ($argc !== 4) {
                echo "Usage: php manage_users.php update <username> <new_password>\n";
                exit(1);
            }
            echo updateUserPassword($users_file, $argv[2], $argv[3]) . "\n";
            break;
            
        case 'activate':
            if ($argc !== 3) {
                echo "Usage: php manage_users.php activate <username>\n";
                exit(1);
            }
            echo toggleUserStatus($users_file, $argv[2], true) . "\n";
            break;
            
        case 'deactivate':
            if ($argc !== 3) {
                echo "Usage: php manage_users.php deactivate <username>\n";
                exit(1);
            }
            echo toggleUserStatus($users_file, $argv[2], false) . "\n";
            break;
            
        case 'delete':
            if ($argc !== 3) {
                echo "Usage: php manage_users.php delete <username>\n";
                exit(1);
            }
            echo deleteUser($users_file, $argv[2]) . "\n";
            break;
            
        case 'list':
            echo listUsers($users_file) . "\n";
            break;
            
        default:
            echo "Unknown action: $action\n";
            exit(1);
    }
} else {
    // Web interface (remove this in production)
    echo "This script should be run from command line only\n";
}
?>
