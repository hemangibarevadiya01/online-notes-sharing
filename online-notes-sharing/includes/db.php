<?php
/**
 * Online Notes Sharing Platform - Database Connection
 * Uses PDO with Prepared Statements and Exception Handling.
 */

// Database configuration settings
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'online_notes_db');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Log error securely and terminate gracefully
    error_log("Database Connection Error: " . $e->getMessage());
    die("<div style='font-family:sans-serif;padding:30px;text-align:center;'>
            <h2 style='color:#dc2626;'>Database Connection Error</h2>
            <p>Unable to connect to the database server. Please ensure MySQL is running and <code>online_notes_db</code> is created.</p>
            <p><small style='color:#6b7280;'>" . htmlspecialchars($e->getMessage()) . "</small></p>
         </div>");
}
