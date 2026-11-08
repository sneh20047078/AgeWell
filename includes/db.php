<?php
/**
 * Database Connection and Functions
 */

// Database configuration
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'agewell');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/**
 * Execute a SQL query and return all results
 */
function fetchAll($sql, $params = [], $types = '') {
    global $conn;
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        // Generate types string if not provided
        if (empty($types)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 'b';
                }
            }
        }
        
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}

/**
 * Execute a SQL query and return single row
 */
function fetchOne($sql, $params = [], $types = '') {
    global $conn;
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        // Generate types string if not provided
        if (empty($types)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 'b';
                }
            }
        }
        
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Execute a SQL query with parameters
 */
function executeQuery($sql, $params = [], $types = '') {
    global $conn;
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        // Generate types string if not provided
        if (empty($types)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 'b';
                }
            }
        }
        
        $stmt->bind_param($types, ...$params);
    }
    
    return $stmt->execute();
}

/**
 * Delete record from table
 */
function delete($table, $where, $params = [], $types = '') {
    global $conn;
    
    $sql = "DELETE FROM $table WHERE $where";
    return executeQuery($sql, $params, $types);
}

/**
 * Insert record into table
 */
function insert($table, $data) {
    global $conn;
    
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    
    $params = array_values($data);
    
    // Execute query
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return false;
    }
    
    // Generate types string
    $types = '';
    foreach ($params as $param) {
        if (is_int($param)) {
            $types .= 'i';
        } elseif (is_float($param)) {
            $types .= 'd';
        } elseif (is_string($param)) {
            $types .= 's';
        } else {
            $types .= 'b';
        }
    }
    
    $stmt->bind_param($types, ...$params);
    $result = $stmt->execute();
    
    if (!$result) {
        return false;
    }
    
    return $conn->insert_id;
}

/**
 * Update record in table
 */
function update($table, $data, $where, $whereParams = [], $whereTypes = '') {
    global $conn;
    
    $updates = [];
    foreach (array_keys($data) as $column) {
        $updates[] = "$column = ?";
    }
    
    $updateStr = implode(', ', $updates);
    $sql = "UPDATE $table SET $updateStr WHERE $where";
    
    $params = array_merge(array_values($data), $whereParams);
    
    // Generate types string
    $types = '';
    foreach ($params as $param) {
        if (is_int($param)) {
            $types .= 'i';
        } elseif (is_float($param)) {
            $types .= 'd';
        } elseif (is_string($param)) {
            $types .= 's';
        } else {
            $types .= 'b';
        }
    }
    
    // Execute query
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param($types, ...$params);
    
    return $stmt->execute();
}

/**
 * Execute a SQL query with parameters and no results
 * This is a wrapper for executeQuery for backward compatibility
 */
function query($sql, $params = [], $types = '') {
    return executeQuery($sql, $params, $types);
}

/**
 * Get a PDO database connection
 * 
 * @return PDO PDO database connection
 * @throws PDOException on connection failure
 */
function getConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('PDO Connection Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    return $pdo;
}