<?php
declare(strict_types=1);

// database/db_connection.php
$requiredVars = [
    'MYSQLHOST',
    'MYSQLPORT',
    'MYSQLDATABASE',
    'MYSQLUSER',
    'MYSQLPASSWORD',
];

$config = [];
foreach ($requiredVars as $varName) {
    $value = getenv($varName);
    if ($value === false || trim((string) $value) === '') {
        throw new RuntimeException('Environment variables not loaded');
    }
    $config[$varName] = trim((string) $value);
}

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $config['MYSQLHOST'],
        $config['MYSQLPORT'],
        $config['MYSQLDATABASE']
    );

    $pdo = new PDO(
        $dsn,
        $config['MYSQLUSER'],
        $config['MYSQLPASSWORD'],
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
