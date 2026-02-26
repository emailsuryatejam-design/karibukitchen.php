<?php
require_once 'config.php';

$db = getDB();

// Create pilot tables
$sql = "

-- Pilot Users
CREATE TABLE IF NOT EXISTS pilot_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role ENUM('chef','store','admin') NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Standard items with portion weights
CREATE TABLE IF NOT EXISTS pilot_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    unit_weight DECIMAL(10,2) NOT NULL,
    weight_unit VARCHAR(10) NOT NULL DEFAULT 'g',
    portions_per_unit INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Daily sessions (one per day)
CREATE TABLE IF NOT EXISTS pilot_daily_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_date DATE NOT NULL,
    guest_count INT NOT NULL,
    chef_id INT NOT NULL,
    status ENUM('open','requisition_sent','supplied','day_closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_session_date (session_date),
    FOREIGN KEY (chef_id) REFERENCES pilot_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Requisition line items
CREATE TABLE IF NOT EXISTS pilot_requisitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    item_id INT NOT NULL,
    portions_requested INT NOT NULL DEFAULT 0,
    carryover_portions INT NOT NULL DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES pilot_daily_sessions(id),
    FOREIGN KEY (item_id) REFERENCES pilot_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Store supply records
CREATE TABLE IF NOT EXISTS pilot_store_supplies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requisition_id INT NOT NULL,
    portions_supplied INT NOT NULL DEFAULT 0,
    notes TEXT,
    supplied_by INT,
    supplied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requisition_id) REFERENCES pilot_requisitions(id),
    FOREIGN KEY (supplied_by) REFERENCES pilot_users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Day close records
CREATE TABLE IF NOT EXISTS pilot_day_close (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    item_id INT NOT NULL,
    portions_total INT NOT NULL DEFAULT 0,
    portions_consumed INT NOT NULL DEFAULT 0,
    portions_remaining INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES pilot_daily_sessions(id),
    FOREIGN KEY (item_id) REFERENCES pilot_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kitchen stock (running balance carried forward)
CREATE TABLE IF NOT EXISTS pilot_kitchen_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL UNIQUE,
    portions_available INT NOT NULL DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES pilot_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// Execute each statement
$statements = array_filter(array_map('trim', explode(';', $sql)));
foreach ($statements as $stmt) {
    if (!empty($stmt)) {
        try {
            $db->exec($stmt);
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage() . "<br>";
        }
    }
}

echo "<h3>Tables created successfully!</h3>";

// Seed default users
$users = [
    ['chef1', password_hash('chef123', PASSWORD_DEFAULT), 'Head Chef', 'chef'],
    ['store1', password_hash('store123', PASSWORD_DEFAULT), 'Store Manager', 'store'],
    ['admin1', password_hash('admin123', PASSWORD_DEFAULT), 'Administrator', 'admin'],
];

$stmt = $db->prepare("INSERT IGNORE INTO pilot_users (username, password_hash, name, role) VALUES (?, ?, ?, ?)");
foreach ($users as $u) {
    $stmt->execute($u);
}
echo "<p>Default users seeded (chef1/chef123, store1/store123, admin1/admin123)</p>";

// Seed standard items
$items = [
    // Poultry
    ['Whole Chicken', 'Poultry', 1500, 'g', 6],
    ['Chicken Wings', 'Poultry', 1000, 'g', 8],
    ['Chicken Thighs', 'Poultry', 500, 'g', 2],
    ['Chicken Breast', 'Poultry', 250, 'g', 1],
    ['Chicken Drumsticks', 'Poultry', 500, 'g', 4],
    ['Duck Breast', 'Poultry', 350, 'g', 2],
    ['Turkey Breast', 'Poultry', 500, 'g', 2],

    // Beef
    ['Beef Fillet', 'Beef', 250, 'g', 1],
    ['Beef Sirloin', 'Beef', 300, 'g', 1],
    ['Beef Ribeye', 'Beef', 350, 'g', 1],
    ['Beef Tenderloin', 'Beef', 200, 'g', 1],
    ['Beef Mince', 'Beef', 500, 'g', 4],
    ['Beef Short Ribs', 'Beef', 500, 'g', 2],
    ['Beef Brisket', 'Beef', 1000, 'g', 4],
    ['Beef Rump', 'Beef', 300, 'g', 1],

    // Lamb
    ['Lamb Rack', 'Lamb', 400, 'g', 2],
    ['Lamb Chops', 'Lamb', 200, 'g', 1],
    ['Lamb Shank', 'Lamb', 500, 'g', 1],
    ['Lamb Leg', 'Lamb', 2000, 'g', 8],
    ['Lamb Mince', 'Lamb', 500, 'g', 4],

    // Pork
    ['Pork Chops', 'Pork', 250, 'g', 1],
    ['Pork Belly', 'Pork', 500, 'g', 3],
    ['Pork Tenderloin', 'Pork', 400, 'g', 2],
    ['Pork Ribs', 'Pork', 1000, 'g', 4],
    ['Pork Sausages', 'Pork', 500, 'g', 4],

    // Seafood
    ['Salmon Fillet', 'Seafood', 200, 'g', 1],
    ['Tuna Steak', 'Seafood', 200, 'g', 1],
    ['Prawns', 'Seafood', 500, 'g', 4],
    ['Cod Fillet', 'Seafood', 200, 'g', 1],
    ['Sea Bass', 'Seafood', 300, 'g', 1],
    ['Lobster Tail', 'Seafood', 250, 'g', 1],
    ['Calamari', 'Seafood', 500, 'g', 4],
    ['Mussels', 'Seafood', 1000, 'g', 4],
    ['Crab Meat', 'Seafood', 250, 'g', 2],
    ['Tilapia Fillet', 'Seafood', 200, 'g', 1],

    // Vegetables
    ['Potatoes', 'Vegetables', 1000, 'g', 5],
    ['Rice (Basmati)', 'Grains', 1000, 'g', 10],
    ['Rice (Jasmine)', 'Grains', 1000, 'g', 10],
    ['Pasta (Penne)', 'Grains', 500, 'g', 5],
    ['Pasta (Spaghetti)', 'Grains', 500, 'g', 5],
    ['Carrots', 'Vegetables', 1000, 'g', 8],
    ['Broccoli', 'Vegetables', 500, 'g', 4],
    ['Spinach', 'Vegetables', 250, 'g', 4],
    ['Onions', 'Vegetables', 1000, 'g', 8],
    ['Tomatoes', 'Vegetables', 1000, 'g', 8],
    ['Bell Peppers', 'Vegetables', 500, 'g', 4],
    ['Mushrooms', 'Vegetables', 250, 'g', 4],
    ['Zucchini', 'Vegetables', 500, 'g', 4],
    ['Asparagus', 'Vegetables', 250, 'g', 4],
    ['Green Beans', 'Vegetables', 500, 'g', 5],
    ['Sweet Corn', 'Vegetables', 500, 'g', 4],
    ['Cabbage', 'Vegetables', 1000, 'g', 8],
    ['Cauliflower', 'Vegetables', 500, 'g', 4],
    ['Lettuce (Iceberg)', 'Vegetables', 300, 'g', 6],
    ['Cucumber', 'Vegetables', 300, 'g', 4],

    // Dairy & Eggs
    ['Butter', 'Dairy', 250, 'g', 25],
    ['Heavy Cream', 'Dairy', 500, 'ml', 10],
    ['Cheddar Cheese', 'Dairy', 500, 'g', 10],
    ['Parmesan Cheese', 'Dairy', 250, 'g', 15],
    ['Mozzarella Cheese', 'Dairy', 250, 'g', 5],
    ['Eggs (Dozen)', 'Dairy', 12, 'pcs', 12],
    ['Whole Milk', 'Dairy', 1000, 'ml', 8],
    ['Yogurt', 'Dairy', 500, 'g', 5],

    // Pantry Staples
    ['Olive Oil', 'Pantry', 1000, 'ml', 40],
    ['Vegetable Oil', 'Pantry', 1000, 'ml', 40],
    ['All-Purpose Flour', 'Pantry', 1000, 'g', 20],
    ['Sugar', 'Pantry', 1000, 'g', 50],
    ['Salt', 'Pantry', 500, 'g', 100],
    ['Black Pepper', 'Pantry', 100, 'g', 50],
    ['Garlic', 'Pantry', 100, 'g', 10],
    ['Ginger', 'Pantry', 100, 'g', 10],
    ['Soy Sauce', 'Pantry', 500, 'ml', 25],
    ['Tomato Paste', 'Pantry', 400, 'g', 8],
    ['Coconut Milk', 'Pantry', 400, 'ml', 4],
    ['Chicken Stock', 'Pantry', 1000, 'ml', 4],
    ['Beef Stock', 'Pantry', 1000, 'ml', 4],

    // Bread & Bakery
    ['Bread Loaf (White)', 'Bakery', 700, 'g', 14],
    ['Bread Rolls', 'Bakery', 50, 'g', 1],
    ['Naan Bread', 'Bakery', 100, 'g', 1],
    ['Tortilla Wraps', 'Bakery', 60, 'g', 1],
    ['Puff Pastry Sheet', 'Bakery', 500, 'g', 4],

    // Fruits
    ['Lemons', 'Fruits', 100, 'g', 2],
    ['Limes', 'Fruits', 50, 'g', 2],
    ['Oranges', 'Fruits', 200, 'g', 1],
    ['Bananas', 'Fruits', 150, 'g', 1],
    ['Strawberries', 'Fruits', 500, 'g', 5],
    ['Mixed Berries', 'Fruits', 500, 'g', 5],
];

$check = $db->query("SELECT COUNT(*) FROM pilot_items")->fetchColumn();
if ($check == 0) {
    $stmt = $db->prepare("INSERT INTO pilot_items (name, category, unit_weight, weight_unit, portions_per_unit) VALUES (?, ?, ?, ?, ?)");
    foreach ($items as $item) {
        $stmt->execute($item);
    }
    echo "<p>" . count($items) . " standard items seeded.</p>";
} else {
    echo "<p>Items table already has data (" . $check . " items). Skipping seed.</p>";
}

// Initialize kitchen stock for all items
$db->exec("INSERT IGNORE INTO pilot_kitchen_stock (item_id, portions_available)
            SELECT id, 0 FROM pilot_items WHERE id NOT IN (SELECT item_id FROM pilot_kitchen_stock)");
echo "<p>Kitchen stock initialized.</p>";

echo "<hr>";
echo "<h3>Setup Complete!</h3>";
echo "<p><strong>Login Credentials:</strong></p>";
echo "<table border='1' cellpadding='8' style='border-collapse:collapse'>";
echo "<tr><th>Role</th><th>Username</th><th>Password</th></tr>";
echo "<tr><td>Chef</td><td>chef1</td><td>chef123</td></tr>";
echo "<tr><td>Store</td><td>store1</td><td>store123</td></tr>";
echo "<tr><td>Admin</td><td>admin1</td><td>admin123</td></tr>";
echo "</table>";
echo "<br><p><a href='index.php'>Go to Login Page</a></p>";
