<?php
declare(strict_types=1);

// Load bootstrapping
$container = require_once __DIR__ . '/../bootstrap/init.php';

$config = $container->get('config')['database'];

// Connect to MySQL server first without selecting database to ensure it exists
$dsn = "mysql:host={$config['host']};port={$config['port']};charset={$config['charset']}";
try {
    $pdo = new \PDO($dsn, $config['username'], $config['password'], [
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
    ]);
    $dbNameEscaped = str_replace('`', '``', $config['database']);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbNameEscaped}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database `{$config['database']}` created or already exists.\n";
} catch (\PDOException $e) {
    fwrite(STDERR, "Error creating database: " . $e->getMessage() . "\n");
    exit(1);
}

// Run migrations
try {
    $migration = new \App\Database\Migration($container->get(\App\Database\Connection::class));
    $migration->run(__DIR__ . '/schema.sql');
    echo "Migration completed successfully!\n";
    
    // Run dynamic alterations for existing tables
    $connection = $container->get(\App\Database\Connection::class);
    $pdo = $connection->getPdo();
    
    // Check and add/modify all required columns
    $columns = [
        'username' => "ALTER TABLE users ADD COLUMN username VARCHAR(64) NULL AFTER ip_hash",
        'email' => "ALTER TABLE users ADD COLUMN email VARCHAR(255) UNIQUE NULL AFTER username",
        'password_hash' => "ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL AFTER email",
        'user_type' => "ALTER TABLE users ADD COLUMN user_type ENUM('guest','registered') DEFAULT 'guest' AFTER password_hash",
        'verified' => "ALTER TABLE users ADD COLUMN verified TINYINT(1) DEFAULT 0 AFTER user_type",
        'avatar_path' => "ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL AFTER verified",
        'bio' => "ALTER TABLE users ADD COLUMN bio VARCHAR(160) NULL AFTER avatar_path",
        'display_name' => "ALTER TABLE users ADD COLUMN display_name VARCHAR(20) NOT NULL DEFAULT '' AFTER bio",
        'tags' => "ALTER TABLE users ADD COLUMN tags JSON NULL AFTER interests",
        'country_flag' => "ALTER TABLE users ADD COLUMN country_flag VARCHAR(8) NULL AFTER gender",
        'remember_token' => "ALTER TABLE users ADD COLUMN remember_token VARCHAR(64) NULL AFTER is_banned",
        'token_expires' => "ALTER TABLE users ADD COLUMN token_expires DATETIME NULL AFTER remember_token"
    ];

    foreach ($columns as $col => $sql) {
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE '{$col}'");
        if ($stmt->fetch() === false) {
            $pdo->exec($sql);
            echo "Added column '{$col}' to users table.\n";
        }
    }

    // Check and add attachment columns to messages table
    $msgColumns = [
        'attachment_path' => "ALTER TABLE messages ADD COLUMN attachment_path VARCHAR(255) NULL AFTER content",
        'attachment_type' => "ALTER TABLE messages ADD COLUMN attachment_type VARCHAR(64) NULL AFTER attachment_path"
    ];
    foreach ($msgColumns as $col => $sql) {
        $stmt = $pdo->query("SHOW COLUMNS FROM messages LIKE '{$col}'");
        if ($stmt->fetch() === false) {
            $pdo->exec($sql);
            echo "Added column '{$col}' to messages table.\n";
        }
    }

    // Modify existing age and gender columns if they are of old types
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'age'");
    $ageCol = $stmt->fetch();
    if ($ageCol && !str_contains(strtolower($ageCol['Type']), 'tinyint')) {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN age TINYINT UNSIGNED NOT NULL DEFAULT 18");
        echo "Modified 'age' column to TINYINT UNSIGNED.\n";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'gender'");
    $genderCol = $stmt->fetch();
    if ($genderCol && !str_contains(strtolower($genderCol['Type']), 'enum')) {
        $pdo->exec("UPDATE users SET gender = 'O' WHERE gender NOT IN ('F', 'M') OR gender IS NULL");
        $pdo->exec("ALTER TABLE users MODIFY COLUMN gender ENUM('F','M','O') NOT NULL DEFAULT 'O'");
        echo "Modified 'gender' column to ENUM('F','M','O').\n";
    }
} catch (\Exception $e) {
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
