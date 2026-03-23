<?php
// 003_create_product_adjustment_table.php
// PostgreSQL version - uses SERIAL for auto-increment

$tableDefinition = [
    'name' => 'product_adjustments',
    'columns' => [
        ['name' => 'id', 'type' => 'SERIAL', 'primary' => true],
        ['name' => 'product_id', 'type' => 'INTEGER'],
        ['name' => 'user_id', 'type' => 'INTEGER'],
        ['name' => 'quantity', 'type' => 'INTEGER'],
        ['name' => 'type', 'type' => 'VARCHAR(50)'],
        ['name' => 'reason', 'type' => 'TEXT'],
        ['name' => 'created_at', 'type' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
        ['name' => 'updated_at', 'type' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
    ]
];

echo "Table structure for product_adjustments created (PostgreSQL version).";
