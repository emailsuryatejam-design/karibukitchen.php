<?php
require_once 'config.php';
requireLogin();

$db = getDB();
$role = getUserRole();
$page = $_GET['page'] ?? ($role === 'store' ? 'store_dashboard' : 'chef_dashboard');
$today = date('Y-m-d');

// Get today's session if exists
$stmt = $db->prepare("SELECT * FROM pilot_daily_sessions WHERE session_date = ?");
$stmt->execute([$today]);
$todaySession = $stmt->fetch();

// Check if chef needs guest count popup
$showGuestPopup = ($role === 'chef' && !$todaySession && $page !== 'reports' && $page !== 'history');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pantry Planner - Pilot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --primary: #0f3460; --accent: #e94560; --dark: #1a1a2e; --light-bg: #f4f6f9; }
        body { background: var(--light-bg); font-family: 'Segoe UI', system-ui, sans-serif; }
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
        .carryover-badge { background: #d4edda; color: #155724; font-size: 0.8rem; padding: 2px 8px; border-radius: 10px; }
        .shortage { color: var(--accent); font-weight: 600; }
        .surplus { color: #28a745; font-weight: 600; }

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
        <span class="navbar-brand">
            <i class="bi bi-clipboard2-check"></i> Pantry Planner <span class="badge-pilot">PILOT</span>
        </span>
        <div class="d-flex align-items-center gap-3">
            <span class="text-light">
                <i class="bi bi-person-circle"></i> <?= htmlspecialchars(getUserName()) ?>
                <span class="badge bg-light text-dark"><?= ucfirst($role) ?></span>
            </span>
            <span class="text-light opacity-75"><i class="bi bi-calendar3"></i> <?= date('D, M j, Y') ?></span>
            <a href="logout.php" class="btn btn-outline-light btn-sm"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar no-print p-0">
                <nav class="nav flex-column">
                    <?php if ($role === 'chef' || $role === 'admin'): ?>
                        <a class="nav-link <?= $page === 'chef_dashboard' ? 'active' : '' ?>" href="?page=chef_dashboard">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                        <a class="nav-link <?= $page === 'requisition' ? 'active' : '' ?>" href="?page=requisition">
                            <i class="bi bi-cart-plus"></i> Requisition
                        </a>
                        <a class="nav-link <?= $page === 'review_supply' ? 'active' : '' ?>" href="?page=review_supply">
                            <i class="bi bi-clipboard-check"></i> Review Supply
                        </a>
                        <a class="nav-link <?= $page === 'day_close' ? 'active' : '' ?>" href="?page=day_close">
                            <i class="bi bi-moon-stars"></i> Day Close
                        </a>
                    <?php endif; ?>
                    <?php if ($role === 'store' || $role === 'admin'): ?>
                        <a class="nav-link <?= $page === 'store_dashboard' ? 'active' : '' ?>" href="?page=store_dashboard">
                            <i class="bi bi-shop"></i> Store Dashboard
                        </a>
                        <a class="nav-link <?= $page === 'supply' ? 'active' : '' ?>" href="?page=supply">
                            <i class="bi bi-truck"></i> Supply Items
                        </a>
                    <?php endif; ?>
                    <hr class="mx-3">
                    <a class="nav-link <?= $page === 'reports' ? 'active' : '' ?>" href="?page=reports">
                        <i class="bi bi-file-earmark-bar-graph"></i> Reports
                    </a>
                    <a class="nav-link <?= $page === 'history' ? 'active' : '' ?>" href="?page=history">
                        <i class="bi bi-clock-history"></i> History
                    </a>
                    <?php if ($role === 'admin'): ?>
                        <a class="nav-link <?= $page === 'manage_items' ? 'active' : '' ?>" href="?page=manage_items">
                            <i class="bi bi-gear"></i> Manage Items
                        </a>
                    <?php endif; ?>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <?php
                switch ($page) {
                    case 'chef_dashboard':
                        include_chef_dashboard($db, $todaySession, $today);
                        break;
                    case 'requisition':
                        include_requisition($db, $todaySession, $today);
                        break;
                    case 'review_supply':
                        include_review_supply($db, $todaySession);
                        break;
                    case 'day_close':
                        include_day_close($db, $todaySession);
                        break;
                    case 'store_dashboard':
                        include_store_dashboard($db, $today);
                        break;
                    case 'supply':
                        include_supply($db, $today);
                        break;
                    case 'reports':
                        include_reports($db);
                        break;
                    case 'history':
                        include_history($db);
                        break;
                    case 'manage_items':
                        include_manage_items($db);
                        break;
                    default:
                        echo '<div class="alert alert-warning">Page not found.</div>';
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
                    <p class="mb-3 fs-5">How many guests are expected today?</p>
                    <p class="text-muted mb-3"><?= date('l, F j, Y') ?></p>
                    <input type="number" id="guestCount" class="form-control form-control-lg text-center mx-auto" style="max-width:200px" min="1" max="9999" placeholder="0" autofocus>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-primary btn-lg px-5" onclick="submitGuestCount()">
                        <i class="bi bi-check-lg"></i> Start Day
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
    <script>
    <?php if ($showGuestPopup): ?>
    $(document).ready(function() {
        new bootstrap.Modal('#guestModal').show();
        $('#guestCount').focus();
    });

    function submitGuestCount() {
        const count = parseInt($('#guestCount').val());
        if (!count || count < 1) {
            alert('Please enter a valid number of guests.');
            return;
        }
        $.post('api.php', { action: 'create_session', guest_count: count }, function(res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.message || 'Error creating session');
            }
        }, 'json');
    }

    $('#guestCount').on('keypress', function(e) {
        if (e.which === 13) submitGuestCount();
    });
    <?php endif; ?>

    function printSection(elementId) {
        const content = document.getElementById(elementId).innerHTML;
        const win = window.open('', '_blank');
        win.document.write(`
            <html><head><title>Print</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>body{padding:20px;font-family:'Segoe UI',system-ui,sans-serif;} .no-print{display:none!important;} @media print{.no-print{display:none!important;}}</style>
            </head><body>${content}</body></html>
        `);
        win.document.close();
        setTimeout(() => { win.print(); }, 500);
    }

    function downloadCSV(tableId, filename) {
        const table = document.getElementById(tableId);
        if (!table) return;
        let csv = [];
        const rows = table.querySelectorAll('tr');
        rows.forEach(row => {
            const cols = row.querySelectorAll('td, th');
            let rowData = [];
            cols.forEach(col => {
                let text = col.innerText.replace(/"/g, '""');
                // Skip action columns
                if (!col.classList.contains('no-print')) {
                    rowData.push('"' + text + '"');
                }
            });
            csv.push(rowData.join(','));
        });
        const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename + '.csv';
        a.click();
    }
    </script>

    <!-- Footer -->
    <footer class="text-center py-3 no-print" style="background:#1a1a2e; color:rgba(255,255,255,0.5); font-size:0.8rem;">
        Powered by <strong style="color:rgba(255,255,255,0.7);">VyomaAI Studios</strong>
    </footer>
</body>
</html>

<?php
// ============================================================
// CHEF DASHBOARD
// ============================================================
function include_chef_dashboard($db, $session, $today) {
    if (!$session) {
        echo '<div class="alert alert-info"><i class="bi bi-info-circle"></i> Set the guest count to begin today\'s session.</div>';
        return;
    }

    // Get stats
    $reqCount = $db->prepare("SELECT COUNT(*) FROM pilot_requisitions WHERE session_id = ?");
    $reqCount->execute([$session['id']]);
    $totalReqItems = $reqCount->fetchColumn();

    $supCount = $db->prepare("SELECT COUNT(*) FROM pilot_store_supplies ss JOIN pilot_requisitions r ON ss.requisition_id = r.id WHERE r.session_id = ?");
    $supCount->execute([$session['id']]);
    $totalSupplied = $supCount->fetchColumn();

    $statusLabels = [
        'open' => ['Open', 'bg-primary'],
        'requisition_sent' => ['Requisition Sent', 'bg-warning text-dark'],
        'supplied' => ['Supplied', 'bg-success'],
        'day_closed' => ['Day Closed', 'bg-secondary']
    ];
    $statusInfo = $statusLabels[$session['status']] ?? ['Unknown', 'bg-dark'];
?>
    <h4 class="mb-4">Chef Dashboard</h4>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-number"><?= $session['guest_count'] ?></div>
                <div class="stat-label">Guests Today</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-number"><?= $totalReqItems ?></div>
                <div class="stat-label">Items Requested</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-number"><?= $totalSupplied ?></div>
                <div class="stat-label">Items Supplied</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <span class="badge <?= $statusInfo[1] ?> status-badge fs-6"><?= $statusInfo[0] ?></span>
                <div class="stat-label mt-2">Session Status</div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">Quick Actions</div>
        <div class="card-body">
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($session['status'] === 'open'): ?>
                    <a href="?page=requisition" class="btn btn-primary"><i class="bi bi-cart-plus"></i> Create Requisition</a>
                <?php endif; ?>
                <?php if ($session['status'] === 'supplied'): ?>
                    <a href="?page=review_supply" class="btn btn-primary"><i class="bi bi-clipboard-check"></i> Review Supply</a>
                    <a href="?page=day_close" class="btn btn-accent"><i class="bi bi-moon-stars"></i> Day Close</a>
                <?php endif; ?>
                <?php if ($session['status'] === 'requisition_sent'): ?>
                    <span class="btn btn-outline-secondary disabled"><i class="bi bi-hourglass-split"></i> Waiting for Store...</span>
                <?php endif; ?>
                <a href="?page=reports" class="btn btn-outline-primary"><i class="bi bi-file-earmark-bar-graph"></i> View Reports</a>
            </div>
        </div>
    </div>
<?php
}

// ============================================================
// REQUISITION PAGE
// ============================================================
function include_requisition($db, $session, $today) {
    if (!$session) {
        echo '<div class="alert alert-warning">Please set the guest count first.</div>';
        return;
    }
    if ($session['status'] !== 'open') {
        // Show existing requisition
        $stmt = $db->prepare("
            SELECT r.*, i.name, i.category, i.unit_weight, i.weight_unit, i.portions_per_unit
            FROM pilot_requisitions r
            JOIN pilot_items i ON r.item_id = i.id
            WHERE r.session_id = ?
            ORDER BY i.category, i.name
        ");
        $stmt->execute([$session['id']]);
        $items = $stmt->fetchAll();

        echo '<h4 class="mb-3">Today\'s Requisition <span class="badge bg-info">Already Submitted</span></h4>';
        echo '<div id="printRequisition">';
        echo '<h5 class="mb-2 d-none d-print-block">Requisition - ' . date('D, M j, Y') . ' | Guests: ' . $session['guest_count'] . '</h5>';
        echo '<table class="table table-striped" id="reqTable"><thead><tr><th>#</th><th>Item</th><th>Category</th><th>Weight/Unit</th><th>Carryover</th><th>Requested</th><th>Notes</th></tr></thead><tbody>';
        $n = 1;
        foreach ($items as $it) {
            echo '<tr><td>' . $n++ . '</td><td>' . htmlspecialchars($it['name']) . '</td><td>' . htmlspecialchars($it['category']) . '</td>';
            echo '<td>' . $it['unit_weight'] . $it['weight_unit'] . ' (' . $it['portions_per_unit'] . ' portions)</td>';
            echo '<td>' . ($it['carryover_portions'] > 0 ? '<span class="carryover-badge">' . $it['carryover_portions'] . ' in stock</span>' : '-') . '</td>';
            echo '<td><strong>' . $it['portions_requested'] . '</strong></td>';
            echo '<td>' . htmlspecialchars($it['notes'] ?? '') . '</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<div class="no-print"><button class="btn btn-outline-primary me-2" onclick="printSection(\'printRequisition\')"><i class="bi bi-printer"></i> Print</button>';
        echo '<button class="btn btn-outline-success" onclick="downloadCSV(\'reqTable\',\'requisition_' . $today . '\')"><i class="bi bi-download"></i> Download CSV</button></div>';
        return;
    }

    // Get all active items with kitchen stock
    $items = $db->query("
        SELECT i.*, COALESCE(ks.portions_available, 0) as stock_portions
        FROM pilot_items i
        LEFT JOIN pilot_kitchen_stock ks ON i.id = ks.item_id
        WHERE i.is_active = 1
        ORDER BY i.category, i.name
    ")->fetchAll();

    // Group by category
    $categories = [];
    foreach ($items as $item) {
        $categories[$item['category']][] = $item;
    }
?>
    <h4 class="mb-3">Create Requisition <small class="text-muted">| Guests: <?= $session['guest_count'] ?></small></h4>

    <form id="requisitionForm">
        <input type="hidden" name="action" value="submit_requisition">
        <input type="hidden" name="session_id" value="<?= $session['id'] ?>">

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Select Items & Portions</span>
                <div>
                    <input type="text" id="itemSearch" class="form-control form-control-sm" placeholder="Search items..." style="width:200px;display:inline-block">
                </div>
            </div>
            <div class="card-body p-0">
                <?php foreach ($categories as $cat => $catItems): ?>
                    <div class="category-header"><?= htmlspecialchars($cat) ?></div>
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th style="width:40px"></th>
                                <th>Item</th>
                                <th>Unit</th>
                                <th>Portions/Unit</th>
                                <th>In Stock</th>
                                <th style="width:120px">Portions Needed</th>
                                <th style="width:200px">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($catItems as $item): ?>
                            <tr class="item-row" data-name="<?= strtolower($item['name']) ?>">
                                <td><input type="checkbox" class="form-check-input item-check" data-id="<?= $item['id'] ?>"></td>
                                <td><?= htmlspecialchars($item['name']) ?></td>
                                <td><?= $item['unit_weight'] . $item['weight_unit'] ?></td>
                                <td><?= $item['portions_per_unit'] ?></td>
                                <td>
                                    <?php if ($item['stock_portions'] > 0): ?>
                                        <span class="carryover-badge"><i class="bi bi-box-seam"></i> <?= $item['stock_portions'] ?> portions</span>
                                    <?php else: ?>
                                        <span class="text-muted">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <input type="number" name="portions[<?= $item['id'] ?>]" class="form-control form-control-sm portions-input" min="0" value="0" disabled>
                                    <input type="hidden" name="carryover[<?= $item['id'] ?>]" value="<?= $item['stock_portions'] ?>">
                                </td>
                                <td>
                                    <input type="text" name="notes[<?= $item['id'] ?>]" class="form-control form-control-sm" placeholder="Optional" disabled>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-send"></i> Submit Requisition to Store</button>
            <button type="button" class="btn btn-outline-secondary" onclick="location.reload()"><i class="bi bi-arrow-clockwise"></i> Reset</button>
        </div>
    </form>

    <script>
    $(document).ready(function() {
        // Toggle inputs when checkbox is checked
        $('.item-check').on('change', function() {
            const row = $(this).closest('tr');
            const enabled = $(this).is(':checked');
            row.find('.portions-input, input[type=text]').prop('disabled', !enabled);
            if (enabled) row.find('.portions-input').focus();
            else row.find('.portions-input').val(0);
        });

        // Search filter
        $('#itemSearch').on('input', function() {
            const q = $(this).val().toLowerCase();
            $('.item-row').each(function() {
                $(this).toggle($(this).data('name').includes(q));
            });
        });

        // Submit form
        $('#requisitionForm').on('submit', function(e) {
            e.preventDefault();
            const checked = $('.item-check:checked');
            if (checked.length === 0) {
                alert('Please select at least one item.');
                return;
            }
            // Build data
            let items = [];
            checked.each(function() {
                const id = $(this).data('id');
                const row = $(this).closest('tr');
                const portions = parseInt(row.find('.portions-input').val()) || 0;
                const notes = row.find('input[type=text]').val() || '';
                const carryover = parseInt($('input[name="carryover[' + id + ']"]').val()) || 0;
                if (portions > 0) {
                    items.push({ item_id: id, portions: portions, notes: notes, carryover: carryover });
                }
            });
            if (items.length === 0) {
                alert('Please enter portions for at least one item.');
                return;
            }
            if (!confirm('Submit this requisition to the store? (' + items.length + ' items)')) return;

            $.post('api.php', {
                action: 'submit_requisition',
                session_id: <?= $session['id'] ?>,
                items: JSON.stringify(items)
            }, function(res) {
                if (res.success) {
                    alert('Requisition submitted successfully!');
                    location.href = '?page=chef_dashboard';
                } else {
                    alert(res.message || 'Error submitting');
                }
            }, 'json');
        });
    });
    </script>
<?php
}

// ============================================================
// REVIEW SUPPLY (Chef sees what store supplied)
// ============================================================
function include_review_supply($db, $session) {
    if (!$session) {
        echo '<div class="alert alert-warning">No session for today.</div>';
        return;
    }

    $stmt = $db->prepare("
        SELECT r.*, i.name, i.category, i.unit_weight, i.weight_unit, i.portions_per_unit,
               COALESCE(ss.portions_supplied, 0) as supplied, ss.notes as supply_notes
        FROM pilot_requisitions r
        JOIN pilot_items i ON r.item_id = i.id
        LEFT JOIN pilot_store_supplies ss ON ss.requisition_id = r.id
        WHERE r.session_id = ?
        ORDER BY i.category, i.name
    ");
    $stmt->execute([$session['id']]);
    $items = $stmt->fetchAll();
?>
    <h4 class="mb-3">Review Supply vs Requisition</h4>

    <div id="printSupplyReview">
        <h6 class="d-none d-print-block">Supply Review - <?= date('D, M j, Y') ?> | Guests: <?= $session['guest_count'] ?></h6>
        <table class="table table-striped" id="supplyReviewTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Carryover</th>
                    <th>Requested</th>
                    <th>Supplied</th>
                    <th>Difference</th>
                    <th>Store Notes</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $n = 1;
            foreach ($items as $it):
                $diff = $it['supplied'] - $it['portions_requested'];
                $diffClass = $diff < 0 ? 'shortage' : ($diff > 0 ? 'surplus' : '');
                $diffText = $diff > 0 ? '+' . $diff : ($diff < 0 ? $diff : '-');
            ?>
                <tr>
                    <td><?= $n++ ?></td>
                    <td><?= htmlspecialchars($it['name']) ?></td>
                    <td><?= htmlspecialchars($it['category']) ?></td>
                    <td><?= $it['carryover_portions'] > 0 ? $it['carryover_portions'] : '-' ?></td>
                    <td><strong><?= $it['portions_requested'] ?></strong></td>
                    <td><strong><?= $it['supplied'] ?></strong></td>
                    <td class="<?= $diffClass ?>"><?= $diffText ?></td>
                    <td><?= htmlspecialchars($it['supply_notes'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="no-print d-flex gap-2">
        <button class="btn btn-outline-primary" onclick="printSection('printSupplyReview')"><i class="bi bi-printer"></i> Print</button>
        <button class="btn btn-outline-success" onclick="downloadCSV('supplyReviewTable','supply_review_<?= date('Y-m-d') ?>')"><i class="bi bi-download"></i> Download CSV</button>
    </div>
<?php
}

// ============================================================
// DAY CLOSE (Chef marks remaining portions)
// ============================================================
function include_day_close($db, $session) {
    if (!$session) {
        echo '<div class="alert alert-warning">No session for today.</div>';
        return;
    }

    if ($session['status'] === 'day_closed') {
        // Show day close report
        $stmt = $db->prepare("
            SELECT dc.*, i.name, i.category
            FROM pilot_day_close dc
            JOIN pilot_items i ON dc.item_id = i.id
            WHERE dc.session_id = ?
            ORDER BY i.category, i.name
        ");
        $stmt->execute([$session['id']]);
        $items = $stmt->fetchAll();

        echo '<h4 class="mb-3">Day Close Report <span class="badge bg-secondary">Closed</span></h4>';
        echo '<div id="printDayClose">';
        echo '<h6 class="d-none d-print-block">Day Close Report - ' . date('D, M j, Y') . ' | Guests: ' . $session['guest_count'] . '</h6>';
        echo '<table class="table table-striped" id="dayCloseTable"><thead><tr><th>#</th><th>Item</th><th>Category</th><th>Total Portions</th><th>Consumed</th><th>Remaining</th></tr></thead><tbody>';
        $n = 1;
        foreach ($items as $it) {
            echo '<tr><td>' . $n++ . '</td><td>' . htmlspecialchars($it['name']) . '</td><td>' . htmlspecialchars($it['category']) . '</td>';
            echo '<td>' . $it['portions_total'] . '</td><td>' . $it['portions_consumed'] . '</td>';
            echo '<td><strong>' . $it['portions_remaining'] . '</strong></td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<div class="no-print d-flex gap-2">';
        echo '<button class="btn btn-outline-primary" onclick="printSection(\'printDayClose\')"><i class="bi bi-printer"></i> Print</button>';
        echo '<button class="btn btn-outline-success" onclick="downloadCSV(\'dayCloseTable\',\'day_close_' . date('Y-m-d') . '\')"><i class="bi bi-download"></i> Download CSV</button>';
        echo '</div>';
        return;
    }

    if ($session['status'] !== 'supplied') {
        echo '<div class="alert alert-info">Day close is available after the store has supplied items.</div>';
        return;
    }

    // Get items with total available (carryover + supplied)
    $stmt = $db->prepare("
        SELECT r.*, i.name, i.category,
               COALESCE(ss.portions_supplied, 0) as supplied
        FROM pilot_requisitions r
        JOIN pilot_items i ON r.item_id = i.id
        LEFT JOIN pilot_store_supplies ss ON ss.requisition_id = r.id
        WHERE r.session_id = ?
        ORDER BY i.category, i.name
    ");
    $stmt->execute([$session['id']]);
    $items = $stmt->fetchAll();
?>
    <h4 class="mb-3">Day Close - Mark Remaining Portions</h4>
    <p class="text-muted">Enter how many portions of each item remain unconsumed at end of day.</p>

    <form id="dayCloseForm">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>Carryover</th>
                    <th>Supplied</th>
                    <th>Total Available</th>
                    <th style="width:150px">Remaining (Unconsumed)</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $n = 1;
            foreach ($items as $it):
                $total = $it['carryover_portions'] + $it['supplied'];
            ?>
                <tr>
                    <td><?= $n++ ?></td>
                    <td><?= htmlspecialchars($it['name']) ?></td>
                    <td><?= $it['carryover_portions'] ?></td>
                    <td><?= $it['supplied'] ?></td>
                    <td><strong><?= $total ?></strong></td>
                    <td>
                        <input type="number" name="remaining[<?= $it['item_id'] ?>]" class="form-control form-control-sm"
                               min="0" max="<?= $total ?>" value="0" data-total="<?= $total ?>">
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <button type="submit" class="btn btn-accent btn-lg"><i class="bi bi-moon-stars"></i> Close Day</button>
    </form>

    <script>
    $('#dayCloseForm').on('submit', function(e) {
        e.preventDefault();
        if (!confirm('Are you sure you want to close the day? This will update kitchen stock for tomorrow.')) return;

        let items = [];
        $('input[name^="remaining"]').each(function() {
            const id = $(this).attr('name').match(/\d+/)[0];
            const remaining = parseInt($(this).val()) || 0;
            const total = parseInt($(this).data('total')) || 0;
            items.push({ item_id: id, remaining: remaining, total: total });
        });

        $.post('api.php', {
            action: 'day_close',
            session_id: <?= $session['id'] ?>,
            items: JSON.stringify(items)
        }, function(res) {
            if (res.success) {
                alert('Day closed successfully! Kitchen stock updated.');
                location.href = '?page=day_close';
            } else {
                alert(res.message || 'Error closing day');
            }
        }, 'json');
    });
    </script>
<?php
}

// ============================================================
// STORE DASHBOARD
// ============================================================
function include_store_dashboard($db, $today) {
    // Get today's session
    $stmt = $db->prepare("
        SELECT ds.*, u.name as chef_name
        FROM pilot_daily_sessions ds
        JOIN pilot_users u ON ds.chef_id = u.id
        WHERE ds.session_date = ?
    ");
    $stmt->execute([$today]);
    $session = $stmt->fetch();

    if (!$session) {
        echo '<div class="alert alert-info"><i class="bi bi-info-circle"></i> No requisition from kitchen yet today.</div>';
        return;
    }

    $reqCount = $db->prepare("SELECT COUNT(*) FROM pilot_requisitions WHERE session_id = ?");
    $reqCount->execute([$session['id']]);
    $totalItems = $reqCount->fetchColumn();

    $totalPortions = $db->prepare("SELECT COALESCE(SUM(portions_requested),0) FROM pilot_requisitions WHERE session_id = ?");
    $totalPortions->execute([$session['id']]);
    $portionsNeeded = $totalPortions->fetchColumn();

    $statusLabels = [
        'open' => ['Kitchen Preparing', 'bg-secondary'],
        'requisition_sent' => ['Pending Supply', 'bg-warning text-dark'],
        'supplied' => ['Supplied', 'bg-success'],
        'day_closed' => ['Day Closed', 'bg-secondary']
    ];
    $statusInfo = $statusLabels[$session['status']] ?? ['Unknown', 'bg-dark'];
?>
    <h4 class="mb-4">Store Dashboard</h4>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-number"><?= $session['guest_count'] ?></div>
                <div class="stat-label">Guests Expected</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-number"><?= $totalItems ?></div>
                <div class="stat-label">Items Requested</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="stat-number"><?= $portionsNeeded ?></div>
                <div class="stat-label">Total Portions</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <span class="badge <?= $statusInfo[1] ?> status-badge fs-6"><?= $statusInfo[0] ?></span>
                <div class="stat-label mt-2">Status</div>
            </div>
        </div>
    </div>

    <?php if ($session['status'] === 'requisition_sent'): ?>
        <a href="?page=supply" class="btn btn-primary btn-lg"><i class="bi bi-truck"></i> Supply Items Now</a>
    <?php elseif ($session['status'] === 'supplied'): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle"></i> Items supplied. Waiting for kitchen day close.</div>
    <?php endif; ?>
<?php
}

// ============================================================
// SUPPLY PAGE (Store marks what they supply)
// ============================================================
function include_supply($db, $today) {
    $stmt = $db->prepare("SELECT * FROM pilot_daily_sessions WHERE session_date = ?");
    $stmt->execute([$today]);
    $session = $stmt->fetch();

    if (!$session || $session['status'] === 'open') {
        echo '<div class="alert alert-info">No requisition from kitchen yet.</div>';
        return;
    }

    if ($session['status'] === 'supplied' || $session['status'] === 'day_closed') {
        // Show what was supplied
        $stmt = $db->prepare("
            SELECT r.*, i.name, i.category, i.unit_weight, i.weight_unit,
                   COALESCE(ss.portions_supplied, 0) as supplied, ss.notes as supply_notes
            FROM pilot_requisitions r
            JOIN pilot_items i ON r.item_id = i.id
            LEFT JOIN pilot_store_supplies ss ON ss.requisition_id = r.id
            WHERE r.session_id = ?
            ORDER BY i.category, i.name
        ");
        $stmt->execute([$session['id']]);
        $items = $stmt->fetchAll();

        echo '<h4 class="mb-3">Supply Log <span class="badge bg-success">Completed</span></h4>';
        echo '<div id="printStoreLog">';
        echo '<h6 class="d-none d-print-block">Store Supply Log - ' . date('D, M j, Y') . '</h6>';
        echo '<table class="table table-striped" id="storeLogTable"><thead><tr><th>#</th><th>Item</th><th>Requested</th><th>Supplied</th><th>Diff</th><th>Notes</th></tr></thead><tbody>';
        $n = 1;
        foreach ($items as $it) {
            $diff = $it['supplied'] - $it['portions_requested'];
            $diffClass = $diff < 0 ? 'shortage' : ($diff > 0 ? 'surplus' : '');
            echo '<tr><td>' . $n++ . '</td><td>' . htmlspecialchars($it['name']) . '</td>';
            echo '<td>' . $it['portions_requested'] . '</td><td>' . $it['supplied'] . '</td>';
            echo '<td class="' . $diffClass . '">' . ($diff > 0 ? '+' . $diff : ($diff < 0 ? $diff : '-')) . '</td>';
            echo '<td>' . htmlspecialchars($it['supply_notes'] ?? '') . '</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<div class="no-print d-flex gap-2">';
        echo '<button class="btn btn-outline-primary" onclick="printSection(\'printStoreLog\')"><i class="bi bi-printer"></i> Print</button>';
        echo '<button class="btn btn-outline-success" onclick="downloadCSV(\'storeLogTable\',\'store_log_' . $today . '\')"><i class="bi bi-download"></i> Download CSV</button>';
        echo '</div>';
        return;
    }

    // Show requisition for supply
    $stmt = $db->prepare("
        SELECT r.*, i.name, i.category, i.unit_weight, i.weight_unit, i.portions_per_unit
        FROM pilot_requisitions r
        JOIN pilot_items i ON r.item_id = i.id
        WHERE r.session_id = ?
        ORDER BY i.category, i.name
    ");
    $stmt->execute([$session['id']]);
    $items = $stmt->fetchAll();
?>
    <h4 class="mb-3">Supply Items <small class="text-muted">| Kitchen Requisition</small></h4>
    <p class="text-muted">Enter the portions you are supplying. You can supply more or less than requested.</p>

    <form id="supplyForm">
        <div id="printSupplyForm">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Item</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Requested</th>
                        <th style="width:130px">Supplying</th>
                        <th style="width:200px">Notes</th>
                    </tr>
                </thead>
                <tbody>
                <?php $n = 1; foreach ($items as $it): ?>
                    <tr>
                        <td><?= $n++ ?></td>
                        <td><?= htmlspecialchars($it['name']) ?></td>
                        <td><?= htmlspecialchars($it['category']) ?></td>
                        <td><?= $it['unit_weight'] . $it['weight_unit'] ?></td>
                        <td><strong><?= $it['portions_requested'] ?></strong></td>
                        <td>
                            <input type="number" name="supply[<?= $it['id'] ?>]" class="form-control form-control-sm"
                                   min="0" value="<?= $it['portions_requested'] ?>">
                        </td>
                        <td>
                            <input type="text" name="notes[<?= $it['id'] ?>]" class="form-control form-control-sm" placeholder="e.g. short stock">
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex gap-2 no-print">
            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-circle"></i> Mark as Supplied</button>
            <button type="button" class="btn btn-outline-primary" onclick="printSection('printSupplyForm')"><i class="bi bi-printer"></i> Print Requisition</button>
        </div>
    </form>

    <script>
    $('#supplyForm').on('submit', function(e) {
        e.preventDefault();
        if (!confirm('Confirm supply? The kitchen will be notified.')) return;

        let items = [];
        $('input[name^="supply"]').each(function() {
            const reqId = $(this).attr('name').match(/\d+/)[0];
            const supplied = parseInt($(this).val()) || 0;
            const notes = $('input[name="notes[' + reqId + ']"]').val() || '';
            items.push({ requisition_id: reqId, supplied: supplied, notes: notes });
        });

        $.post('api.php', {
            action: 'mark_supplied',
            session_id: <?= $session['id'] ?>,
            items: JSON.stringify(items)
        }, function(res) {
            if (res.success) {
                alert('Supply recorded! Kitchen has been notified.');
                location.href = '?page=store_dashboard';
            } else {
                alert(res.message || 'Error');
            }
        }, 'json');
    });
    </script>
<?php
}

// ============================================================
// REPORTS
// ============================================================
function include_reports($db) {
    $reportDate = $_GET['date'] ?? date('Y-m-d');
    $stmt = $db->prepare("
        SELECT ds.*, u.name as chef_name
        FROM pilot_daily_sessions ds
        JOIN pilot_users u ON ds.chef_id = u.id
        WHERE ds.session_date = ?
    ");
    $stmt->execute([$reportDate]);
    $session = $stmt->fetch();
?>
    <h4 class="mb-3">Daily Report</h4>
    <form class="row g-2 mb-4 no-print" method="GET">
        <input type="hidden" name="page" value="reports">
        <div class="col-auto">
            <input type="date" name="date" class="form-control" value="<?= $reportDate ?>">
        </div>
        <div class="col-auto">
            <button class="btn btn-primary">View Report</button>
        </div>
    </form>

    <?php if (!$session): ?>
        <div class="alert alert-info">No session found for <?= $reportDate ?>.</div>
        <?php return; ?>
    <?php endif; ?>

    <div id="printFullReport">
        <div class="text-center mb-4">
            <h5>Pantry Planner - Daily Report</h5>
            <p class="mb-1"><strong>Date:</strong> <?= date('l, F j, Y', strtotime($reportDate)) ?></p>
            <p class="mb-1"><strong>Chef:</strong> <?= htmlspecialchars($session['chef_name']) ?> | <strong>Guests:</strong> <?= $session['guest_count'] ?></p>
            <p><strong>Status:</strong> <?= ucfirst(str_replace('_', ' ', $session['status'])) ?></p>
        </div>

        <!-- Requisition Section -->
        <h5 class="mt-4 mb-2" style="color:var(--primary)">1. Requisition</h5>
        <?php
        $reqStmt = $db->prepare("
            SELECT r.*, i.name, i.category, i.unit_weight, i.weight_unit
            FROM pilot_requisitions r
            JOIN pilot_items i ON r.item_id = i.id
            WHERE r.session_id = ?
            ORDER BY i.category, i.name
        ");
        $reqStmt->execute([$session['id']]);
        $reqItems = $reqStmt->fetchAll();
        ?>
        <table class="table table-sm table-striped" id="reportReqTable">
            <thead><tr><th>#</th><th>Item</th><th>Category</th><th>Carryover</th><th>Requested</th></tr></thead>
            <tbody>
            <?php $n=1; foreach ($reqItems as $it): ?>
                <tr><td><?=$n++?></td><td><?=htmlspecialchars($it['name'])?></td><td><?=htmlspecialchars($it['category'])?></td>
                <td><?=$it['carryover_portions']?></td><td><strong><?=$it['portions_requested']?></strong></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Store Supply Section -->
        <h5 class="mt-4 mb-2" style="color:var(--primary)">2. Store Supply Log</h5>
        <?php
        $supStmt = $db->prepare("
            SELECT r.portions_requested, i.name, i.category,
                   COALESCE(ss.portions_supplied, 0) as supplied, ss.notes as supply_notes
            FROM pilot_requisitions r
            JOIN pilot_items i ON r.item_id = i.id
            LEFT JOIN pilot_store_supplies ss ON ss.requisition_id = r.id
            WHERE r.session_id = ?
            ORDER BY i.category, i.name
        ");
        $supStmt->execute([$session['id']]);
        $supItems = $supStmt->fetchAll();
        ?>
        <table class="table table-sm table-striped" id="reportSupTable">
            <thead><tr><th>#</th><th>Item</th><th>Requested</th><th>Supplied</th><th>Diff</th><th>Notes</th></tr></thead>
            <tbody>
            <?php $n=1; foreach ($supItems as $it):
                $diff = $it['supplied'] - $it['portions_requested'];
                $cls = $diff < 0 ? 'shortage' : ($diff > 0 ? 'surplus' : '');
            ?>
                <tr><td><?=$n++?></td><td><?=htmlspecialchars($it['name'])?></td>
                <td><?=$it['portions_requested']?></td><td><?=$it['supplied']?></td>
                <td class="<?=$cls?>"><?=$diff>0?'+'.  $diff:($diff<0?$diff:'-')?></td>
                <td><?=htmlspecialchars($it['supply_notes']??'')?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Consumption Section -->
        <?php if ($session['status'] === 'day_closed'): ?>
        <h5 class="mt-4 mb-2" style="color:var(--primary)">3. Consumption Report</h5>
        <?php
        $dcStmt = $db->prepare("
            SELECT dc.*, i.name, i.category
            FROM pilot_day_close dc
            JOIN pilot_items i ON dc.item_id = i.id
            WHERE dc.session_id = ?
            ORDER BY i.category, i.name
        ");
        $dcStmt->execute([$session['id']]);
        $dcItems = $dcStmt->fetchAll();
        $totalConsumed = 0; $totalRemaining = 0;
        ?>
        <table class="table table-sm table-striped" id="reportConTable">
            <thead><tr><th>#</th><th>Item</th><th>Total Available</th><th>Consumed</th><th>Remaining (Carryover)</th></tr></thead>
            <tbody>
            <?php $n=1; foreach ($dcItems as $it):
                $totalConsumed += $it['portions_consumed'];
                $totalRemaining += $it['portions_remaining'];
            ?>
                <tr><td><?=$n++?></td><td><?=htmlspecialchars($it['name'])?></td>
                <td><?=$it['portions_total']?></td><td><?=$it['portions_consumed']?></td>
                <td><strong><?=$it['portions_remaining']?></strong></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold"><td colspan="3">TOTAL</td><td><?=$totalConsumed?></td><td><?=$totalRemaining?></td></tr>
            </tfoot>
        </table>
        <?php else: ?>
            <p class="text-muted">Consumption report available after day close.</p>
        <?php endif; ?>
    </div>

    <div class="no-print d-flex gap-2 mt-3">
        <button class="btn btn-primary" onclick="printSection('printFullReport')"><i class="bi bi-printer"></i> Print Full Report</button>
        <button class="btn btn-outline-success" onclick="downloadCSV('reportReqTable','requisition_<?=$reportDate?>')"><i class="bi bi-download"></i> Requisition CSV</button>
        <button class="btn btn-outline-success" onclick="downloadCSV('reportSupTable','supply_log_<?=$reportDate?>')"><i class="bi bi-download"></i> Supply Log CSV</button>
        <?php if ($session['status'] === 'day_closed'): ?>
            <button class="btn btn-outline-success" onclick="downloadCSV('reportConTable','consumption_<?=$reportDate?>')"><i class="bi bi-download"></i> Consumption CSV</button>
        <?php endif; ?>
    </div>
<?php
}

// ============================================================
// HISTORY
// ============================================================
function include_history($db) {
    $sessions = $db->query("
        SELECT ds.*, u.name as chef_name,
               (SELECT COUNT(*) FROM pilot_requisitions WHERE session_id = ds.id) as item_count
        FROM pilot_daily_sessions ds
        JOIN pilot_users u ON ds.chef_id = u.id
        ORDER BY ds.session_date DESC
        LIMIT 30
    ")->fetchAll();
?>
    <h4 class="mb-3">Session History</h4>
    <table class="table table-striped">
        <thead>
            <tr><th>Date</th><th>Chef</th><th>Guests</th><th>Items</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($sessions as $s):
            $statusLabels = [
                'open' => '<span class="badge bg-primary">Open</span>',
                'requisition_sent' => '<span class="badge bg-warning text-dark">Req Sent</span>',
                'supplied' => '<span class="badge bg-success">Supplied</span>',
                'day_closed' => '<span class="badge bg-secondary">Closed</span>'
            ];
        ?>
            <tr>
                <td><?= date('M j, Y', strtotime($s['session_date'])) ?></td>
                <td><?= htmlspecialchars($s['chef_name']) ?></td>
                <td><?= $s['guest_count'] ?></td>
                <td><?= $s['item_count'] ?></td>
                <td><?= $statusLabels[$s['status']] ?? $s['status'] ?></td>
                <td><a href="?page=reports&date=<?= $s['session_date'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Report</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php
}

// ============================================================
// MANAGE ITEMS (Admin)
// ============================================================
function include_manage_items($db) {
    $items = $db->query("SELECT * FROM pilot_items ORDER BY category, name")->fetchAll();
    $categories = [];
    foreach ($items as $item) {
        $categories[$item['category']][] = $item;
    }
?>
    <h4 class="mb-3">Manage Standard Items</h4>

    <!-- Add Item Form -->
    <div class="card mb-4">
        <div class="card-header">Add New Item</div>
        <div class="card-body">
            <form id="addItemForm" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Item Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Category</label>
                    <input type="text" name="category" class="form-control" list="catList" required>
                    <datalist id="catList">
                        <?php foreach (array_keys($categories) as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Unit Weight</label>
                    <input type="number" name="unit_weight" class="form-control" step="0.01" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Unit</label>
                    <select name="weight_unit" class="form-select">
                        <option value="g">g</option>
                        <option value="kg">kg</option>
                        <option value="ml">ml</option>
                        <option value="L">L</option>
                        <option value="pcs">pcs</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Portions/Unit</label>
                    <input type="number" name="portions_per_unit" class="form-control" min="1" value="1" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus"></i> Add</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Items List -->
    <?php foreach ($categories as $cat => $catItems): ?>
        <div class="category-header"><?= htmlspecialchars($cat) ?> (<?= count($catItems) ?> items)</div>
        <table class="table table-sm table-striped">
            <thead><tr><th>Name</th><th>Weight</th><th>Unit</th><th>Portions/Unit</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($catItems as $it): ?>
                <tr>
                    <td><?= htmlspecialchars($it['name']) ?></td>
                    <td><?= $it['unit_weight'] ?></td>
                    <td><?= $it['weight_unit'] ?></td>
                    <td><?= $it['portions_per_unit'] ?></td>
                    <td><?= $it['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-<?= $it['is_active'] ? 'warning' : 'success' ?>"
                                onclick="toggleItem(<?= $it['id'] ?>, <?= $it['is_active'] ? 0 : 1 ?>)">
                            <?= $it['is_active'] ? 'Deactivate' : 'Activate' ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>

    <script>
    $('#addItemForm').on('submit', function(e) {
        e.preventDefault();
        $.post('api.php', {
            action: 'add_item',
            name: $('[name=name]').val(),
            category: $('[name=category]').val(),
            unit_weight: $('[name=unit_weight]').val(),
            weight_unit: $('[name=weight_unit]').val(),
            portions_per_unit: $('[name=portions_per_unit]').val()
        }, function(res) {
            if (res.success) { location.reload(); }
            else { alert(res.message || 'Error'); }
        }, 'json');
    });

    function toggleItem(id, active) {
        $.post('api.php', { action: 'toggle_item', item_id: id, is_active: active }, function(res) {
            if (res.success) location.reload();
            else alert(res.message || 'Error');
        }, 'json');
    }
    </script>
<?php
}
?>
