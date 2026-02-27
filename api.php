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
        if (!$check->fetch()) { echo json_encode(['success'=>false,'message'=>'Session not open']); exit; }
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO pilot_requisitions (session_id,item_id,portions_requested,carryover_portions,notes) VALUES (?,?,?,?,?)");
            foreach ($items as $item) $stmt->execute([$sessionId, intval($item['item_id']), intval($item['portions']), intval($item['carryover']??0), $item['notes']??'']);
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
            $stmt = $db->prepare("INSERT INTO pilot_store_supplies (requisition_id,portions_supplied,notes,supplied_by) VALUES (?,?,?,?)");
            foreach ($items as $item) $stmt->execute([intval($item['requisition_id']), intval($item['supplied']), $item['notes']??'', getUserId()]);
            $db->prepare("UPDATE pilot_daily_sessions SET status='supplied' WHERE id=?")->execute([$sessionId]);
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
            $dcStmt = $db->prepare("INSERT INTO pilot_day_close (session_id,item_id,portions_total,portions_consumed,portions_remaining) VALUES (?,?,?,?,?)");
            $skStmt = $db->prepare("INSERT INTO pilot_kitchen_stock (item_id,kitchen_id,portions_available) VALUES (?,?,?) ON DUPLICATE KEY UPDATE portions_available=VALUES(portions_available)");
            foreach ($items as $item) {
                $itemId=intval($item['item_id']); $total=intval($item['total']); $remaining=intval($item['remaining']); $consumed=$total-$remaining;
                $dcStmt->execute([$sessionId,$itemId,$total,$consumed,$remaining]);
                $skStmt->execute([$itemId,$kitchenId,$remaining]);
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
        $db->prepare("UPDATE pilot_users SET is_active=? WHERE id=?")->execute([intval($_POST['is_active']??0), intval($_POST['user_id']??0)]);
        echo json_encode(['success'=>true]);
        break;

    case 'assign_kitchen':
        if (getUserRole()!=='admin') { echo json_encode(['success'=>false,'message'=>'Not authorized']); exit; }
        $kitchenId = intval($_POST['kitchen_id']??0)?:null;
        $db->prepare("UPDATE pilot_users SET kitchen_id=? WHERE id=?")->execute([$kitchenId, intval($_POST['user_id']??0)]);
        echo json_encode(['success'=>true]);
        break;

    case 'add_item':
        if (getUserRole()!=='admin') { echo json_encode(['success'=>false,'message'=>'Not authorized']); exit; }
        $name=trim($_POST['name']??''); $category=trim($_POST['category']??''); $uw=floatval($_POST['unit_weight']??0); $wu=trim($_POST['weight_unit']??'g'); $ppu=intval($_POST['portions_per_unit']??1);
        if (!$name||!$category||$uw<=0) { echo json_encode(['success'=>false,'message'=>'Fill all fields']); exit; }
        $db->prepare("INSERT INTO pilot_items (name,category,unit_weight,weight_unit,portions_per_unit) VALUES (?,?,?,?,?)")->execute([$name,$category,$uw,$wu,$ppu]);
        $newItemId = $db->lastInsertId();
        // Init stock for all kitchens
        $db->prepare("INSERT IGNORE INTO pilot_kitchen_stock (item_id,kitchen_id,portions_available) SELECT ?, id, 0 FROM pilot_kitchens")->execute([$newItemId]);
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
