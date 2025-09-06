<?php
require_once 'auth.php';

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\';');

// HTTPS enforcement
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// CORS headers for local development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session with secure settings
session_start();

// Check if user is authenticated and is admin
function requireAdminAuth() {
    if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit();
    }
    
    // Check if user is admin (you can modify this logic based on your needs)
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit();
    }
}

// Validate and sanitize input
function validateUserInput($username, $password) {
    $errors = [];
    
    // Username validation
    if (empty($username)) {
        $errors[] = 'Username is required';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = 'Username must be between 3 and 50 characters';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Username can only contain letters, numbers, and underscores';
    }
    
    // Password validation
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6 || strlen($password) > 255) {
        $errors[] = 'Password must be between 6 and 255 characters';
    }
    
    return $errors;
}

// Get all users
function getAllUsers() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, created_at FROM users ORDER BY username");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error in getAllUsers: " . $e->getMessage());
        return false;
    }
}

// Add new user
function addUser($username, $password, $isAdmin = false) {
    global $pdo;
    
    try {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([strtolower($username)]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Username already exists'];
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $pdo->prepare("INSERT INTO users (username, password, is_admin, created_at) VALUES (?, ?, ?, NOW())");
        $result = $stmt->execute([strtolower($username), $hashedPassword, $isAdmin ? 1 : 0]);
        
        if ($result) {
            return ['success' => true, 'message' => 'User created successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to create user'];
        }
    } catch (PDOException $e) {
        error_log("Database error in addUser: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

// Delete user
function deleteUser($userId) {
    global $pdo;
    
    try {
        // Prevent deletion of current user
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
            return ['success' => false, 'message' => 'Cannot delete your own account'];
        }
        
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $result = $stmt->execute([$userId]);
        
        if ($result && $stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'User deleted successfully'];
        } else {
            return ['success' => false, 'message' => 'User not found or already deleted'];
        }
    } catch (PDOException $e) {
        error_log("Database error in deleteUser: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

// Handle different endpoints
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];

switch ($method) {
    case 'GET':
        if (strpos($path, '/users') !== false) {
            requireAdminAuth();
            
            $users = getAllUsers();
            if ($users !== false) {
                echo json_encode(['success' => true, 'users' => $users]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to fetch users']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
        }
        break;
        
    case 'POST':
        if (strpos($path, '/users') !== false) {
            requireAdminAuth();
            
            // Validate CSRF token
            $headers = getallheaders();
            $csrfToken = $headers['X-CSRF-Token'] ?? '';
            
            if (!validateCSRFToken($csrfToken)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
                exit();
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
                exit();
            }
            
            $username = trim($input['username'] ?? '');
            $password = $input['password'] ?? '';
            $isAdmin = isset($input['is_admin']) ? (bool)$input['is_admin'] : false;
            
            // Validate input
            $errors = validateUserInput($username, $password);
            
            if (!empty($errors)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
                exit();
            }
            
            $result = addUser($username, $password, $isAdmin);
            
            if ($result['success']) {
                http_response_code(201);
            } else {
                http_response_code(400);
            }
            
            echo json_encode($result);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
        }
        break;
        
    case 'DELETE':
        if (preg_match('/\/users\/(\d+)/', $path, $matches)) {
            requireAdminAuth();
            
            // Validate CSRF token
            $headers = getallheaders();
            $csrfToken = $headers['X-CSRF-Token'] ?? '';
            
            if (!validateCSRFToken($csrfToken)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
                exit();
            }
            
            $userId = (int)$matches[1];
            $result = deleteUser($userId);
            
            if ($result['success']) {
                http_response_code(200);
            } else {
                http_response_code(400);
            }
            
            echo json_encode($result);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Endpoint not found']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}
?>