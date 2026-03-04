<?php
// 003_create_product_adjustment_table.php
// This is just a template - not executable Laravel code

$tableDefinition = [
    'name' => 'product_adjustments',
    'columns' => [
        ['name' => 'id', 'type' => 'INT', 'primary' => true, 'auto_increment' => true],
        ['name' => 'product_id', 'type' => 'INT'],
        ['name' => 'user_id', 'type' => 'INT'],
        ['name' => 'quantity', 'type' => 'INT'],
        ['name' => 'type', 'type' => 'VARCHAR(50)'],
        ['name' => 'reason', 'type' => 'TEXT'],
        ['name' => 'created_at', 'type' => 'TIMESTAMP'],
        ['name' => 'updated_at', 'type' => 'TIMESTAMP'],
    ]
];

echo "Table structure for product_adjustments created.";