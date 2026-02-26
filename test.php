<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Pantry Planner - Diagnostics</h2>";

// 1. PHP Version
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";

// 2. Required Extensions
$extensions = ['pdo', 'pdo_mysql', 'json', 'session'];
echo "<p><strong>Extensions:</strong></p><ul>";
foreach ($extensions as $ext) {
    $status = extension_loaded($ext) ? '✅' : '❌ MISSING';
    echo "<li>$ext: $status</li>";
}
echo "</ul>";

// 3. Database Connection
echo "<p><strong>Database Test:</strong> ";
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=u929828006_Pantryplanner;charset=utf8mb4",
        "u929828006_Pantryplanner",
        "6145ury@Teja",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Connected successfully!</p>";

    // Check if pilot tables exist
    $tables = $pdo->query("SHOW TABLES LIKE 'pilot_%'")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p><strong>Pilot Tables:</strong> ";
    if (empty($tables)) {
        echo "❌ No pilot tables found. <a href='setup.php'>Run Setup</a></p>";
    } else {
        echo "✅ " . count($tables) . " tables found (" . implode(', ', $tables) . ")</p>";
    }
} catch (PDOException $e) {
    echo "❌ FAILED: " . $e->getMessage() . "</p>";
}

echo "<hr><p><a href='setup.php'>Run Setup</a> | <a href='index.php'>Go to Login</a></p>";
