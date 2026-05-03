<?php
// database/db_connection.php

try {
    $dbHost = getenv('DB_HOST') ?: 'localhost';
    $dbPort = getenv('DB_PORT') ?: '3306';
    $dbName = getenv('DB_NAME') ?: 'lims';
    $dbUser = getenv('DB_USER') ?: 'root';
    $dbPass = getenv('DB_PASS');
    if ($dbPass === false) {
        $dbPass = '';
    }

    $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ];

    $isLocalDefaults = $dbHost === 'localhost' && $dbUser === 'root' && $dbName === 'lims';
    if ($isLocalDefaults) {
        // Local dev convenience: ensure the database exists.
        $bootstrapPdo = new PDO(
            "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4",
            $dbUser,
            $dbPass,
            $pdoOptions
        );
        $bootstrapPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}`");
    }

    // Main app connection (works for Railway and local).
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        $pdoOptions
    );

    // Create admin table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin (
        admin_id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        FirstName VARCHAR(50) NOT NULL,
        LastName VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL,
        Status ENUM('active', 'deactive') NOT NULL DEFAULT 'active',
        last_login DATETIME NULL,
        login_location VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Check if default admin accounts exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM admin");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        // Insert default admin accounts with hashed passwords
        $defaultPassword = password_hash('123', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO admin (username, password, FirstName, LastName, email, Status) VALUES 
            ('allain', '$defaultPassword', 'Allain', 'User', 'allain@example.com', 'active'),
            ('ayham', '$defaultPassword', 'Ayham', 'User', 'ayham@example.com', 'active')
        ");
    }
    
    return $pdo;
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    throw new Exception('Database connection failed. Please check your database configuration.');
}
