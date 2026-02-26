<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'u929828006_Pantryplanner');
define('DB_USER', 'u929828006_Pantryplanner');
define('DB_PASS', '6145ury@Teja');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $pdo;
}

function isLoggedIn() {
    return isset($_SESSION['pilot_user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

function getUserRole() {
    return $_SESSION['pilot_user_role'] ?? '';
}

function getUserName() {
    return $_SESSION['pilot_user_name'] ?? '';
}

function getUserId() {
    return $_SESSION['pilot_user_id'] ?? 0;
}
