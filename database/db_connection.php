<?php
declare(strict_types=1);

// database/db_connection.php
$railwayHost = trim((string) (getenv('MYSQLHOST') ?: ''));
$railwayPort = trim((string) (getenv('MYSQLPORT') ?: ''));
$railwayDb = trim((string) (getenv('MYSQLDATABASE') ?: ''));
$railwayUser = trim((string) (getenv('MYSQLUSER') ?: ''));
$railwayPass = getenv('MYSQLPASSWORD');
$railwayPass = $railwayPass === false ? '' : (string) $railwayPass;

$hasRailwayConfig = $railwayHost !== ''
    && $railwayPort !== ''
    && $railwayDb !== ''
    && $railwayUser !== ''
    && $railwayPass !== '';

$config = [];
if ($hasRailwayConfig) {
    $config = [
        'host' => $railwayHost,
        'port' => $railwayPort,
        'db' => $railwayDb,
        'user' => $railwayUser,
        'pass' => $railwayPass,
    ];
} else {
    // Local fallback for localhost:8000 development.
    $config = [
        'host' => 'localhost',
        'port' => '3306',
        'db' => 'lims',
        'user' => 'root',
        'pass' => '',
    ];
}

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['host'],
        $config['port'],
        $config['db']
    );

    $pdo = new PDO(
        $dsn,
        $config['user'],
        $config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    return $pdo;
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    throw new RuntimeException('Database connection failed. Please check your database configuration.');
}
