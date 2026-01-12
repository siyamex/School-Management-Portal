<?php
/**
 * Database Connection Handler
 * Using PDO for secure database operations
 */

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
    // Log error and show user-friendly message
    error_log("Database Connection Error: " . $e->getMessage());
    
    if (APP_ENV === 'development') {
        die("Database connection failed: " . $e->getMessage());
    } else {
        die("Database connection failed. Please contact the administrator.");
    }
}

/**
 * Execute a prepared statement with parameters
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind
 * @return PDOStatement
 */
function query($sql, $params = []) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query Error: " . $e->getMessage() . " | SQL: " . $sql);
        throw $e;
    }
}

/**
 * Get a single row
 * 
 * @param string $sql SQL query
 * @param array $params Parameters to bind
 * @return array|false
 */
function getRow($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt->fetch();
}

/**
 * Get all rows
 * 
 * @param string $sql SQL query
 * @param array $params Parameters to bind
 * @return array
 */
function getAll($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Get single value
 * 
 * @param string $sql SQL query
 * @param array $params Parameters to bind
 * @return mixed
 */
function getValue($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt->fetchColumn();
}

/**
 * Insert and return last insert ID
 * 
 * @param string $sql SQL query
 * @param array $params Parameters to bind
 * @return string Last insert ID
 */
function insert($sql, $params = []) {
    global $pdo;
    query($sql, $params);
    return $pdo->lastInsertId();
}

/**
 * Begin transaction
 */
function beginTransaction() {
    global $pdo;
    $pdo->beginTransaction();
}

/**
 * Commit transaction
 */
function commit() {
    global $pdo;
    $pdo->commit();
}

/**
 * Rollback transaction
 */
function rollback() {
    global $pdo;
    $pdo->rollBack();
}
