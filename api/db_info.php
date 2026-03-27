<?php
/**
 * Database Inspector Script
 * Shows database name, tables, columns, and data
 */

header('Content-Type: application/json');

// Include database connection
require_once __DIR__ . '/../db_connect.php';

// Check if database is connected
if (!isDBConnected()) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => getDBError()
    ]);
    exit;
}

try {
    // Get database name
    $dbNameResult = $pdo->query("SELECT current_database()");
    $databaseName = $dbNameResult->fetchColumn();
    
    // Get all tables
    $tablesResult = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        ORDER BY table_name
    ");
    $tables = $tablesResult->fetchAll(PDO::FETCH_COLUMN);
    
    $result = [
        'success' => true,
        'database' => $databaseName,
        'tables' => []
    ];
    
    // For each table, get columns and data
    foreach ($tables as $tableName) {
        // Get columns info
        $columnsResult = $pdo->query("
            SELECT 
                column_name,
                data_type,
                character_maximum_length,
                is_nullable,
                column_default
            FROM information_schema.columns
            WHERE table_name = '$tableName' AND table_schema = 'public'
            ORDER BY ordinal_position
        ");
        $columns = $columnsResult->fetchAll(PDO::FETCH_ASSOC);
        
        // Get table data
        $dataResult = $pdo->query("SELECT * FROM $tableName");
        $data = $dataResult->fetchAll(PDO::FETCH_ASSOC);
        
        // Get row count
        $countResult = $pdo->query("SELECT COUNT(*) FROM $tableName");
        $rowCount = $countResult->fetchColumn();
        
        $result['tables'][$tableName] = [
            'columns' => $columns,
            'row_count' => $rowCount,
            'data' => $data
        ];
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to get database info',
        'error' => $e->getMessage()
    ]);
}
