<?php
/**
 * Database Setup Script for Tajnur Authentication System
 * Run this script once to create the database and users table
 */

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'tajnur_auth';

try {
    // Connect to MySQL server (without selecting database)
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to MySQL server successfully.\n";
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '$database' created or already exists.\n";
    
    // Select the database
    $pdo->exec("USE `$database`");
    
    // Create users table
    $createUsersTable = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(100),
            role ENUM('admin', 'staff', 'manager') DEFAULT 'staff',
            is_admin BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            is_active BOOLEAN DEFAULT TRUE,
            failed_login_attempts INT DEFAULT 0,
            locked_until TIMESTAMP NULL
        )
    ";
    
    $pdo->exec($createUsersTable);
    echo "Users table created successfully.\n";
    
    // Create login_attempts table for rate limiting
    $createAttemptsTable = "
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            success BOOLEAN DEFAULT FALSE,
            INDEX idx_username_time (username, attempt_time),
            INDEX idx_ip_time (ip_address, attempt_time)
        )
    ";
    
    $pdo->exec($createAttemptsTable);
    echo "Login attempts table created successfully.\n";
    
    // Insert default users (migrate from hardcoded credentials)
    $defaultUsers = [
        ['admin', 'tajnur2024', 'admin@tajnur.com', 'admin', true],
        ['staff', 'office123', 'staff@tajnur.com', 'staff', false],
        ['manager', 'docs2024', 'manager@tajnur.com', 'manager', false]
    ];
    
    $insertUser = $pdo->prepare("
        INSERT IGNORE INTO users (username, password_hash, email, role, is_admin) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($defaultUsers as $user) {
        $hashedPassword = password_hash($user[1], PASSWORD_DEFAULT);
        $insertUser->execute([$user[0], $hashedPassword, $user[2], $user[3], $user[4]]);
        $adminStatus = $user[4] ? ' (Admin)' : '';
        echo "User '{$user[0]}' created with role '{$user[3]}'$adminStatus.\n";
    }
    
    // Create sessions table for better session management
    $createSessionsTable = "
        CREATE TABLE IF NOT EXISTS user_sessions (
            id VARCHAR(128) PRIMARY KEY,
            user_id INT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_expires (expires_at)
        )
    ";
    
    $pdo->exec($createSessionsTable);
    echo "User sessions table created successfully.\n";
    
    echo "\n=== Database Setup Complete ===\n";
    echo "Database: $database\n";
    echo "Tables created: users, login_attempts, user_sessions\n";
    echo "Default users created: admin, staff, manager\n";
    echo "\nNext steps:\n";
    echo "1. Update auth.php to use database instead of hardcoded users\n";
    echo "2. Configure your web server to serve PHP files\n";
    echo "3. Test the authentication endpoints\n";
    
} catch (PDOException $e) {
    echo "Database setup failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Display connection info for reference
echo "\n=== Connection Information ===\n";
echo "Host: $host\n";
echo "Database: $database\n";
echo "Username: $username\n";
echo "\n=== API Endpoints ===\n";
echo "Login: POST /api/auth.php/login\n";
echo "Logout: POST /api/auth.php/logout\n";
echo "Verify: GET /api/auth.php/verify\n";
echo "CSRF Token: GET /api/auth.php/csrf\n";
?>