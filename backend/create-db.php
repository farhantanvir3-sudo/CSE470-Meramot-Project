<?php

/**
 * Meramot Automated Local Database Creator
 * Checks if the target PostgreSQL database exists; if not, creates it.
 */

$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    echo "[INFO] No .env file found. Please create one from .env.example first.\n";
    exit(1);
}

// Parse simple .env key-value pairs
$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$env = [];
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }
    if (str_contains($line, '=')) {
        [$key, $val] = explode('=', $line, 2);
        $val = trim($val);
        // Strip surrounding quotes if present
        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
            (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
            $val = substr($val, 1, -1);
        }
        $env[trim($key)] = $val;
    }
}

// If DATABASE_URL is provided, parse it
if (!empty($env['DATABASE_URL'])) {
    $parts = parse_url($env['DATABASE_URL']);
    if ($parts) {
        $host = $parts['host'] ?? '127.0.0.1';
        $port = $parts['port'] ?? 5432;
        $username = $parts['user'] ?? 'postgres';
        $password = $parts['pass'] ?? '';
        $database = ltrim($parts['path'] ?? 'meramot', '/');
    }
} else {
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['DB_PORT'] ?? '5432';
    $database = $env['DB_DATABASE'] ?? 'meramot';
    $username = $env['DB_USERNAME'] ?? 'meramot_user';
    $password = $env['DB_PASSWORD'] ?? 'meramot_pass';
}

echo "===============================================================\n";
echo "  Checking PostgreSQL connection ($host:$port)...\n";
echo "  Target Database: $database | User: $username\n";
echo "===============================================================\n";

// 1. First, try connecting directly to the target database
try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$database", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    echo "[OK] Connected to '$database' database successfully!\n";
    exit(0);
} catch (PDOException $e) {
    // If target database doesn't exist, try connecting to the default 'postgres' database to create it
    if (str_contains(strtolower($e->getMessage()), 'does not exist') || str_contains(strtolower($e->getMessage()), 'database')) {
        echo "[INFO] Database '$database' does not exist yet. Attempting creation...\n";
    } else {
        echo "[WARNING] Direct connection to '$database' failed: " . $e->getMessage() . "\n";
        echo "[INFO] Trying maintenance database 'postgres' to create '$database'...\n";
    }
}

// 2. Connect to maintenance db 'postgres' to execute CREATE DATABASE
$attempts = [
    ['user' => $username, 'pass' => $password],
    ['user' => 'postgres', 'pass' => 'postgres'],
    ['user' => 'postgres', 'pass' => 'root'],
    ['user' => 'postgres', 'pass' => 'admin'],
    ['user' => 'postgres', 'pass' => '123456'],
    ['user' => 'postgres', 'pass' => 'password'],
];

$connected = false;
$maintPdo = null;

foreach ($attempts as $cred) {
    try {
        $maintPdo = new PDO("pgsql:host=$host;port=$port;dbname=postgres", $cred['user'], $cred['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]);
        $connected = true;
        break;
    } catch (PDOException) {
        continue;
    }
}

if (!$connected || !$maintPdo) {
    echo "[ERROR] Could not connect to PostgreSQL server.\n";
    echo "Please ensure:\n";
    echo "  1. PostgreSQL service is running on $host:$port\n";
    echo "  2. Your credentials in backend/.env (DB_USERNAME and DB_PASSWORD) match your local PostgreSQL install.\n";
    echo "  3. 'extension=pdo_pgsql' is enabled in your php.ini.\n";
    exit(1);
}

try {
    // Check if database exists
    $stmt = $maintPdo->prepare("SELECT 1 FROM pg_database WHERE datname = :dbname");
    $stmt->execute(['dbname' => $database]);
    if ($stmt->fetch()) {
        echo "[OK] Database '$database' already exists!\n";
    } else {
        $maintPdo->exec("CREATE DATABASE \"$database\"");
        echo "[SUCCESS] Created database '$database' successfully!\n";
    }
    exit(0);
} catch (PDOException $e) {
    echo "[ERROR] Failed to create database: " . $e->getMessage() . "\n";
    exit(1);
}
