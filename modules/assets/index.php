<?php
/**
 * Capital Asset & Equipment Management Module
 * Islamia College of Technology College Management System
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['admin', 'accountant']);

$pdo = Database::getConnection();
$currentUser = getCurrentUser();
$userRole = strtolower($currentUser['role'] ?? 'accountant');
$canManage = in_array($userRole, ['admin', 'accountant']);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrfRequest();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_asset') {
        $name   = trim($_POST['asset_name'] ?? '');
        $specs  = trim($_POST['specification'] ?? '');
        $units  = (int)($_POST['units_count'] ?? 1);
        $cost   = (float)($_POST['total_cost'] ?? 0);
        $paid   = (float)($_POST['total_paid'] ?? 0);
        $dues   = max(0.0, $cost - $paid);
        $date   = !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : date('Y-m-d');
        $vendor = trim($_POST['vendor_name'] ?? '');

        if (empty($name) || $cost <= 0) {
            $error = "Asset name and total cost are required.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO capital_assets (asset_name, specification, units_count, total_cost, total_paid, dues, purchase_date, vendor_name, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'operational')
                ");
                $stmt->execute([$name, $specs, $units, $cost, $paid, $dues, $date, $vendor]);
                $message = "Capital asset '{$name}' registered successfully.";
            } catch (PDOException $e) {
                error_log("Create asset error: " . $e->getMessage());
                $error = "An error occurred while saving the capital asset.";
            }
        }
    } elseif ($action === 'add_installment') {
        $assetId = (int)($_POST['asset_id'] ?? 0);
        $amount  = (float)($_POST['amount_paid'] ?? 0);
        $date    = !empty($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');
        $remarks = trim($_POST['remarks'] ?? '');

        if ($assetId > 0 && $amount > 0) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO asset_payment_installments (asset_id, payment_date, amount_paid, remarks)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$assetId, $date, $amount, $remarks]);

                // Update asset total_paid & dues
                $stmt2 = $pdo->prepare("
                    UPDATE capital_assets 
                    SET total_paid = total_paid + ?, dues = GREATEST(0, total_cost - (total_paid + ?))
                    WHERE id = ?
                ");
                $stmt2->execute([$amount, $amount, $assetId]);
                $message = "Installment of Rs. " . number_format($amount, 0) . " recorded.";
            } catch (PDOException $e) {
                error_log("Add installment error: " . $e->getMessage());
                $error = "An error occurred while recording the installment.";
            }
        }
    } elseif ($action === 'delete_asset') {
        if ($userRole !== 'admin') {
            $error = "Only administrators can delete capital assets.";
        } else {
            $id = (int)($_POST['id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM capital_assets WHERE id = ?");
                $stmt->execute([$id]);
                $message = "Capital asset deleted successfully.";
            } catch (PDOException $e) {
                error_log("Delete asset error: " . $e->getMessage());
                $error = "Cannot delete asset: payment installments are linked to this asset.";
            }
        }
    }
}

// Compute live metrics from actual database
$totalCostAll = (float)$pdo->query("SELECT COALESCE(SUM(total_cost), 0) FROM capital_assets")->fetchColumn();
$totalPaidAll = (float)$pdo->query("SELECT COALESCE(SUM(total_paid), 0) FROM capital_assets")->fetchColumn();
$totalRemaining = max(0.0, $totalCostAll - $totalPaidAll);

$assets = $pdo->query("SELECT * FROM capital_assets ORDER BY id ASC")->fetchAll();
$installments = $pdo->query("
    SELECT i.*, a.asset_name 
    FROM asset_payment_installments i 
    JOIN capital_assets a ON i.asset_id = a.id 
    ORDER BY i.payment_date DESC, i.id DESC
")->fetchAll();

$pageTitle = "Capital Assets & Installments";
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Capital Assets & Equipment</h1>
        <p class="page-breadcrumb">Asset Management • Solar plant, labs, hardware investments, and financing installments</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button type="button" class="btn btn-glass" onclick="openModal('addInstallmentModal')">
            <span>+ Record Installment</span>
        </button>
        <button type="button" class="btn btn-primary" onclick="openModal('addAssetModal')">
            <span>+ Add Capital Asset</span>
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div style="padding:14px 18px; background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.3); color:#6ee7b7; border-radius:10px; margin-bottom:20px;">
        ✓ <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div style="padding:14px 18px; background:rgba(244,63,94,0.12); border:1px solid rgba(244,63,94,0.3); color:#fda4af; border-radius:10px; margin-bottom:20px;">
        ⚠️ <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- 3 Live Calculated Metric Cards (Part 27) -->
<div class="metric-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); margin-bottom: 24px;">
    <div class="metric-card">
        <div class="metric-header">
            <span class="metric-title">Total Cost</span>
            <span class="metric-icon">⚡</span>
        </div>
        <div class="metric-value">Rs. <?= number_format($totalCostAll, 0) ?></div>
        <div class="metric-sub">Capital Investments (Solar & Lab Systems)</div>
    </div>
    <div class="metric-card emerald">
        <div class="metric-header">
            <span class="metric-title">Total Paid</span>
            <span class="metric-icon">💰</span>
        </div>
        <div class="metric-value">Rs. <?= number_format($totalPaidAll, 0) ?></div>
        <div class="metric-sub">Settled Installment Payments</div>
    </div>
    <div class="metric-card amber">
        <div class="metric-header">
            <span class="metric-title">Remaining Dues</span>
            <span class="metric-icon">⏳</span>
        </div>
        <div class="metric-value">Rs. <?= number_format($totalRemaining, 0) ?></div>
        <div class="metric-sub">Outstanding Vendor Balances</div>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="tab-wrapper">
    <div class="tab-nav">
        <button type="button" class="tab-btn active" data-tab="tab-assets">Capital Asset Inventory (<?= count($assets) ?>)</button>
        <button type="button" class="tab-btn" data-tab="tab-installments">Payment Installments History (<?= count($installments) ?>)</button>
    </div>

    <!-- 1. Assets Tab -->
    <div class="tab-pane active" id="tab-assets">
        <div class="glass-card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Asset Name / Equipment</th>
                            <th>Specifications</th>
                            <th>Units</th>
                            <th>Total Cost</th>
                            <th>Total Paid</th>
                            <th>Remaining Due</th>
                            <th>Purchase Date</th>
                            <th>Status</th>
                            <?php if ($userRole === 'admin'): ?>
                                <th style="text-align:right;">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assets)): ?>
                            <tr><td colspan="<?= $userRole === 'admin' ? 9 : 8 ?>" class="empty-state">No capital assets recorded.</td></tr>
                        <?php else: ?>
                            <?php foreach ($assets as $a): ?>
                                <tr>
                                    <td><strong style="color: var(--primary-light);"><?= htmlspecialchars($a['asset_name']) ?></strong></td>
                                    <td style="color: var(--text-muted); max-width: 260px;"><?= htmlspecialchars($a['specification'] ?: '—') ?></td>
                                    <td><?= (int)$a['units_count'] ?> Units</td>
                                    <td><strong>Rs. <?= number_format((float)$a['total_cost'], 0) ?></strong></td>
                                    <td style="color: var(--accent-emerald);">Rs. <?= number_format((float)$a['total_paid'], 0) ?></td>
                                    <td>
                                        <strong style="color: <?= $a['dues'] > 0 ? 'var(--accent-amber)' : 'var(--text-muted)' ?>;">
                                            Rs. <?= number_format((float)$a['dues'], 0) ?>
                                        </strong>
                                    </td>
                                    <td style="color: var(--text-muted); font-size: 13px;"><?= $a['purchase_date'] ? date('M d, Y', strtotime($a['purchase_date'])) : '—' ?></td>
                                    <td>
                                        <span class="status-badge <?= $a['status'] === 'operational' ? 'badge-active' : 'badge-pending' ?>">
                                            <?= ucfirst(htmlspecialchars($a['status'])) ?>
                                        </span>
                                    </td>
                                    <?php if ($userRole === 'admin'): ?>
                                        <td style="text-align:right;">
                                            <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteAsset(<?= $a['id'] ?>, '<?= htmlspecialchars($a['asset_name'], ENT_QUOTES) ?>')">Delete</button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 2. Installments Tab -->
    <div class="tab-pane" id="tab-installments">
        <div class="glass-card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Receipt ID</th>
                            <th>Asset Target</th>
                            <th>Payment Date</th>
                            <th>Installment Paid</th>
                            <th>Remarks / Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($installments)): ?>
                            <tr><td colspan="5" class="empty-state">No payment installments recorded.</td></tr>
                        <?php else: ?>
                            <?php foreach ($installments as $ins): ?>
                                <tr>
                                    <td><strong style="color: var(--primary-light); font-family: monospace;">INS-#<?= $ins['id'] ?></strong></td>
                                    <td><strong><?= htmlspecialchars($ins['asset_name']) ?></strong></td>
                                    <td style="color: var(--text-muted); font-size: 13px;"><?= date('M d, Y', strtotime($ins['payment_date'])) ?></td>
                                    <td><strong style="color: var(--accent-emerald);">Rs. <?= number_format((float)$ins['amount_paid'], 0) ?></strong></td>
                                    <td style="color: var(--text-muted);"><?= htmlspecialchars($ins['remarks'] ?: 'Vendor Installment') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Asset -->
<div class="modal-overlay" id="addAssetModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Register Capital Asset</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('addAssetModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="create_asset">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="add_asset_name">Asset Name <span class="required">*</span></label>
                    <input type="text" id="add_asset_name" name="asset_name" class="form-control" required placeholder="e.g. Solar System Inverter & Panels">
                </div>

                <div class="form-group">
                    <label class="form-label" for="add_asset_spec">Technical Specification</label>
                    <textarea id="add_asset_spec" name="specification" class="form-control" placeholder="e.g. 5KW Hybrid Solar System with 8 Tier-1 Panels..."></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="add_units">Units Count</label>
                        <input type="number" id="add_units" name="units_count" class="form-control" value="1" min="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_cost">Total Cost (PKR) <span class="required">*</span></label>
                        <input type="number" id="add_cost" name="total_cost" class="form-control" required step="100" placeholder="e.g. 158950">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_paid">Down Payment (PKR)</label>
                        <input type="number" id="add_paid" name="total_paid" class="form-control" step="100" value="0">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" for="add_pdate">Purchase Date</label>
                        <input type="date" id="add_pdate" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_vendor">Vendor / Supplier</label>
                        <input type="text" id="add_vendor" name="vendor_name" class="form-control" placeholder="e.g. Swat Solar Solutions">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('addAssetModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Asset</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Record Installment -->
<div class="modal-overlay" id="addInstallmentModal">
    <div class="modal-dialog" style="max-width: 440px;">
        <div class="modal-header">
            <h3 class="modal-title">Record Asset Installment</h3>
            <button type="button" class="modal-close-btn" onclick="closeModal('addInstallmentModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="add_installment">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="ins_asset_id">Select Capital Asset <span class="required">*</span></label>
                    <select id="ins_asset_id" name="asset_id" class="form-control" required>
                        <?php foreach ($assets as $as): ?>
                            <option value="<?= $as['id'] ?>">
                                <?= htmlspecialchars($as['asset_name']) ?> (Remaining: Rs. <?= number_format((float)$as['dues'], 0) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="ins_amount">Installment Amount (PKR) <span class="required">*</span></label>
                    <input type="number" id="ins_amount" name="amount_paid" class="form-control" required step="100" placeholder="e.g. 50000">
                </div>

                <div class="form-group">
                    <label class="form-label" for="ins_date">Payment Date</label>
                    <input type="date" id="ins_date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="ins_remarks">Remarks / Receipt Note</label>
                    <input type="text" id="ins_remarks" name="remarks" class="form-control" placeholder="e.g. 2nd Installment Paid via Cheque">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-glass" onclick="closeModal('addInstallmentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Installment</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Form with CSRF -->
<form id="delete-asset-form" method="POST" action="" style="display:none;">
    <?= getCsrfField() ?>
    <input type="hidden" name="action" value="delete_asset">
    <input type="hidden" name="id" id="delete_asset_id">
</form>

<script>
function confirmDeleteAsset(id, name) {
    confirmAction(
        'Delete Capital Asset?',
        `Are you sure you want to delete '${name}'? All installment history for this asset will also be checked.`,
        () => {
            document.getElementById('delete_asset_id').value = id;
            document.getElementById('delete-asset-form').submit();
        }
    );
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
