<?php
// ============================================================
// Database Configuration - PDO Singleton
// ============================================================

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        // Prefer environment-driven DB credentials.
        $host   = getenv('DB_HOST') ?: '127.0.0.1';
        $port   = (int)(getenv('DB_PORT') ?: 3306);
        $dbName = getenv('DB_NAME') ?: DB_NAME;
        $user   = getenv('DB_USER') ?: 'root';
        $pass   = getenv('DB_PASS') ?: '';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true,               // Reuse connections
            PDO::MYSQL_ATTR_FOUND_ROWS => true,         // Accurate affected row counts
        ];
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        }
        catch (PDOException $e) {
            // Do not expose DB details in production
            error_log('DB Connection failed: ' . $e->getMessage());
            die('<div style="font-family:sans-serif;padding:2rem;color:#c00">
                    <h2>Database Error</h2>
                    <p>Could not connect to the database. Please contact the system administrator.</p>
                 </div>');
        }
    }
    return $pdo;
}
