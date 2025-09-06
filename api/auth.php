<?php
// Enable CORS for local development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Content Security Policy
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com;");

// HTTPS Strict Transport Security (only if HTTPS is enabled)
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configure secure session settings
ini_set('session.cookie_httponly', '1');      // Prevent JavaScript access to session cookies
ini_set('session.use_strict_mode', '1');      // Prevent session fixation attacks
ini_set('session.cookie_samesite', 'Strict'); // CSRF protection

// Set secure session cookie parameters
session_set_cookie_params([
    'lifetime' => 3600,        // 1 hour session lifetime
    'path' => '/',
    'domain' => '',            // Use current domain
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // HTTPS only when available
    'httponly' => true,        // Prevent JavaScript access
    'samesite' => 'Strict'     // CSRF protection
]);

// Start session with secure settings
session_start();

// Regenerate session ID on login to prevent session fixation
if (!isset($_SESSION['session_regenerated'])) {
    session_regenerate_id(true);
    $_SESSION['session_regenerated'] = true;
}

// Database configuration
$db_config = [
    'host' => 'localhost',
    'dbname' => 'tajnur_auth',
    'username' => 'root',
    'password' => ''
];

// Database connection
function getDBConnection() {
    global $db_config;
    try {
        $pdo = new PDO(
            "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4",
            $db_config['username'],
            $db_config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}

// Get user from database
function getUserByUsername($username) {
    $pdo = getDBConnection();
    if (!$pdo) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Update user login info
function updateUserLogin($userId) {
    $pdo = getDBConnection();
    if (!$pdo) return;
    
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW(), failed_login_attempts = 0 WHERE id = ?");
    $stmt->execute([$userId]);
}

// Record login attempt
function recordLoginAttempt($username, $success = false) {
    $pdo = getDBConnection();
    if (!$pdo) return;
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $pdo->prepare("INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, ?)");
    $stmt->execute([$username, $ipAddress, $success]);
}

// Rate limiting configuration
$max_attempts = 5;
$lockout_time = 900; // 15 minutes

function checkRateLimit($username) {
    global $max_attempts, $lockout_time;
    
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    $attempts = $_SESSION['login_attempts'];
    $current_time = time();
    
    // Clean old attempts
    if (isset($attempts[$username])) {
        $attempts[$username] = array_filter($attempts[$username], function($timestamp) use ($current_time, $lockout_time) {
            return ($current_time - $timestamp) < $lockout_time;
        });
    }
    
    // Check if user is locked out
    if (isset($attempts[$username]) && count($attempts[$username]) >= $max_attempts) {
        return false;
    }
    
    return true;
}

function recordFailedAttempt($username) {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    if (!isset($_SESSION['login_attempts'][$username])) {
        $_SESSION['login_attempts'][$username] = [];
    }
    
    $_SESSION['login_attempts'][$username][] = time();
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Handle different endpoints
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path_parts = explode('/', trim($path, '/'));
$endpoint = end($path_parts);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        if ($endpoint === 'login' || strpos($path, 'login') !== false) {
            // Login endpoint
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['username']) || !isset($input['password'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Username and password required']);
                exit;
            }
            
            // Input validation and sanitization
            $username = trim($input['username']);
            $password = $input['password'];
            
            // Validate username format
            if (empty($username) || strlen($username) < 3 || strlen($username) > 50) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Username must be between 3 and 50 characters']);
                exit;
            }
            
            // Sanitize username (allow only alphanumeric and underscore)
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Username contains invalid characters']);
                exit;
            }
            
            // Validate password
            if (empty($password) || strlen($password) < 6 || strlen($password) > 255) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Password must be between 6 and 255 characters']);
                exit;
            }
            
            // Convert username to lowercase for consistency
            $username = strtolower($username);
            
            // Validate CSRF token if provided
            if (isset($input['csrf_token']) && !validateCSRFToken($input['csrf_token'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
                exit;
            }
            
            // Check rate limiting
            if (!checkRateLimit($username)) {
                http_response_code(429);
                echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Please try again later.']);
                exit;
            }
            
            // Get user from database
            $user = getUserByUsername($username);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Successful login
                $_SESSION['authenticated'] = true;
                $_SESSION['username'] = $username;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['is_admin'] = (bool)$user['is_admin'];
                $_SESSION['login_time'] = time();
                
                // Update database
                updateUserLogin($user['id']);
                recordLoginAttempt($username, true);
                
                // Clear failed attempts
                if (isset($_SESSION['login_attempts'][$username])) {
                    unset($_SESSION['login_attempts'][$username]);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'user' => $username,
                    'role' => $user['role'],
                    'is_admin' => (bool)$user['is_admin'],
                    'csrf_token' => generateCSRFToken()
                ]);
            } else {
                // Failed login
                recordFailedAttempt($username);
                recordLoginAttempt($username, false);
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            }
        } elseif ($endpoint === 'logout' || strpos($path, 'logout') !== false) {
            // Logout endpoint
            session_destroy();
            echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
        }
        break;
        
    case 'GET':
        if ($endpoint === 'verify' || strpos($path, 'verify') !== false) {
            // Verify authentication status
            $authenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
            
            if ($authenticated) {
                echo json_encode([
                    'success' => true,
                    'authenticated' => true,
                    'user' => $_SESSION['username'] ?? null,
                    'csrf_token' => generateCSRFToken()
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'authenticated' => false,
                    'csrf_token' => generateCSRFToken()
                ]);
            }
        } elseif ($endpoint === 'csrf' || strpos($path, 'csrf') !== false) {
            // Get CSRF token
            echo json_encode([
                'success' => true,
                'csrf_token' => generateCSRFToken()
            ]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}
?>