<?php
require_once 'config.php';
header('Content-Type: application/json');
if (!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Not logged in']); exit; }

$db = getDB();
$action = $_POST['action'] ?? '';

switch ($action) {

    case 'create_session':
        $guestCount = intval($_POST['guest_count'] ?? 0);
        $kitchenId = intval($_POST['kitchen_id'] ?? 0);
        if ($guestCount < 1 || $kitchenId < 1) { echo json_encode(['success'=>false,'message'=>'Invalid data']); exit; }
        $today = date('Y-m-d');
        $check = $db->prepare("SELECT id FROM pilot_daily_sessions WHERE session_date=? AND kitchen_id=?");
        $check->execute([$today, $kitchenId]);
        if ($check->fetch()) { echo json_encode(['success'=>false,'message'=>'Session already exists']); exit; }
        $stmt = $db->prepare("INSERT INTO pilot_daily_sessions (session_date, guest_count, chef_id, kitchen_id, status) VALUES (?,?,?,?,'open')");
        $stmt->execute([$today, $guestCount, getUserId(), $kitchenId]);
        echo json_encode(['success'=>true,'session_id'=>$db->lastInsertId()]);
        break;

    case 'submit_requisition':
        $sessionId = intval($_POST['session_id'] ?? 0);
        $items = json_decode($_POST['items'] ?? '[]', true);
        if (!$sessionId || empty($items)) { echo json_encode(['success'=>false,'message'=>'Missing data']); exit; }
        $check = $db->prepare("SELECT * FROM pilot_daily_sessions WHERE id=? AND status='open'"); $check->execute([$sessionId]);
        $sess = $check->fetch();
        if (!$sess) { echo json_encode(['success'=>false,'message'=>'Session not open']); exit; }
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO pilot_requisitions (session_id, item_id, portions_requested, required_kg, roundoff_kg, instock_kg, order_kg, carryover_portions, notes) VALUES (?,?,?,?,?,?,?,0,?)");
            foreach ($items as $item) {
                $itemId = intval($item['item_id'] ?? 0);
                // Custom/ad-hoc item — auto-create in pilot_items
                if ($itemId === 0 && !empty($item['custom_name'])) {
                    $customName = trim($item['custom_name']);
                    $db->prepare("INSERT INTO pilot_items (name, category, unit_weight, weight_unit, portions_per_unit, portion_weight_kg) VALUES (?,'Custom',1000,'g',3.33,0.300)")
                        ->execute([$customName]);
                    $itemId = intval($db->lastInsertId());
                    // Add stock entry for this kitchen
                    $db->prepare("INSERT IGNORE INTO pilot_kitchen_stock (item_id, kitchen_id, portions_available, kg_available) VALUES (?,?,0,0)")
                        ->execute([$itemId, $sess['kitchen_id']]);
                }
                if ($itemId < 1) continue;
                $stmt->execute([
                    $sessionId,
                    $itemId,
                    intval($item['portions']),
                    round(floatval($item['required_kg']), 2),
                    round(floatval($item['roundoff_kg']), 2),
                    round(floatval($item['instock_kg']), 2),
                    round(floatval($item['order_kg']), 2),
                    $item['notes'] ?? ''
                ]);
            }
            $db->prepare("UPDATE pilot_daily_sessions SET status='requisition_sent' WHERE id=?")->execute([$sessionId]);
            $db->commit();
            echo json_encode(['success'=>true]);
        } catch (Exception $e) { $db->rollBack(); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        break;

    case 'mark_supplied':
        $sessionId = intval($_POST['session_id'] ?? 0);
        $items = json_decode($_POST['items'] ?? '[]', true);
        if (!$sessionId || empty($items)) { echo json_encode(['success'=>false,'message'=>'Missing data']); exit; }
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO pilot_store_supplies (requisition_id, kg_supplied, portions_supplied, notes, supplied_by) VALUES (?,?,0,?,?)");
            foreach ($items as $item) {
                $stmt->execute([
                    intval($item['requisition_id']),
                    round(floatval($item['kg_supplied']), 2),
                    $item['notes'] ?? '',
                    getUserId()
                ]);
            }
            $db->prepare("UPDATE pilot_daily_sessions SET status='supplied' WHERE id=?")->execute([$sessionId]);
            $db->commit();
            echo json_encode(['success'=>true]);
        } catch (Exception $e) { $db->rollBack(); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        break;

    case 'add_substitutes':
        $sessionId = intval($_POST['session_id'] ?? 0);
        $items = json_decode($_POST['items'] ?? '[]', true);
        if (!$sessionId || empty($items)) { echo json_encode(['success'=>false,'message'=>'Missing data']); exit; }
        $sess = $db->prepare("SELECT * FROM pilot_daily_sessions WHERE id=?"); $sess->execute([$sessionId]); $sess = $sess->fetch();
        if (!$sess) { echo json_encode(['success'=>false,'message'=>'Session not found']); exit; }
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO pilot_requisitions (session_id, item_id, portions_requested, required_kg, roundoff_kg, instock_kg, order_kg, carryover_portions, notes) VALUES (?,?,0,?,?,0,?,0,?)");
            foreach ($items as $item) {
                $itemId = intval($item['item_id'] ?? 0);
                $orderKg = round(floatval($item['order_kg']), 2);
                if ($itemId < 1 || $orderKg <= 0) continue;
                $stmt->execute([$sessionId, $itemId, $orderKg, $orderKg, $orderKg, $item['notes'] ?? '']);
            }
            // Set status back to requisition_sent so store sees the new items
            $db->prepare("UPDATE pilot_daily_sessions SET status='requisition_sent' WHERE id=?")->execute([$sessionId]);
            $db->commit();
            echo json_encode(['success'=>true]);
        } catch (Exception $e) { $db->rollBack(); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        break;

    case 'day_close':
        $sessionId = intval($_POST['session_id'] ?? 0);
        $kitchenId = intval($_POST['kitchen_id'] ?? 0);
        $items = json_decode($_POST['items'] ?? '[]', true);
        if (!$sessionId || empty($items)) { echo json_encode(['success'=>false,'message'=>'Missing data']); exit; }
        $db->beginTransaction();
        try {
            $dcStmt = $db->prepare("INSERT INTO pilot_day_close (session_id, item_id, kg_total, kg_remaining, portions_total, portions_consumed, portions_remaining) VALUES (?,?,?,?,0,0,0)");
            $skStmt = $db->prepare("INSERT INTO pilot_kitchen_stock (item_id, kitchen_id, kg_available, portions_available) VALUES (?,?,?,0) ON DUPLICATE KEY UPDATE kg_available=VALUES(kg_available)");
            foreach ($items as $item) {
                $itemId = intval($item['item_id']);
                $kgTotal = round(floatval($item['kg_total']), 2);
                $kgRemaining = round(floatval($item['kg_remaining']), 2);
                $dcStmt->execute([$sessionId, $itemId, $kgTotal, $kgRemaining]);
                $skStmt->execute([$itemId, $kitchenId, $kgRemaining]);
            }
            $db->prepare("UPDATE pilot_daily_sessions SET status='day_closed' WHERE id=?")->execute([$sessionId]);
            $db->commit();
            echo json_encode(['success'=>true]);
        } catch (Exception $e) { $db->rollBack(); echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
        break;

    case 'add_kitchen':
        if (getUserRole()!=='admin') { echo json_encode(['success'=>false,'message'=>'Not authorized']); exit; }
        $name=trim($_POST['name']??''); $code=strtoupper(trim($_POST['code']??'')); $location=trim($_POST['location']??'');
        if (!$name||!$code) { echo json_encode(['success'=>false,'message'=>'Name and code required']); exit; }
        try { $db->prepare("INSERT INTO pilot_kitchens (name,code,location) VALUES (?,?,?)")->execute([$name,$code,$location]); echo json_encode(['success'=>true]); }
        catch (Exception $e) { echo json_encode(['success'=>false,'message'=>'Code already exists']); }
        break;

    case 'toggle_kitchen':
        if (getUserRole()!=='admin') { echo json_encode(['success'=>false,'message'=>'Not authorized']); exit; }
        $db->prepare("UPDATE pilot_kitchens SET is_active=? WHERE id=?")->execute([intval($_POST['is_active']??0), intval($_POST['kitchen_id']??0)]);
        echo json_encode(['success'=>true]);
        break;

    case 'add_user':
        if (getUserRole()!=='admin') { echo json_encode(['success'=>false,'message'=>'Not authorized']); exit; }
        $username=trim($_POST['username']??''); $password=$_POST['password']??''; $fullname=trim($_POST['fullname']??''); $role=$_POST['role']??'chef'; $kitchenId=intval($_POST['kitchen_id']??0)?:null;
        if (!$username||!$password||!$fullname) { echo json_encode(['success'=>false,'message'=>'All fields required']); exit; }
        try { $db->prepare("INSERT INTO pilot_users (username,password_hash,name,role,kitchen_id) VALUES (?,?,?,?,?)")->execute([$username,password_hash($password,PASSWORD_DEFAULT),$fullname,$role,$kitchenId]); echo json_encode(['success'=>true]); }
        catch (Exception $e) { echo json_encode(['success'=>false,'message'=>'Username already exists']); }
        break;

    case 'toggle_user':
        if (getUserRole()!=='admin') { echo json_encode(['success'=>false,'message'=>'Not authorized']); exit; }
        $targetId = intval($_POST['user_id']??0);
        $newActive = intval($_POST['is_active']??0);
        // Prevent deactivating the last active admin
        if ($newActive === 0) {
            $target = $db->prepare("SELECT role FROM pilot_users WHERE id=?"); $target->execute([$targetId]); $target = $target->fetch();
            if ($target && $target['role'] === 'admin') {
                $adminCount = $db->query("SELECT COUNT(*) FROM pilot_users WHERE role='admin' AND is_active=1")->fetchColumn();
                if ($adminCount <= 1) { echo json_encode(['success'=>false,'message'=>'Cannot deactivate the last admin']); exit; }
            }
        }
        // Prevent deactivating yourself
        if ($targetId == getUserId() && $newActive === 0) { echo json_encode(['success'=>false,'message'=>'Cannot deactivate yourself']); exit; }
        $db->prepare("UPDATE pilot_users SET is_active=? WHERE id=?")->execute([$newActive, $targetId]);
        echo json_encode(['success'=>true]);
        break;

    case 'edit_user':
        if (getUserRole()!=='admin') { echo json_encode(['success'=>false,'message'=>'Not authorized']); exit; }
        $userId = intval($_POST['user_id']??0);
        $username = trim($_POST['username']??'');
        $fullname = trim($_POST['fullname']??'');
        $role = $_POST['role']??'chef';
        $kitchenId = intval($_POST['kitchen_id']??0)?:null;
        $password = $_POST['password']??'';
        if (!$userId || !$username || !$fullname) { echo json_encode(['success'=>false,'message'=>'All fields required']); exit; }
        // Check username uniqueness (excluding current user)
        $dup = $db->prepare("SELECT id FROM pilot_users WHERE username=? AND id!=?"); $dup->execute([$username, $userId]);
        if ($dup->fetch()) { echo json_encode(['success'=>false,'message'=>'Username already taken']); exit; }
        try {
            if ($password) {
                $db->prepare("UPDATE pilot_users SET username=?, name=?, role=?, kitchen_id=?, password_hash=? WHERE id=?")
                    ->execute([$username, $fullname, $role, $kitchenId, password_hash($password, PASSWORD_DEFAULT), $userId]);
            } else {
                $db->prepare("UPDATE pilot_users SET username=?, name=?, role=?, kitchen_id=? WHERE id=?")
                    ->execute([$username, $fullname, $role, $kitchenId, $userId]);
            }
            echo json_encode(['success'=>true]);
        } catch (Exception $e) { echo json_encode(['success'=>false,'message'=>'Error updating user']); }
        break;

    case 'assign_kitchen':
        if (getUserRole()!=='admin') { echo json_encode(['success'=>false,'message'=>'Not authorized']); exit; }
        $kitchenId = intval($_POST['kitchen_id']??0)?:null;
        $db->prepare("UPDATE pilot_users SET kitchen_id=? WHERE id=?")->execute([$kitchenId, intval($_POST['user_id']??0)]);
        echo json_encode(['success'=>true]);
        break;

    case 'add_item':
        if (getUserRole()!=='admin') { echo json_encode(['success'=>false,'message'=>'Not authorized']); exit; }
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $portionGrams = floatval($_POST['portion_grams'] ?? 300);
        $portionWeightKg = round($portionGrams / 1000, 3);
        if (!$name || !$category || $portionGrams <= 0) { echo json_encode(['success'=>false,'message'=>'Fill all fields']); exit; }
        $portionsPerKg = round(1 / $portionWeightKg, 2);
        $db->prepare("INSERT INTO pilot_items (name, category, unit_weight, weight_unit, portions_per_unit, portion_weight_kg) VALUES (?,?,?,?,?,?)")
            ->execute([$name, $category, $portionGrams, 'g', $portionsPerKg, $portionWeightKg]);
        $newItemId = $db->lastInsertId();
        $db->prepare("INSERT IGNORE INTO pilot_kitchen_stock (item_id, kitchen_id, portions_available, kg_available) SELECT ?, id, 0, 0 FROM pilot_kitchens")->execute([$newItemId]);
        echo json_encode(['success'=>true]);
        break;

    case 'toggle_item':
        if (getUserRole()!=='admin') { echo json_encode(['success'=>false,'message'=>'Not authorized']); exit; }
        $db->prepare("UPDATE pilot_items SET is_active=? WHERE id=?")->execute([intval($_POST['is_active']??0), intval($_POST['item_id']??0)]);
        echo json_encode(['success'=>true]);
        break;

    default:
        echo json_encode(['success'=>false,'message'=>'Unknown action']);
}
