<?php
/**
 * ==============================================================================
 * DATABASE CONFIGURATION - FORMS HUB
 * Al Manar Pipes Factory | Hostinger Production Database
 * ==============================================================================
 */

// Hostinger Database Credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'u976858450_forms_hub');
define('DB_USER', 'u976858450_forms_admin');
define('DB_PASS', 'FormsHub2026SecurePassX9');
define('DB_PORT', 3306);
define('DB_REMOTE_HOST', 'srv1650.hstgr.io');

/**
 * Get established PDO database connection with fallback hosts
 *
 * @return PDO
 * @throws PDOException
 */
function getDbConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $candidateHosts = [DB_HOST, DB_REMOTE_HOST, '127.0.0.1'];
    $lastException = null;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    foreach (array_unique($candidateHosts) as $host) {
        try {
            $dsn = "mysql:host={$host};port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            return $pdo;
        } catch (PDOException $e) {
            $lastException = $e;
        }
    }

    throw $lastException ?: new PDOException("Unable to establish database connection.");
}

/**
 * Ensure unified form_records table exists
 *
 * @return void
 */
function ensureFormRecordsTableExists() {
    $pdo = getDbConnection();
    $sql = "CREATE TABLE IF NOT EXISTS form_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        form_type VARCHAR(64) NOT NULL,
        form_ref VARCHAR(64) NULL,
        entry_date DATE NOT NULL,
        machine_line VARCHAR(64) NULL,
        form_data JSON NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_lookup (form_type, entry_date),
        INDEX idx_line (machine_line)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sql);
}

/**
 * Ensure iqama_installments table exists
 *
 * @return void
 */
function ensureIqamaInstallmentsTableExists() {
    $pdo = getDbConnection();
    $sql = "CREATE TABLE IF NOT EXISTS iqama_installments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cycle_code VARCHAR(64) NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        transfer_date DATE NOT NULL,
        file_path VARCHAR(255) NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cycle (cycle_code),
        INDEX idx_transfer_date (transfer_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($sql);
}
