<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$db = getDB();
$action = $_POST['action'] ?? '';

switch ($action) {

    // ============================================================
    // CREATE DAILY SESSION (Guest count popup)
    // ============================================================
    case 'create_session':
        $guestCount = intval($_POST['guest_count'] ?? 0);
        if ($guestCount < 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid guest count']);
            exit;
        }
        $today = date('Y-m-d');

        // Check if session already exists
        $check = $db->prepare("SELECT id FROM pilot_daily_sessions WHERE session_date = ?");
        $check->execute([$today]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Session already exists for today']);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO pilot_daily_sessions (session_date, guest_count, chef_id, status) VALUES (?, ?, ?, 'open')");
        $stmt->execute([$today, $guestCount, getUserId()]);

        echo json_encode(['success' => true, 'session_id' => $db->lastInsertId()]);
        break;

    // ============================================================
    // SUBMIT REQUISITION
    // ============================================================
    case 'submit_requisition':
        $sessionId = intval($_POST['session_id'] ?? 0);
        $items = json_decode($_POST['items'] ?? '[]', true);

        if (!$sessionId || empty($items)) {
            echo json_encode(['success' => false, 'message' => 'Missing data']);
            exit;
        }

        // Verify session is open
        $check = $db->prepare("SELECT * FROM pilot_daily_sessions WHERE id = ? AND status = 'open'");
        $check->execute([$sessionId]);
        if (!$check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Session not open']);
            exit;
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO pilot_requisitions (session_id, item_id, portions_requested, carryover_portions, notes) VALUES (?, ?, ?, ?, ?)");
            foreach ($items as $item) {
                $stmt->execute([
                    $sessionId,
                    intval($item['item_id']),
                    intval($item['portions']),
                    intval($item['carryover'] ?? 0),
                    $item['notes'] ?? ''
                ]);
            }

            // Update session status
            $db->prepare("UPDATE pilot_daily_sessions SET status = 'requisition_sent' WHERE id = ?")->execute([$sessionId]);

            $db->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    // ============================================================
    // MARK SUPPLIED (Store)
    // ============================================================
    case 'mark_supplied':
        $sessionId = intval($_POST['session_id'] ?? 0);
        $items = json_decode($_POST['items'] ?? '[]', true);

        if (!$sessionId || empty($items)) {
            echo json_encode(['success' => false, 'message' => 'Missing data']);
            exit;
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO pilot_store_supplies (requisition_id, portions_supplied, notes, supplied_by) VALUES (?, ?, ?, ?)");
            foreach ($items as $item) {
                $stmt->execute([
                    intval($item['requisition_id']),
                    intval($item['supplied']),
                    $item['notes'] ?? '',
                    getUserId()
                ]);
            }

            // Update session status
            $db->prepare("UPDATE pilot_daily_sessions SET status = 'supplied' WHERE id = ?")->execute([$sessionId]);

            $db->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    // ============================================================
    // DAY CLOSE
    // ============================================================
    case 'day_close':
        $sessionId = intval($_POST['session_id'] ?? 0);
        $items = json_decode($_POST['items'] ?? '[]', true);

        if (!$sessionId || empty($items)) {
            echo json_encode(['success' => false, 'message' => 'Missing data']);
            exit;
        }

        $db->beginTransaction();
        try {
            $dcStmt = $db->prepare("INSERT INTO pilot_day_close (session_id, item_id, portions_total, portions_consumed, portions_remaining) VALUES (?, ?, ?, ?, ?)");
            $stockStmt = $db->prepare("INSERT INTO pilot_kitchen_stock (item_id, portions_available) VALUES (?, ?) ON DUPLICATE KEY UPDATE portions_available = VALUES(portions_available)");

            foreach ($items as $item) {
                $itemId = intval($item['item_id']);
                $total = intval($item['total']);
                $remaining = intval($item['remaining']);
                $consumed = $total - $remaining;

                $dcStmt->execute([$sessionId, $itemId, $total, $consumed, $remaining]);
                $stockStmt->execute([$itemId, $remaining]);
            }

            // Also reset stock to 0 for items NOT in today's requisition
            // (they had no activity, stock remains as-is from previous day close)

            // Update session status
            $db->prepare("UPDATE pilot_daily_sessions SET status = 'day_closed' WHERE id = ?")->execute([$sessionId]);

            $db->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    // ============================================================
    // ADD ITEM (Admin)
    // ============================================================
    case 'add_item':
        if (getUserRole() !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Not authorized']);
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $unitWeight = floatval($_POST['unit_weight'] ?? 0);
        $weightUnit = trim($_POST['weight_unit'] ?? 'g');
        $portionsPerUnit = intval($_POST['portions_per_unit'] ?? 1);

        if (!$name || !$category || $unitWeight <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please fill all fields']);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO pilot_items (name, category, unit_weight, weight_unit, portions_per_unit) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $category, $unitWeight, $weightUnit, $portionsPerUnit]);

        // Init kitchen stock
        $db->prepare("INSERT IGNORE INTO pilot_kitchen_stock (item_id, portions_available) VALUES (?, 0)")->execute([$db->lastInsertId()]);

        echo json_encode(['success' => true]);
        break;

    // ============================================================
    // TOGGLE ITEM (Admin)
    // ============================================================
    case 'toggle_item':
        if (getUserRole() !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Not authorized']);
            exit;
        }

        $itemId = intval($_POST['item_id'] ?? 0);
        $isActive = intval($_POST['is_active'] ?? 0);

        $stmt = $db->prepare("UPDATE pilot_items SET is_active = ? WHERE id = ?");
        $stmt->execute([$isActive, $itemId]);

        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
