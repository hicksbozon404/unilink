<?php
// fix_database.php
session_start();
$pdo = new PDO("mysql:host=localhost;dbname=unilink_db;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// Add missing columns to users table
try {
    $pdo->exec("
        ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS full_names VARCHAR(255) NOT NULL DEFAULT 'Student',
        ADD COLUMN IF NOT EXISTS email VARCHAR(255) UNIQUE NOT NULL DEFAULT 'student@unilink.edu',
        ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS student_id VARCHAR(50) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS faculty VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS program VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS duration VARCHAR(50) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS current_year VARCHAR(50) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS whatsapp VARCHAR(20) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS portal_username VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS portal_password VARCHAR(255) DEFAULT NULL
    ");
    
    echo "Database structure updated successfully!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>