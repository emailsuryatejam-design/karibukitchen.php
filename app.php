<?php
require_once 'config.php';
requireLogin();

$db = getDB();
$role = getUserRole();
$userId = getUserId();
$today = date('Y-m-d');

// Get user's kitchen
$userStmt = $db->prepare("SELECT kitchen_id FROM pilot_users WHERE id = ?");
$userStmt->execute([$userId]);
$userKitchenId = $userStmt->fetchColumn();

// Admin can switch kitchens via dropdown
$activeKitchenId = $userKitchenId;
if ($role === 'admin') {
    if (isset($_GET['kitchen_id'])) {
        $_SESSION['admin_kitchen_id'] = intval($_GET['kitchen_id']);
    }
    $activeKitchenId = $_SESSION['admin_kitchen_id'] ?? null;
}

// Get all kitchens for admin dropdown
$allKitchens = $db->query("SELECT * FROM pilot_kitchens WHERE is_active = 1 ORDER BY name")->fetchAll();

// Get active kitchen info
$activeKitchen = null;
if ($activeKitchenId) {
    $ks = $db->prepare("SELECT * FROM pilot_kitchens WHERE id = ?");
    $ks->execute([$activeKitchenId]);
    $activeKitchen = $ks->fetch();
}

$defaultPage = match($role) {
    'store' => 'store_dashboard',
    'admin' => 'manage_kitchens',
    default => 'chef_dashboard'
};
$page = $_GET['page'] ?? $defaultPage;

// Get today's session for active kitchen
$todaySession = null;
if ($activeKitchenId) {
    $stmt = $db->prepare("SELECT * FROM pilot_daily_sessions WHERE session_date = ? AND kitchen_id = ?");
    $stmt->execute([$today, $activeKitchenId]);
    $todaySession = $stmt->fetch();
}

// Chef needs guest count popup only if they have a kitchen assigned
$showGuestPopup = ($role === 'chef' && $activeKitchenId && !$todaySession && $page !== 'reports' && $page !== 'history');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karibu Kitchen<?= $activeKitchen ? ' - ' . htmlspecialchars($activeKitchen['name']) : '' ?></title>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#0f3460">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Karibu Kitchen">
    <link rel="apple-touch-icon" href="icons/icon-152.png">
    <link rel="icon" type="image/png" sizes="192x192" href="icons/icon-192.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --primary: #0f3460; --accent: #e94560; --dark: #1a1a2e; --light-bg: #f4f6f9; }
        body { background: var(--light-bg); font-family: 'Segoe UI', system-ui, sans-serif; padding-bottom: 0; }
        .navbar { background: var(--dark) !important; }
        .navbar-brand { font-weight: 700; color: #fff !important; }
        .badge-pilot { background: var(--accent); font-size: 0.65rem; padding: 3px 8px; border-radius: 10px; vertical-align: middle; }
        .sidebar { background: #fff; min-height: calc(100vh - 56px); border-right: 1px solid #e0e0e0; padding-top: 20px; }
        .sidebar .nav-link { color: #333; padding: 10px 20px; border-radius: 0; font-weight: 500; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: var(--primary); color: #fff; }
        .sidebar .nav-link i { margin-right: 8px; width: 20px; text-align: center; }
        .main-content { padding: 24px; }
        .card { border: none; box-shadow: 0 2px 12px rgba(0,0,0,0.08); border-radius: 12px; }
        .card-header { background: var(--primary); color: #fff; border-radius: 12px 12px 0 0 !important; font-weight: 600; }
        .btn-primary { background: var(--primary); border-color: var(--primary); }
        .btn-primary:hover { background: var(--dark); border-color: var(--dark); }
        .btn-accent { background: var(--accent); border-color: var(--accent); color: #fff; }
        .btn-accent:hover { background: #c73a52; border-color: #c73a52; color: #fff; }
        .status-badge { font-size: 0.8rem; padding: 4px 12px; border-radius: 20px; }
        .table th { background: #f8f9fa; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card { text-align: center; padding: 20px; }
        .stat-card .stat-number { font-size: 2rem; font-weight: 700; color: var(--primary); }
        .stat-card .stat-label { color: #6c757d; font-size: 0.85rem; }
        .category-header { background: #e8ecf1; padding: 8px 15px; font-weight: 600; color: var(--primary); border-radius: 6px; margin: 15px 0 8px; }
        .item-row:hover { background: #f0f4ff; }
        .kg-badge { background: #d4edda; color: #155724; font-size: 0.8rem; padding: 2px 8px; border-radius: 10px; }
        .shortage { color: var(--accent); font-weight: 600; }
        .surplus { color: #28a745; font-weight: 600; }
        .kitchen-badge { background: #e8ecf1; color: var(--primary); padding: 4px 12px; border-radius: 15px; font-size: 0.8rem; font-weight: 600; }
        .kitchen-switcher select { background: rgba(255,255,255,0.15); color: #fff; border: 1px solid rgba(255,255,255,0.3); border-radius: 6px; padding: 4px 8px; font-size: 0.85rem; }
        .kitchen-switcher select option { color: #333; }
        .auto-calc { background: #f0f4ff; font-weight: 600; text-align: center; }
        .uom-badge { background: #e8ecf1; color: var(--primary); padding: 1px 6px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
        .hamburger { display:none; background:none; border:none; color:#fff; font-size:1.5rem; cursor:pointer; padding:4px 8px; }
        .drawer-handle { display:none; }

        /* Desktop sidebar */
        @media (min-width: 768px) {
            .sidebar { display:block !important; }
            .drawer-overlay { display:none !important; }
        }

        /* Mobile bottom drawer */
        @media (max-width: 767px) {
            .hamburger { display:inline-block; }
            .sidebar {
                position:fixed; bottom:0; left:0; right:0; top:auto;
                width:100% !important; flex:none !important; max-width:100% !important;
                min-height:auto; max-height:65vh;
                border-radius:20px 20px 0 0;
                border-right:none; border-top:1px solid #e0e0e0;
                box-shadow:0 -8px 30px rgba(0,0,0,0.25);
                transform:translateY(100%);
                transition:transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
                overflow-y:auto; z-index:1050;
                padding-top:0; padding-bottom:env(safe-area-inset-bottom, 20px);
            }
            .sidebar.open { transform:translateY(0); }
            .sidebar .drawer-handle {
                display:block; width:40px; height:4px;
                background:#ccc; border-radius:2px;
                margin:12px auto 8px;
            }
            .sidebar .nav-link { padding:14px 24px; font-size:1rem; border-bottom:1px solid #f0f0f0; }
            .sidebar .nav-link i { font-size:1.1rem; }
            .sidebar hr { margin:4px 20px; }
            .drawer-overlay {
                display:none; position:fixed; top:0; left:0; right:0; bottom:0;
                background:rgba(0,0,0,0.4); z-index:1040;
            }
            .drawer-overlay.open { display:block; }
            .col-md-10 { width:100% !important; flex:0 0 100% !important; max-width:100% !important; }
            .main-content { padding: 15px !important; }
            .navbar .d-flex { flex-wrap:wrap; gap:5px !important; font-size:0.85rem; }
            .stat-card .stat-number { font-size:1.4rem; }
            .table { font-size:0.82rem; }
            body { padding-bottom: 0; }
        }
        @media print {
            .no-print { display: none !important; }
            .sidebar { display: none !important; }
            .navbar { display: none !important; }
            .main-content { padding: 0 !important; }
            .card { box-shadow: none !important; border: 1px solid #ddd !important; }
            body { background: #fff; }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-dark px-3 no-print">
        <div class="d-flex align-items-center">
            <button class="hamburger me-2" onclick="toggleDrawer()" aria-label="Menu"><i class="bi bi-list"></i></button>
            <span class="navbar-brand mb-0">
                <i class="bi bi-clipboard2-check"></i> Karibu Kitchen <span class="badge-pilot">PILOT</span>
            <?php if ($activeKitchen): ?>
                <span class="kitchen-badge ms-2"><i class="bi bi-building"></i> <?= htmlspecialchars($activeKitchen['name']) ?></span>
            <?php endif; ?>
            </span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <?php if ($role === 'admin' && count($allKitchens) > 0): ?>
                <div class="kitchen-switcher">
                    <select onchange="if(this.value) location.href='?page=<?= $page ?>&kitchen_id='+this.value">
                        <option value="">-- Select Kitchen --</option>
                        <?php foreach ($allKitchens as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= $activeKitchenId == $k['id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <span class="text-light">
                <i class="bi bi-person-circle"></i> <?= htmlspecialchars(getUserName()) ?>
                <span class="badge bg-light text-dark"><?= ucfirst($role) ?></span>
            </span>
            <span class="text-light opacity-75 d-none d-md-inline"><i class="bi bi-calendar3"></i> <?= date('D, M j, Y') ?></span>
            <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </nav>

    <div class="drawer-overlay no-print" id="drawerOverlay" onclick="toggleDrawer()"></div>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar / Bottom Drawer -->
            <div class="col-md-2 sidebar no-print p-0" id="sidebar">
                <div class="drawer-handle"></div>
                <nav class="nav flex-column">
                    <?php if ($role === 'chef' || $role === 'admin'): ?>
                        <a class="nav-link <?= $page === 'chef_dashboard' ? 'active' : '' ?>" href="?page=chef_dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
                        <a class="nav-link <?= $page === 'requisition' ? 'active' : '' ?>" href="?page=requisition"><i class="bi bi-cart-plus"></i> Requisition</a>
                        <a class="nav-link <?= $page === 'review_supply' ? 'active' : '' ?>" href="?page=review_supply"><i class="bi bi-clipboard-check"></i> Review Supply</a>
                        <a class="nav-link <?= $page === 'day_close' ? 'active' : '' ?>" href="?page=day_close"><i class="bi bi-moon-stars"></i> Day Close</a>
                    <?php endif; ?>
                    <?php if ($role === 'store' || $role === 'admin'): ?>
                        <a class="nav-link <?= $page === 'store_dashboard' ? 'active' : '' ?>" href="?page=store_dashboard"><i class="bi bi-shop"></i> Store Dashboard</a>
                        <a class="nav-link <?= $page === 'supply' ? 'active' : '' ?>" href="?page=supply"><i class="bi bi-truck"></i> Supply Items</a>
                    <?php endif; ?>
                    <hr class="mx-3">
                    <a class="nav-link <?= $page === 'reports' ? 'active' : '' ?>" href="?page=reports"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a>
                    <a class="nav-link <?= $page === 'history' ? 'active' : '' ?>" href="?page=history"><i class="bi bi-clock-history"></i> History</a>
                    <?php if ($role === 'admin'): ?>
                        <hr class="mx-3">
                        <a class="nav-link <?= $page === 'manage_kitchens' ? 'active' : '' ?>" href="?page=manage_kitchens"><i class="bi bi-building"></i> Manage Kitchens</a>
                        <a class="nav-link <?= $page === 'manage_users' ? 'active' : '' ?>" href="?page=manage_users"><i class="bi bi-people"></i> Manage Users</a>
                        <a class="nav-link <?= $page === 'manage_items' ? 'active' : '' ?>" href="?page=manage_items"><i class="bi bi-gear"></i> Manage Items</a>
                    <?php endif; ?>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <?php
                if (!$activeKitchenId && $role !== 'admin' && !in_array($page, ['reports','history'])) {
                    echo '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> You are not assigned to any kitchen. Please contact admin.</div>';
                } elseif ($role === 'admin' && !$activeKitchenId && in_array($page, ['chef_dashboard','requisition','review_supply','day_close','store_dashboard','supply'])) {
                    echo '<div class="alert alert-info"><i class="bi bi-info-circle"></i> Select a kitchen from the dropdown above to view operations.</div>';
                } else {
                    switch ($page) {
                        case 'chef_dashboard': include_chef_dashboard($db, $todaySession, $today, $activeKitchenId); break;
                        case 'requisition': include_requisition($db, $todaySession, $today, $activeKitchenId); break;
                        case 'review_supply': include_review_supply($db, $todaySession); break;
                        case 'day_close': include_day_close($db, $todaySession, $activeKitchenId); break;
                        case 'store_dashboard': include_store_dashboard($db, $today, $activeKitchenId); break;
                        case 'supply': include_supply($db, $today, $activeKitchenId); break;
                        case 'reports': include_reports($db, $activeKitchenId, $allKitchens); break;
                        case 'history': include_history($db, $activeKitchenId, $allKitchens); break;
                        case 'manage_kitchens': include_manage_kitchens($db); break;
                        case 'manage_users': include_manage_users($db); break;
                        case 'manage_items': include_manage_items($db); break;
                        default: echo '<div class="alert alert-warning">Page not found.</div>';
                    }
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Guest Count Modal -->
    <?php if ($showGuestPopup): ?>
    <div class="modal fade" id="guestModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background:var(--primary);color:#fff;">
                    <h5 class="modal-title"><i class="bi bi-people-fill"></i> Good Morning, Chef!</h5>
                </div>
                <div class="modal-body text-center py-4">
                    <p class="text-muted mb-2"><?= htmlspecialchars($activeKitchen['name'] ?? '') ?></p>
                    <p class="mb-3 fs-5">How many bed nights / guests today?</p>
                    <p class="text-muted mb-3"><?= date('l, F j, Y') ?></p>
                    <input type="number" id="guestCount" class="form-control form-control-lg text-center mx-auto" style="max-width:200px" min="1" max="9999" placeholder="0" autofocus>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-primary btn-lg px-5" onclick="submitGuestCount()"><i class="bi bi-check-lg"></i> Start Day</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="text-center py-3 no-print" style="background:#1a1a2e; color:rgba(255,255,255,0.5); font-size:0.8rem;">
        Powered by <strong style="color:rgba(255,255,255,0.7);">VyomaAI Studios</strong>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
    <script>
    <?php if ($showGuestPopup): ?>
    $(document).ready(function() { new bootstrap.Modal('#guestModal').show(); $('#guestCount').focus(); });
    function submitGuestCount() {
        const count = parseInt($('#guestCount').val());
        if (!count || count < 1) { alert('Please enter a valid number.'); return; }
        $.post('api.php', { action: 'create_session', guest_count: count, kitchen_id: <?= $activeKitchenId ?> }, function(res) {
            if (res.success) location.reload(); else alert(res.message || 'Error');
        }, 'json');
    }
    $('#guestCount').on('keypress', function(e) { if (e.which === 13) submitGuestCount(); });
    <?php endif; ?>

    // Bottom drawer toggle
    function toggleDrawer() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('drawerOverlay').classList.toggle('open');
    }
    // Close drawer when clicking a nav link on mobile
    document.querySelectorAll('.sidebar .nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 767) {
                document.getElementById('sidebar').classList.remove('open');
                document.getElementById('drawerOverlay').classList.remove('open');
            }
        });
    });

    function printSection(id) {
        const content = document.getElementById(id).innerHTML;
        const win = window.open('', '_blank');
        win.document.write('<html><head><title>Print</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><style>body{padding:20px;font-family:system-ui;}.no-print{display:none!important;}.shortage{color:#e94560;font-weight:600;}.surplus{color:#28a745;font-weight:600;}@media print{.no-print{display:none!important;}}</style></head><body>' + content + '</body></html>');
        win.document.close();
        setTimeout(() => win.print(), 500);
    }
    function downloadCSV(tableId, filename) {
        const table = document.getElementById(tableId);
        if (!table) return;
        let csv = [];
        table.querySelectorAll('tr').forEach(row => {
            let rowData = [];
            row.querySelectorAll('td, th').forEach(col => { if (!col.classList.contains('no-print')) rowData.push('"' + col.innerText.replace(/"/g, '""') + '"'); });
            csv.push(rowData.join(','));
        });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv.join('\n')], { type: 'text/csv' }));
        a.download = filename + '.csv';
        a.click();
    }

    // Register Service Worker
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').then(reg => console.log('SW registered')).catch(err => console.log('SW error:', err));
    }
    </script>
</body>
</html>

<?php
// ============================================================
// CHEF DASHBOARD
// ============================================================
function include_chef_dashboard($db, $session, $today, $kitchenId) {
    if (!$session) {
        echo '<div class="alert alert-info"><i class="bi bi-info-circle"></i> Set the guest count to begin today\'s session.</div>';
        return;
    }
    $reqCount = $db->prepare("SELECT COUNT(*) FROM pilot_requisitions WHERE session_id = ?"); $reqCount->execute([$session['id']]);
    $totalReqItems = $reqCount->fetchColumn();
    $totalKg = $db->prepare("SELECT COALESCE(SUM(order_kg),0) FROM pilot_requisitions WHERE session_id = ?"); $totalKg->execute([$session['id']]);
    $totalOrderKg = $totalKg->fetchColumn();
    $statusLabels = ['open'=>['Open','bg-primary'],'requisition_sent'=>['Requisition Sent','bg-warning text-dark'],'supplied'=>['Supplied','bg-success'],'day_closed'=>['Day Closed','bg-secondary']];
    $statusInfo = $statusLabels[$session['status']] ?? ['Unknown','bg-dark'];
?>
    <h4 class="mb-4">Chef Dashboard</h4>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="card stat-card"><div class="stat-number"><?= $session['guest_count'] ?></div><div class="stat-label">Bed Nights</div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card"><div class="stat-number"><?= $totalReqItems ?></div><div class="stat-label">Items Ordered</div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card"><div class="stat-number"><?= number_format($totalOrderKg, 1) ?> <small>kg</small></div><div class="stat-label">Total Order</div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card"><span class="badge <?= $statusInfo[1] ?> status-badge fs-6"><?= $statusInfo[0] ?></span><div class="stat-label mt-2">Session Status</div></div></div>
    </div>
    <div class="card"><div class="card-header">Quick Actions</div><div class="card-body"><div class="d-flex gap-2 flex-wrap">
        <?php if ($session['status'] === 'open'): ?><a href="?page=requisition" class="btn btn-primary"><i class="bi bi-cart-plus"></i> Create Requisition</a><?php endif; ?>
        <?php if ($session['status'] === 'supplied'): ?><a href="?page=review_supply" class="btn btn-primary"><i class="bi bi-clipboard-check"></i> Review Supply</a><a href="?page=day_close" class="btn btn-accent"><i class="bi bi-moon-stars"></i> Day Close</a><?php endif; ?>
        <?php if ($session['status'] === 'requisition_sent'): ?><span class="btn btn-outline-secondary disabled"><i class="bi bi-hourglass-split"></i> Waiting for Store...</span><?php endif; ?>
        <a href="?page=reports" class="btn btn-outline-primary"><i class="bi bi-file-earmark-bar-graph"></i> View Reports</a>
    </div></div></div>
<?php }

// ============================================================
// REQUISITION (KG-based)
// ============================================================
function include_requisition($db, $session, $today, $kitchenId) {
    if (!$session) { echo '<div class="alert alert-warning">Please set the guest count first.</div>'; return; }

    // Already submitted - show read-only
    if ($session['status'] !== 'open') {
        $stmt = $db->prepare("SELECT r.*, i.name, i.category, i.portion_weight_kg FROM pilot_requisitions r JOIN pilot_items i ON r.item_id = i.id WHERE r.session_id = ? ORDER BY i.category, i.name");
        $stmt->execute([$session['id']]); $items = $stmt->fetchAll();
        echo '<h4 class="mb-3">Today\'s Requisition <span class="badge bg-info">Submitted</span></h4>';
        echo '<p class="text-muted">Bed Nights: <strong>'.$session['guest_count'].'</strong> | All quantities in <span class="uom-badge">KG</span></p>';
        echo '<div id="printRequisition" class="table-responsive"><h5 class="mb-2 d-none d-print-block">Requisition - '.date('D, M j, Y').' | Bed Nights: '.$session['guest_count'].'</h5>';
        echo '<table class="table table-striped table-sm" id="reqTable"><thead><tr><th>#</th><th>Item</th><th>UOM</th><th>Portion</th><th>Per KG</th><th>Portions</th><th>Required</th><th>Round Off</th><th>In Stock</th><th>Order</th><th>Notes</th></tr></thead><tbody>';
        $n=1; $totalOrder=0;
        foreach($items as $it) {
            $perKg = $it['portion_weight_kg'] > 0 ? round(1/$it['portion_weight_kg'], 2) : 0;
            $totalOrder += $it['order_kg'];
            echo '<tr><td>'.$n++.'</td><td>'.htmlspecialchars($it['name']).'</td><td>kg</td>';
            echo '<td>'.number_format($it['portion_weight_kg'],3).'</td><td>'.$perKg.'</td>';
            echo '<td><strong>'.$it['portions_requested'].'</strong></td>';
            echo '<td>'.number_format($it['required_kg'],2).'</td>';
            echo '<td>'.number_format($it['roundoff_kg'],2).'</td>';
            echo '<td>'.($it['instock_kg']>0?'<span class="kg-badge">'.$it['instock_kg'].'</span>':'0.00').'</td>';
            echo '<td><strong>'.number_format($it['order_kg'],2).'</strong></td>';
            echo '<td>'.htmlspecialchars($it['notes']??'').'</td></tr>';
        }
        echo '</tbody><tfoot><tr class="fw-bold"><td colspan="9">TOTAL ORDER</td><td>'.number_format($totalOrder,2).' kg</td><td></td></tr></tfoot></table></div>';
        echo '<div class="no-print d-flex gap-2"><button class="btn btn-outline-primary" onclick="printSection(\'printRequisition\')"><i class="bi bi-printer"></i> Print</button>';
        echo '<button class="btn btn-outline-success" onclick="downloadCSV(\'reqTable\',\'requisition_'.$today.'\')"><i class="bi bi-download"></i> CSV</button></div>';
        return;
    }

    // Creating requisition
    $items = $db->prepare("SELECT i.*, COALESCE(ks.kg_available, 0) as stock_kg FROM pilot_items i LEFT JOIN pilot_kitchen_stock ks ON i.id = ks.item_id AND ks.kitchen_id = ? WHERE i.is_active = 1 ORDER BY i.category, i.name");
    $items->execute([$kitchenId]); $items = $items->fetchAll();
    $categories = []; foreach($items as $item) $categories[$item['category']][] = $item;
?>
    <h4 class="mb-3">Create Requisition <small class="text-muted">| Bed Nights: <?= $session['guest_count'] ?></small></h4>
    <p class="text-muted mb-3"><i class="bi bi-info-circle"></i> All quantities in <span class="uom-badge">KG</span>. Enter portions needed — order auto-calculates.</p>
    <form id="requisitionForm"><input type="hidden" name="session_id" value="<?= $session['id'] ?>">
        <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-cart-plus"></i> Select Items & Portions</span><input type="text" id="itemSearch" class="form-control form-control-sm" placeholder="Search items..." style="width:200px"></div>
        <div class="card-body p-0">
        <?php foreach ($categories as $cat => $catItems): ?>
            <div class="category-header"><?= htmlspecialchars($cat) ?> (<?= count($catItems) ?> items)</div>
            <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr>
                <th style="width:35px"></th><th>Item</th><th>UOM</th><th>Portion<br><small>kg</small></th><th>Per<br><small>KG</small></th>
                <th>In Stock<br><small>kg</small></th><th style="width:90px">Portions<br><small>Needed</small></th>
                <th>Required<br><small>kg</small></th><th>Round Off<br><small>kg</small></th><th>Order<br><small>kg</small></th><th style="width:140px">Notes</th>
            </tr></thead><tbody>
            <?php foreach($catItems as $item): ?>
            <tr class="item-row" data-name="<?= strtolower($item['name']) ?>" data-portion-kg="<?= $item['portion_weight_kg'] ?>" data-instock-kg="<?= number_format($item['stock_kg'],2,'.','') ?>">
                <td><input type="checkbox" class="form-check-input item-check" data-id="<?= $item['id'] ?>"></td>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td><span class="uom-badge">kg</span></td>
                <td><?= number_format($item['portion_weight_kg'],3) ?></td>
                <td><?= $item['portion_weight_kg']>0 ? round(1/$item['portion_weight_kg'],2) : 0 ?></td>
                <td><?= $item['stock_kg']>0 ? '<span class="kg-badge">'.$item['stock_kg'].'</span>' : '<span class="text-muted">0.00</span>' ?></td>
                <td><input type="number" class="form-control form-control-sm portions-input" min="0" value="0" disabled></td>
                <td class="auto-calc required-kg">0.00</td>
                <td class="auto-calc roundoff-kg">0.00</td>
                <td class="auto-calc order-kg fw-bold">0.00</td>
                <td><input type="text" class="form-control form-control-sm notes-input" placeholder="—" disabled></td>
            </tr>
            <?php endforeach; ?></tbody></table></div>
        <?php endforeach; ?>
        </div></div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-send"></i> Submit Requisition to Store</button>
            <span class="text-muted" id="reqSummary"></span>
        </div>
    </form>
    <script>
    $(document).ready(function(){
        // Toggle row inputs on checkbox change
        $('.item-check').on('change', function(){
            const row = $(this).closest('tr');
            const on = $(this).is(':checked');
            row.find('.portions-input, .notes-input').prop('disabled', !on);
            if (on) { row.find('.portions-input').val(1).focus().trigger('input'); }
            else { row.find('.portions-input').val(0).trigger('input'); }
        });

        // Auto-calculate kg values when portions change
        $('.portions-input').on('input', function(){
            const row = $(this).closest('tr');
            const portions = parseInt($(this).val()) || 0;
            const portionKg = parseFloat(row.data('portion-kg')) || 0.3;
            const instockKg = parseFloat(row.data('instock-kg')) || 0;

            const requiredKg = portions * portionKg;
            const roundoffKg = Math.ceil(requiredKg);
            const orderKg = Math.max(0, roundoffKg - instockKg);

            row.find('.required-kg').text(requiredKg.toFixed(2));
            row.find('.roundoff-kg').text(roundoffKg.toFixed(2));
            row.find('.order-kg').text(orderKg.toFixed(2));

            updateSummary();
        });

        function updateSummary() {
            let items = 0, totalKg = 0;
            $('.item-check:checked').each(function(){
                const row = $(this).closest('tr');
                const order = parseFloat(row.find('.order-kg').text()) || 0;
                if (order > 0) { items++; totalKg += order; }
            });
            $('#reqSummary').html(items > 0 ? '<i class="bi bi-box-seam"></i> ' + items + ' items, <strong>' + totalKg.toFixed(2) + ' kg</strong> total order' : '');
        }

        // Search filter
        $('#itemSearch').on('input', function(){
            const q = $(this).val().toLowerCase();
            $('.item-row').each(function(){ $(this).toggle($(this).data('name').includes(q)); });
        });

        // Submit requisition
        $('#requisitionForm').on('submit', function(e){
            e.preventDefault();
            const checked = $('.item-check:checked');
            if (!checked.length) { alert('Select at least one item.'); return; }
            let items = [];
            checked.each(function(){
                const id = $(this).data('id');
                const row = $(this).closest('tr');
                const portions = parseInt(row.find('.portions-input').val()) || 0;
                if (portions > 0) {
                    items.push({
                        item_id: id,
                        portions: portions,
                        required_kg: parseFloat(row.find('.required-kg').text()) || 0,
                        roundoff_kg: parseFloat(row.find('.roundoff-kg').text()) || 0,
                        instock_kg: parseFloat(row.data('instock-kg')) || 0,
                        order_kg: parseFloat(row.find('.order-kg').text()) || 0,
                        notes: row.find('.notes-input').val() || ''
                    });
                }
            });
            if (!items.length) { alert('Enter portions for at least one item.'); return; }
            if (!confirm('Submit requisition? (' + items.length + ' items)')) return;
            $.post('api.php', {action:'submit_requisition', session_id:<?=$session['id']?>, items:JSON.stringify(items)}, function(res){
                if (res.success) { alert('Requisition submitted!'); location.href='?page=chef_dashboard'; }
                else alert(res.message || 'Error');
            }, 'json');
        });
    });
    </script>
<?php }

// ============================================================
// REVIEW SUPPLY (KG-based)
// ============================================================
function include_review_supply($db, $session) {
    if (!$session) { echo '<div class="alert alert-warning">No session for today.</div>'; return; }
    $stmt = $db->prepare("SELECT r.*, i.name, i.category, COALESCE(ss.kg_supplied, 0) as supplied_kg, ss.notes as supply_notes FROM pilot_requisitions r JOIN pilot_items i ON r.item_id=i.id LEFT JOIN pilot_store_supplies ss ON ss.requisition_id=r.id WHERE r.session_id=? ORDER BY i.category, i.name");
    $stmt->execute([$session['id']]); $items = $stmt->fetchAll();
?>
    <h4 class="mb-3">Review Supply vs Order</h4>
    <p class="text-muted">All quantities in <span class="uom-badge">KG</span></p>
    <div id="printSupplyReview" class="table-responsive">
        <table class="table table-striped" id="supplyReviewTable">
            <thead><tr><th>#</th><th>Item</th><th>Category</th><th>Order (kg)</th><th>Supplied (kg)</th><th>Difference</th><th>Store Notes</th></tr></thead>
            <tbody>
            <?php $n=1; foreach($items as $it):
                $diff = $it['supplied_kg'] - $it['order_kg'];
                $cls = $diff < 0 ? 'shortage' : ($diff > 0 ? 'surplus' : '');
            ?>
            <tr>
                <td><?=$n++?></td><td><?=htmlspecialchars($it['name'])?></td><td><?=htmlspecialchars($it['category'])?></td>
                <td><strong><?=number_format($it['order_kg'],2)?></strong></td>
                <td><strong><?=number_format($it['supplied_kg'],2)?></strong></td>
                <td class="<?=$cls?>"><?=$diff>0?'+'.number_format($diff,2):($diff<0?number_format($diff,2):'-')?></td>
                <td><?=htmlspecialchars($it['supply_notes']??'')?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="no-print d-flex gap-2">
        <button class="btn btn-outline-primary" onclick="printSection('printSupplyReview')"><i class="bi bi-printer"></i> Print</button>
        <button class="btn btn-outline-success" onclick="downloadCSV('supplyReviewTable','supply_review_<?=date('Y-m-d')?>')"><i class="bi bi-download"></i> CSV</button>
    </div>
<?php }

// ============================================================
// DAY CLOSE (KG-based)
// ============================================================
function include_day_close($db, $session, $kitchenId) {
    if (!$session) { echo '<div class="alert alert-warning">No session for today.</div>'; return; }

    // Already closed - show report
    if ($session['status'] === 'day_closed') {
        $stmt = $db->prepare("SELECT dc.*, i.name, i.category FROM pilot_day_close dc JOIN pilot_items i ON dc.item_id=i.id WHERE dc.session_id=? ORDER BY i.category, i.name");
        $stmt->execute([$session['id']]); $items = $stmt->fetchAll();
        echo '<h4 class="mb-3">Day Close Report <span class="badge bg-secondary">Closed</span></h4>';
        echo '<p class="text-muted">All quantities in <span class="uom-badge">KG</span></p>';
        echo '<div id="printDayClose" class="table-responsive"><table class="table table-striped" id="dayCloseTable"><thead><tr><th>#</th><th>Item</th><th>Total (kg)</th><th>Consumed (kg)</th><th>Remaining (kg)</th></tr></thead><tbody>';
        $n=1; $tc=0; $tr=0;
        foreach($items as $it) {
            $consumed = $it['kg_total'] - $it['kg_remaining'];
            $tc += $consumed; $tr += $it['kg_remaining'];
            echo '<tr><td>'.$n++.'</td><td>'.htmlspecialchars($it['name']).'</td><td>'.number_format($it['kg_total'],2).'</td><td>'.number_format($consumed,2).'</td><td><strong>'.number_format($it['kg_remaining'],2).'</strong></td></tr>';
        }
        echo '</tbody><tfoot><tr class="fw-bold"><td colspan="2">TOTAL</td><td>'.number_format($tc+$tr,2).'</td><td>'.number_format($tc,2).'</td><td>'.number_format($tr,2).'</td></tr></tfoot></table></div>';
        echo '<div class="no-print d-flex gap-2"><button class="btn btn-outline-primary" onclick="printSection(\'printDayClose\')"><i class="bi bi-printer"></i> Print</button><button class="btn btn-outline-success" onclick="downloadCSV(\'dayCloseTable\',\'day_close_'.date('Y-m-d').'\')"><i class="bi bi-download"></i> CSV</button></div>';
        return;
    }

    if ($session['status'] !== 'supplied') { echo '<div class="alert alert-info">Day close available after store supplies items.</div>'; return; }

    // Get items with their instock and supplied kg
    $stmt = $db->prepare("SELECT r.*, i.name, i.category, COALESCE(ss.kg_supplied, 0) as supplied_kg FROM pilot_requisitions r JOIN pilot_items i ON r.item_id=i.id LEFT JOIN pilot_store_supplies ss ON ss.requisition_id=r.id WHERE r.session_id=? ORDER BY i.category, i.name");
    $stmt->execute([$session['id']]); $items = $stmt->fetchAll();
?>
    <h4 class="mb-3">Day Close — Enter Remaining KG</h4>
    <p class="text-muted">Enter the physically remaining quantity (in <span class="uom-badge">KG</span>) for each item in the kitchen.</p>
    <form id="dayCloseForm">
        <div class="table-responsive">
        <table class="table table-striped"><thead><tr><th>#</th><th>Item</th><th>In Stock (kg)</th><th>Supplied (kg)</th><th>Total (kg)</th><th style="width:130px">Remaining (kg)</th></tr></thead><tbody>
        <?php $n=1; foreach($items as $it):
            $totalKg = $it['instock_kg'] + $it['supplied_kg'];
        ?>
        <tr>
            <td><?=$n++?></td><td><?=htmlspecialchars($it['name'])?></td>
            <td><?=number_format($it['instock_kg'],2)?></td>
            <td><?=number_format($it['supplied_kg'],2)?></td>
            <td><strong><?=number_format($totalKg,2)?></strong></td>
            <td><input type="number" name="remaining[<?=$it['item_id']?>]" class="form-control form-control-sm" min="0" max="<?=$totalKg?>" step="0.1" value="0"
                data-item-id="<?=$it['item_id']?>" data-kg-total="<?=number_format($totalKg,2,'.','')?>"></td>
        </tr>
        <?php endforeach; ?></tbody></table>
        </div>
        <button type="submit" class="btn btn-accent btn-lg"><i class="bi bi-moon-stars"></i> Close Day</button>
    </form>
    <script>
    $('#dayCloseForm').on('submit', function(e){
        e.preventDefault();
        if (!confirm('Close the day? Remaining stock will carry over to tomorrow.')) return;
        let items = [];
        $('input[name^="remaining"]').each(function(){
            items.push({
                item_id: $(this).data('item-id'),
                kg_total: parseFloat($(this).data('kg-total')) || 0,
                kg_remaining: parseFloat($(this).val()) || 0
            });
        });
        $.post('api.php', {action:'day_close', session_id:<?=$session['id']?>, kitchen_id:<?=$kitchenId?>, items:JSON.stringify(items)}, function(res){
            if (res.success) { alert('Day closed! Remaining stock saved.'); location.href='?page=day_close'; }
            else alert(res.message || 'Error');
        }, 'json');
    });
    </script>
<?php }

// ============================================================
// STORE DASHBOARD
// ============================================================
function include_store_dashboard($db, $today, $kitchenId) {
    $stmt = $db->prepare("SELECT ds.*, u.name as chef_name, k.name as kitchen_name FROM pilot_daily_sessions ds JOIN pilot_users u ON ds.chef_id=u.id LEFT JOIN pilot_kitchens k ON ds.kitchen_id=k.id WHERE ds.session_date=? AND ds.kitchen_id=?");
    $stmt->execute([$today, $kitchenId]); $session = $stmt->fetch();
    if (!$session) { echo '<div class="alert alert-info"><i class="bi bi-info-circle"></i> No requisition from kitchen yet today.</div>'; return; }
    $reqCount = $db->prepare("SELECT COUNT(*) FROM pilot_requisitions WHERE session_id=?"); $reqCount->execute([$session['id']]); $totalItems = $reqCount->fetchColumn();
    $totalKgStmt = $db->prepare("SELECT COALESCE(SUM(order_kg),0) FROM pilot_requisitions WHERE session_id=?"); $totalKgStmt->execute([$session['id']]); $totalOrderKg = $totalKgStmt->fetchColumn();
    $statusLabels = ['open'=>['Kitchen Preparing','bg-secondary'],'requisition_sent'=>['Pending Supply','bg-warning text-dark'],'supplied'=>['Supplied','bg-success'],'day_closed'=>['Day Closed','bg-secondary']];
    $si = $statusLabels[$session['status']] ?? ['Unknown','bg-dark'];
?>
    <h4 class="mb-4">Store Dashboard</h4>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="card stat-card"><div class="stat-number"><?=$session['guest_count']?></div><div class="stat-label">Bed Nights</div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card"><div class="stat-number"><?=$totalItems?></div><div class="stat-label">Items Ordered</div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card"><div class="stat-number"><?=number_format($totalOrderKg,1)?> <small>kg</small></div><div class="stat-label">Total Order</div></div></div>
        <div class="col-6 col-md-3"><div class="card stat-card"><span class="badge <?=$si[1]?> status-badge fs-6"><?=$si[0]?></span><div class="stat-label mt-2">Status</div></div></div>
    </div>
    <?php if($session['status']==='requisition_sent'): ?>
        <a href="?page=supply" class="btn btn-primary btn-lg"><i class="bi bi-truck"></i> Supply Items Now</a>
    <?php elseif($session['status']==='supplied'): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> Items supplied. Waiting for kitchen day close.</div>
    <?php endif; ?>
<?php }

// ============================================================
// SUPPLY (KG-based)
// ============================================================
function include_supply($db, $today, $kitchenId) {
    $stmt = $db->prepare("SELECT * FROM pilot_daily_sessions WHERE session_date=? AND kitchen_id=?"); $stmt->execute([$today, $kitchenId]); $session = $stmt->fetch();
    if (!$session || $session['status']==='open') { echo '<div class="alert alert-info">No requisition from kitchen yet.</div>'; return; }

    // Already supplied - show log
    if ($session['status']==='supplied' || $session['status']==='day_closed') {
        $stmt = $db->prepare("SELECT r.*, i.name, i.category, COALESCE(ss.kg_supplied,0) as supplied_kg, ss.notes as supply_notes FROM pilot_requisitions r JOIN pilot_items i ON r.item_id=i.id LEFT JOIN pilot_store_supplies ss ON ss.requisition_id=r.id WHERE r.session_id=? ORDER BY i.category, i.name");
        $stmt->execute([$session['id']]); $items = $stmt->fetchAll();
        echo '<h4 class="mb-3">Supply Log <span class="badge bg-success">Completed</span></h4>';
        echo '<p class="text-muted">All quantities in <span class="uom-badge">KG</span></p>';
        echo '<div id="printStoreLog" class="table-responsive"><table class="table table-striped" id="storeLogTable"><thead><tr><th>#</th><th>Item</th><th>Order (kg)</th><th>Supplied (kg)</th><th>Diff</th><th>Notes</th></tr></thead><tbody>';
        $n=1;
        foreach($items as $it) {
            $d = $it['supplied_kg'] - $it['order_kg'];
            $c = $d<0?'shortage':($d>0?'surplus':'');
            echo '<tr><td>'.$n++.'</td><td>'.htmlspecialchars($it['name']).'</td><td>'.number_format($it['order_kg'],2).'</td><td>'.number_format($it['supplied_kg'],2).'</td>';
            echo '<td class="'.$c.'">'.($d>0?'+'.number_format($d,2):($d<0?number_format($d,2):'-')).'</td><td>'.htmlspecialchars($it['supply_notes']??'').'</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<div class="no-print d-flex gap-2"><button class="btn btn-outline-primary" onclick="printSection(\'printStoreLog\')"><i class="bi bi-printer"></i> Print</button><button class="btn btn-outline-success" onclick="downloadCSV(\'storeLogTable\',\'store_log_'.$today.'\')"><i class="bi bi-download"></i> CSV</button></div>';
        return;
    }

    // Supply form
    $stmt = $db->prepare("SELECT r.*, i.name, i.category FROM pilot_requisitions r JOIN pilot_items i ON r.item_id=i.id WHERE r.session_id=? ORDER BY i.category, i.name");
    $stmt->execute([$session['id']]); $items = $stmt->fetchAll();
?>
    <h4 class="mb-3">Supply Items <small class="text-muted">| All in KG</small></h4>
    <form id="supplyForm">
        <div id="printSupplyForm" class="table-responsive">
        <table class="table table-striped"><thead><tr><th>#</th><th>Item</th><th>Category</th><th>Order (kg)</th><th style="width:130px">Supplying (kg)</th><th style="width:200px">Notes</th></tr></thead><tbody>
        <?php $n=1; foreach($items as $it): ?>
        <tr>
            <td><?=$n++?></td><td><?=htmlspecialchars($it['name'])?></td><td><?=htmlspecialchars($it['category'])?></td>
            <td><strong><?=number_format($it['order_kg'],2)?></strong></td>
            <td><input type="number" name="supply[<?=$it['id']?>]" class="form-control form-control-sm" min="0" step="0.1" value="<?=number_format($it['order_kg'],2,'.','')?>"></td>
            <td><input type="text" name="notes[<?=$it['id']?>]" class="form-control form-control-sm" placeholder="e.g. short stock"></td>
        </tr>
        <?php endforeach; ?></tbody></table></div>
        <div class="d-flex gap-2 no-print">
            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-circle"></i> Mark as Supplied</button>
            <button type="button" class="btn btn-outline-primary" onclick="printSection('printSupplyForm')"><i class="bi bi-printer"></i> Print</button>
        </div>
    </form>
    <script>
    $('#supplyForm').on('submit', function(e){
        e.preventDefault();
        if (!confirm('Confirm supply quantities?')) return;
        let items = [];
        $('input[name^="supply"]').each(function(){
            const reqId = $(this).attr('name').match(/\d+/)[0];
            items.push({
                requisition_id: reqId,
                kg_supplied: parseFloat($(this).val()) || 0,
                notes: $('input[name="notes['+reqId+']"]').val() || ''
            });
        });
        $.post('api.php', {action:'mark_supplied', session_id:<?=$session['id']?>, items:JSON.stringify(items)}, function(res){
            if (res.success) { alert('Supply recorded!'); location.href='?page=store_dashboard'; }
            else alert(res.message || 'Error');
        }, 'json');
    });
    </script>
<?php }

// ============================================================
// REPORTS (KG-based)
// ============================================================
function include_reports($db, $kitchenId, $allKitchens) {
    $reportDate = $_GET['date'] ?? date('Y-m-d');
    $reportKitchen = $_GET['rk'] ?? $kitchenId;
    $stmt = $db->prepare("SELECT ds.*, u.name as chef_name, k.name as kitchen_name FROM pilot_daily_sessions ds JOIN pilot_users u ON ds.chef_id=u.id LEFT JOIN pilot_kitchens k ON ds.kitchen_id=k.id WHERE ds.session_date=? AND ds.kitchen_id=?");
    $stmt->execute([$reportDate, $reportKitchen]); $session = $stmt->fetch();
?>
    <h4 class="mb-3">Daily Report</h4>
    <form class="row g-2 mb-4 no-print" method="GET">
        <input type="hidden" name="page" value="reports">
        <div class="col-auto"><input type="date" name="date" class="form-control" value="<?=$reportDate?>"></div>
        <div class="col-auto"><select name="rk" class="form-select"><?php foreach($allKitchens as $k): ?><option value="<?=$k['id']?>" <?=$reportKitchen==$k['id']?'selected':''?>><?=htmlspecialchars($k['name'])?></option><?php endforeach; ?></select></div>
        <div class="col-auto"><button class="btn btn-primary">View Report</button></div>
    </form>
    <?php if(!$session){echo '<div class="alert alert-info">No session found for this date.</div>';return;} ?>
    <div id="printFullReport">
        <div class="text-center mb-4">
            <h5>Karibu Kitchen — Daily Report</h5>
            <p class="mb-1"><strong>Kitchen:</strong> <?=htmlspecialchars($session['kitchen_name']??'')?> | <strong>Date:</strong> <?=date('l, F j, Y',strtotime($reportDate))?></p>
            <p><strong>Chef:</strong> <?=htmlspecialchars($session['chef_name'])?> | <strong>Bed Nights:</strong> <?=$session['guest_count']?> | <strong>Status:</strong> <?=ucfirst(str_replace('_',' ',$session['status']))?></p>
        </div>

        <h5 style="color:var(--primary)"><i class="bi bi-1-circle"></i> Requisition (KG)</h5>
        <?php $rs=$db->prepare("SELECT r.*, i.name, i.category, i.portion_weight_kg FROM pilot_requisitions r JOIN pilot_items i ON r.item_id=i.id WHERE r.session_id=? ORDER BY i.category, i.name"); $rs->execute([$session['id']]); $ri=$rs->fetchAll(); ?>
        <div class="table-responsive"><table class="table table-sm table-striped" id="rptReq"><thead><tr><th>#</th><th>Item</th><th>UOM</th><th>Portions</th><th>Required</th><th>Round Off</th><th>In Stock</th><th>Order</th></tr></thead><tbody>
        <?php $n=1; $tOrder=0; foreach($ri as $it){ $tOrder+=$it['order_kg']; echo '<tr><td>'.$n++.'</td><td>'.htmlspecialchars($it['name']).'</td><td>kg</td><td>'.$it['portions_requested'].'</td><td>'.number_format($it['required_kg'],2).'</td><td>'.number_format($it['roundoff_kg'],2).'</td><td>'.number_format($it['instock_kg'],2).'</td><td><strong>'.number_format($it['order_kg'],2).'</strong></td></tr>'; } ?>
        </tbody><tfoot><tr class="fw-bold"><td colspan="7">TOTAL ORDER</td><td><?=number_format($tOrder,2)?> kg</td></tr></tfoot></table></div>

        <h5 class="mt-4" style="color:var(--primary)"><i class="bi bi-2-circle"></i> Store Supply Log (KG)</h5>
        <?php $ss=$db->prepare("SELECT r.order_kg, i.name, i.category, COALESCE(s.kg_supplied,0) as supplied_kg, s.notes as sn FROM pilot_requisitions r JOIN pilot_items i ON r.item_id=i.id LEFT JOIN pilot_store_supplies s ON s.requisition_id=r.id WHERE r.session_id=? ORDER BY i.category, i.name"); $ss->execute([$session['id']]); $si=$ss->fetchAll(); ?>
        <div class="table-responsive"><table class="table table-sm table-striped" id="rptSup"><thead><tr><th>#</th><th>Item</th><th>Order (kg)</th><th>Supplied (kg)</th><th>Diff</th><th>Notes</th></tr></thead><tbody>
        <?php $n=1; foreach($si as $it){ $d=$it['supplied_kg']-$it['order_kg']; $c=$d<0?'shortage':($d>0?'surplus':'');
            echo '<tr><td>'.$n++.'</td><td>'.htmlspecialchars($it['name']).'</td><td>'.number_format($it['order_kg'],2).'</td><td>'.number_format($it['supplied_kg'],2).'</td><td class="'.$c.'">'.($d>0?'+'.number_format($d,2):($d<0?number_format($d,2):'-')).'</td><td>'.htmlspecialchars($it['sn']??'').'</td></tr>';} ?>
        </tbody></table></div>

        <?php if($session['status']==='day_closed'): ?>
        <h5 class="mt-4" style="color:var(--primary)"><i class="bi bi-3-circle"></i> Consumption Report (KG)</h5>
        <?php $dc=$db->prepare("SELECT dc.*, i.name, i.category FROM pilot_day_close dc JOIN pilot_items i ON dc.item_id=i.id WHERE dc.session_id=? ORDER BY i.category, i.name"); $dc->execute([$session['id']]); $di=$dc->fetchAll(); $tc=0;$trem=0; ?>
        <div class="table-responsive"><table class="table table-sm table-striped" id="rptCon"><thead><tr><th>#</th><th>Item</th><th>Total (kg)</th><th>Consumed (kg)</th><th>Remaining (kg)</th></tr></thead><tbody>
        <?php $n=1; foreach($di as $it){$consumed=$it['kg_total']-$it['kg_remaining'];$tc+=$consumed;$trem+=$it['kg_remaining'];echo '<tr><td>'.$n++.'</td><td>'.htmlspecialchars($it['name']).'</td><td>'.number_format($it['kg_total'],2).'</td><td>'.number_format($consumed,2).'</td><td><strong>'.number_format($it['kg_remaining'],2).'</strong></td></tr>';} ?>
        </tbody><tfoot><tr class="fw-bold"><td colspan="2">TOTAL</td><td><?=number_format($tc+$trem,2)?></td><td><?=number_format($tc,2)?></td><td><?=number_format($trem,2)?></td></tr></tfoot></table></div>
        <?php endif; ?>
    </div>
    <div class="no-print d-flex gap-2 mt-3 flex-wrap">
        <button class="btn btn-primary" onclick="printSection('printFullReport')"><i class="bi bi-printer"></i> Print Full Report</button>
        <button class="btn btn-outline-success" onclick="downloadCSV('rptReq','requisition_<?=$reportDate?>')"><i class="bi bi-download"></i> Requisition CSV</button>
        <button class="btn btn-outline-success" onclick="downloadCSV('rptSup','supply_<?=$reportDate?>')"><i class="bi bi-download"></i> Supply CSV</button>
        <?php if($session['status']==='day_closed'): ?><button class="btn btn-outline-success" onclick="downloadCSV('rptCon','consumption_<?=$reportDate?>')"><i class="bi bi-download"></i> Consumption CSV</button><?php endif; ?>
    </div>
<?php }

// ============================================================
// HISTORY
// ============================================================
function include_history($db, $kitchenId, $allKitchens) {
    $hk = $_GET['hk'] ?? $kitchenId;
    $sessions = $db->prepare("SELECT ds.*, u.name as chef_name, k.name as kitchen_name,
        (SELECT COUNT(*) FROM pilot_requisitions WHERE session_id=ds.id) as item_count,
        (SELECT COALESCE(SUM(order_kg),0) FROM pilot_requisitions WHERE session_id=ds.id) as total_order_kg
        FROM pilot_daily_sessions ds JOIN pilot_users u ON ds.chef_id=u.id LEFT JOIN pilot_kitchens k ON ds.kitchen_id=k.id WHERE ds.kitchen_id=? ORDER BY ds.session_date DESC LIMIT 30");
    $sessions->execute([$hk]); $sessions = $sessions->fetchAll();
?>
    <h4 class="mb-3">Session History</h4>
    <form class="row g-2 mb-3 no-print" method="GET"><input type="hidden" name="page" value="history">
        <div class="col-auto"><select name="hk" class="form-select"><?php foreach($allKitchens as $k): ?><option value="<?=$k['id']?>" <?=$hk==$k['id']?'selected':''?>><?=htmlspecialchars($k['name'])?></option><?php endforeach; ?></select></div>
        <div class="col-auto"><button class="btn btn-primary">Filter</button></div></form>
    <div class="table-responsive">
    <table class="table table-striped"><thead><tr><th>Date</th><th>Kitchen</th><th>Chef</th><th>Bed Nights</th><th>Items</th><th>Order (kg)</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    <?php foreach($sessions as $s): $sl=['open'=>'<span class="badge bg-primary">Open</span>','requisition_sent'=>'<span class="badge bg-warning text-dark">Req Sent</span>','supplied'=>'<span class="badge bg-success">Supplied</span>','day_closed'=>'<span class="badge bg-secondary">Closed</span>']; ?>
    <tr><td><?=date('M j, Y',strtotime($s['session_date']))?></td><td><?=htmlspecialchars($s['kitchen_name']??'')?></td><td><?=htmlspecialchars($s['chef_name'])?></td><td><?=$s['guest_count']?></td><td><?=$s['item_count']?></td><td><?=number_format($s['total_order_kg'],1)?></td><td><?=$sl[$s['status']]??$s['status']?></td><td><a href="?page=reports&date=<?=$s['session_date']?>&rk=<?=$s['kitchen_id']?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Report</a></td></tr>
    <?php endforeach; ?></tbody></table></div>
<?php }

// ============================================================
// MANAGE KITCHENS (Admin) — No changes needed
// ============================================================
function include_manage_kitchens($db) {
    if (getUserRole() !== 'admin') { echo '<div class="alert alert-danger">Access denied.</div>'; return; }
    $kitchens = $db->query("SELECT k.*, (SELECT COUNT(*) FROM pilot_users WHERE kitchen_id=k.id) as user_count FROM pilot_kitchens k ORDER BY k.name")->fetchAll();
?>
    <h4 class="mb-3">Manage Kitchens</h4>
    <div class="card mb-4"><div class="card-header">Add New Kitchen</div><div class="card-body">
        <form id="addKitchenForm" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label">Kitchen Name</label><input type="text" name="name" class="form-control" required placeholder="e.g. Pool Bar Kitchen"></div>
            <div class="col-md-2"><label class="form-label">Code</label><input type="text" name="code" class="form-control" required placeholder="e.g. POOL" maxlength="20"></div>
            <div class="col-md-3"><label class="form-label">Location</label><input type="text" name="location" class="form-control" placeholder="e.g. Pool Area"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus"></i> Add Kitchen</button></div>
        </form>
    </div></div>
    <div class="table-responsive">
    <table class="table table-striped"><thead><tr><th>Name</th><th>Code</th><th>Location</th><th>Users</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    <?php foreach($kitchens as $k): ?>
    <tr><td><strong><?=htmlspecialchars($k['name'])?></strong></td><td><code><?=htmlspecialchars($k['code'])?></code></td><td><?=htmlspecialchars($k['location']??'')?></td><td><?=$k['user_count']?></td>
    <td><?=$k['is_active']?'<span class="badge bg-success">Active</span>':'<span class="badge bg-secondary">Inactive</span>'?></td>
    <td><button class="btn btn-sm btn-outline-<?=$k['is_active']?'warning':'success'?>" onclick="toggleKitchen(<?=$k['id']?>,<?=$k['is_active']?0:1?>)"><?=$k['is_active']?'Deactivate':'Activate'?></button></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <script>
    $('#addKitchenForm').on('submit',function(e){ e.preventDefault();
        var f = $('#addKitchenForm');
        $.post('api.php',{action:'add_kitchen', name:f.find('[name=name]').val(), code:f.find('[name=code]').val(), location:f.find('[name=location]').val()},function(res){ if(res.success) location.reload(); else alert(res.message||'Error'); },'json');
    });
    function toggleKitchen(id,active){ $.post('api.php',{action:'toggle_kitchen',kitchen_id:id,is_active:active},function(res){ if(res.success) location.reload(); else alert(res.message); },'json'); }
    </script>
<?php }

// ============================================================
// MANAGE USERS (Admin) — No changes needed
// ============================================================
function include_manage_users($db) {
    if (getUserRole() !== 'admin') { echo '<div class="alert alert-danger">Access denied.</div>'; return; }
    $users = $db->query("SELECT u.*, k.name as kitchen_name FROM pilot_users u LEFT JOIN pilot_kitchens k ON u.kitchen_id=k.id ORDER BY u.role, u.name")->fetchAll();
    $kitchens = $db->query("SELECT * FROM pilot_kitchens WHERE is_active=1 ORDER BY name")->fetchAll();
?>
    <h4 class="mb-3">Manage Users</h4>
    <div class="card mb-4"><div class="card-header">Add New User</div><div class="card-body">
        <form id="addUserForm" class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Full Name</label><input type="text" name="fullname" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Role</label><select name="role" class="form-select"><option value="chef">Chef</option><option value="store">Store</option><option value="admin">Admin</option></select></div>
            <div class="col-md-2"><label class="form-label">Kitchen</label><select name="kitchen_id" class="form-select"><option value="">-- None --</option><?php foreach($kitchens as $k): ?><option value="<?=$k['id']?>"><?=htmlspecialchars($k['name'])?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus"></i> Add User</button></div>
        </form>
    </div></div>
    <div class="table-responsive">
    <table class="table table-striped"><thead><tr><th>Username</th><th>Name</th><th>Role</th><th>Kitchen</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    <?php foreach($users as $u): ?>
    <tr><td><code><?=htmlspecialchars($u['username'])?></code></td><td><?=htmlspecialchars($u['name'])?></td><td><span class="badge bg-<?=$u['role']==='admin'?'danger':($u['role']==='chef'?'primary':'success')?>"><?=ucfirst($u['role'])?></span></td>
    <td>
        <select class="form-select form-select-sm" onchange="assignKitchen(<?=$u['id']?>,this.value)" style="width:auto;display:inline-block">
            <option value="">-- None --</option>
            <?php foreach($kitchens as $k): ?><option value="<?=$k['id']?>" <?=$u['kitchen_id']==$k['id']?'selected':''?>><?=htmlspecialchars($k['name'])?></option><?php endforeach; ?>
        </select>
    </td>
    <td><?=$u['is_active']?'<span class="badge bg-success">Active</span>':'<span class="badge bg-secondary">Inactive</span>'?></td>
    <td><button class="btn btn-sm btn-outline-<?=$u['is_active']?'warning':'success'?>" onclick="toggleUser(<?=$u['id']?>,<?=$u['is_active']?0:1?>)"><?=$u['is_active']?'Deactivate':'Activate'?></button></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <script>
    $('#addUserForm').on('submit',function(e){ e.preventDefault();
        var f = $('#addUserForm');
        $.post('api.php',{action:'add_user', username:f.find('[name=username]').val(), password:f.find('[name=password]').val(), fullname:f.find('[name=fullname]').val(), role:f.find('[name=role]').val(), kitchen_id:f.find('[name=kitchen_id]').val()},function(res){ if(res.success) location.reload(); else alert(res.message||'Error'); },'json');
    });
    function toggleUser(id,active){ $.post('api.php',{action:'toggle_user',user_id:id,is_active:active},function(res){ if(res.success) location.reload(); else alert(res.message); },'json'); }
    function assignKitchen(userId,kitchenId){ $.post('api.php',{action:'assign_kitchen',user_id:userId,kitchen_id:kitchenId},function(res){ if(!res.success) alert(res.message||'Error'); },'json'); }
    </script>
<?php }

// ============================================================
// MANAGE ITEMS (Updated for KG workflow)
// ============================================================
function include_manage_items($db) {
    if (getUserRole() !== 'admin') { echo '<div class="alert alert-danger">Access denied.</div>'; return; }
    $items = $db->query("SELECT * FROM pilot_items ORDER BY category, name")->fetchAll();
    $categories = []; foreach($items as $item) $categories[$item['category']][] = $item;
    $totalItems = count($items);
    $activeItems = count(array_filter($items, fn($i) => $i['is_active']));
?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Manage Items</h4>
        <span class="text-muted"><?=$activeItems?> active / <?=$totalItems?> total</span>
    </div>

    <!-- Add New Item Card -->
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-plus-circle"></i> Add New Item</div>
        <div class="card-body">
            <form id="addItemForm">
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-semibold">Item Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Cow Meat, Chicken Breast">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                        <input type="text" name="category" class="form-control" list="catList" required placeholder="e.g. Meat">
                        <datalist id="catList"><?php foreach(array_keys($categories) as $cat): ?><option value="<?=htmlspecialchars($cat)?>"><?php endforeach; ?></datalist>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label fw-semibold">Portion Weight <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="portion_grams" class="form-control" step="1" min="1" value="300" required>
                            <span class="input-group-text">grams</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted">
                        <i class="bi bi-calculator"></i>
                        <span id="kgPreview" class="fw-semibold text-primary">0.300 kg</span> per portion &middot;
                        <span id="perKgPreview" class="fw-semibold text-primary">3.33</span> portions per kg
                    </div>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-plus-lg"></i> Add Item</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Item List by Category -->
    <?php if (empty($categories)): ?>
        <div class="alert alert-info"><i class="bi bi-info-circle"></i> No items yet. Add your first item above.</div>
    <?php endif; ?>
    <?php foreach($categories as $cat=>$catItems): ?>
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between py-2" style="background:#e8ecf1;color:var(--primary);">
            <span><i class="bi bi-tag"></i> <?=htmlspecialchars($cat)?></span>
            <span class="badge bg-primary rounded-pill"><?=count($catItems)?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th style="width:35%">Item</th><th class="text-center">Portion</th><th class="text-center">Per KG</th><th class="text-center">Status</th><th class="text-end">Action</th></tr></thead>
                <tbody>
                <?php foreach($catItems as $it):
                    $pwkg = $it['portion_weight_kg'] > 0 ? $it['portion_weight_kg'] : ($it['unit_weight'] > 0 ? $it['unit_weight']/1000/$it['portions_per_unit'] : 0.300);
                    $perKg = $pwkg > 0 ? round(1/$pwkg, 2) : 0;
                ?>
                <tr class="<?=$it['is_active']?'':'table-secondary'?>">
                    <td class="fw-medium"><?=htmlspecialchars($it['name'])?></td>
                    <td class="text-center"><?=number_format($pwkg,3)?> <small class="text-muted">kg</small></td>
                    <td class="text-center"><?=$perKg?></td>
                    <td class="text-center"><?=$it['is_active']?'<span class="badge bg-success">Active</span>':'<span class="badge bg-secondary">Inactive</span>'?></td>
                    <td class="text-end"><button class="btn btn-sm btn-outline-<?=$it['is_active']?'warning':'success'?>" onclick="toggleItem(<?=$it['id']?>,<?=$it['is_active']?0:1?>)"><i class="bi bi-<?=$it['is_active']?'pause':'play'?>-fill"></i> <?=$it['is_active']?'Disable':'Enable'?></button></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

    <script>
    $('[name=portion_grams]').on('input', function(){
        const g = parseFloat($(this).val()) || 0;
        const kg = g / 1000;
        const perKg = kg > 0 ? (1/kg).toFixed(2) : 0;
        $('#kgPreview').text(kg.toFixed(3) + ' kg');
        $('#perKgPreview').text(perKg);
    });
    $('#addItemForm').on('submit',function(e){ e.preventDefault();
        var f = $('#addItemForm');
        var name = f.find('[name=name]').val().trim();
        var cat = f.find('[name=category]').val().trim();
        if (!name || !cat) { alert('Please fill item name and category.'); return; }
        $.post('api.php',{action:'add_item', name:name, category:cat, portion_grams:f.find('[name=portion_grams]').val()},function(res){
            if(res.success) location.reload(); else alert(res.message||'Error');
        },'json');
    });
    function toggleItem(id,active){ $.post('api.php',{action:'toggle_item',item_id:id,is_active:active},function(res){ if(res.success) location.reload(); else alert(res.message); },'json'); }
    </script>
<?php }
?>
