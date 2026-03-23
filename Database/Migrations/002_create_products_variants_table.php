<?php
// Manual table creation with PDO for PostgreSQL
$sql = "
    CREATE TABLE IF NOT EXISTS product_adjustments (
        id SERIAL PRIMARY KEY,
        product_id INT NOT NULL,
        user_id INT NOT NULL,
        quantity INT NOT NULL,
        action VARCHAR(20) NOT NULL,
        reason VARCHAR(200),
        adjustment_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
";

// Create indexes separately
$indexes = [
    "CREATE INDEX idx_product ON product_adjustments(product_id)",
    "CREATE INDEX idx_date ON product_adjustments(adjustment_date)",
    "CREATE INDEX idx_action ON product_adjustments(action)"
];
