<?php
/**
 * Official Printable Institutional Fee Receipt Voucher
 * Islamia College of Technology College Management System
 * Human-Centered Dual-Copy Design (Institute Copy + Student Copy)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireRole(['admin', 'accountant', 'registrar', 'staff']);

$pdo = Database::getConnection();
$txId = (int)($_GET['id'] ?? 0);
$receiptNo = trim($_GET['receipt_no'] ?? '');

if ($txId <= 0 && empty($receiptNo)) {
    header("Location: index.php");
    exit;
}

if ($txId > 0) {
    $stmt = $pdo->prepare("
        SELECT f.*, s.full_name, s.student_reg_no, s.father_name, s.contact_number, s.address,
               b.batch_code, b.batch_name, c.course_name, e.total_agreed_fee,
               (SELECT COALESCE(SUM(amount_paid), 0) FROM fee_transactions WHERE enrollment_id = e.id AND id <= f.id) as cumulative_paid
        FROM fee_transactions f
        JOIN enrollments e ON f.enrollment_id = e.id
        JOIN students s ON e.student_id = s.id
        JOIN batches b ON e.batch_id = b.id
        JOIN courses c ON b.course_id = c.id
        WHERE f.id = ?
    ");
    $stmt->execute([$txId]);
} else {
    $stmt = $pdo->prepare("
        SELECT f.*, s.full_name, s.student_reg_no, s.father_name, s.contact_number, s.address,
               b.batch_code, b.batch_name, c.course_name, e.total_agreed_fee,
               (SELECT COALESCE(SUM(amount_paid), 0) FROM fee_transactions WHERE enrollment_id = e.id AND id <= f.id) as cumulative_paid
        FROM fee_transactions f
        JOIN enrollments e ON f.enrollment_id = e.id
        JOIN students s ON e.student_id = s.id
        JOIN batches b ON e.batch_id = b.id
        JOIN courses c ON b.course_id = c.id
        WHERE f.receipt_no = ? OR f.receipt_voucher_no = ?
        LIMIT 1
    ");
    $stmt->execute([$receiptNo, $receiptNo]);
}
$tx = $stmt->fetch();

if (!$tx) {
    echo "<!DOCTYPE html><html><body style='font-family:sans-serif; text-align:center; padding:50px;'>";
    echo "<h2>Receipt Not Found</h2><p>The requested fee transaction record does not exist.</p>";
    echo "<a href='index.php'>Return to Fee Payments</a></body></html>";
    exit;
}

$baseUri = strpos($_SERVER['REQUEST_URI'], '/islamia%20college') !== false ? '/islamia%20college' : '';
$remaining = max(0.0, (float)$tx['total_agreed_fee'] - (float)$tx['cumulative_paid']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Fee Receipt Voucher #<?= htmlspecialchars($tx['receipt_no'] ?: $tx['receipt_voucher_no']) ?> | ICT Matta</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
            padding: 30px 16px;
            margin: 0;
        }
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .receipt-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            padding: 28px 32px;
            margin-bottom: 24px;
            position: relative;
        }
        .receipt-copy-badge {
            position: absolute;
            top: 20px;
            right: 24px;
            background: #e2e8f0;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 4px 10px;
            border-radius: 20px;
        }
        .receipt-header {
            display: flex;
            align-items: center;
            gap: 16px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .receipt-logo {
            width: 56px;
            height: 56px;
            border-radius: 8px;
            background: #0f172a;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #38bdf8;
            font-size: 26px;
            font-weight: bold;
        }
        .receipt-title {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 4px 0;
        }
        .receipt-sub {
            font-size: 12px;
            color: #64748b;
            margin: 0;
        }
        .receipt-meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 24px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .meta-label {
            color: #64748b;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .meta-val {
            font-weight: 600;
            color: #0f172a;
        }
        .fee-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 13px;
        }
        .fee-table th {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            padding: 10px;
            text-align: left;
            color: #475569;
            font-weight: 600;
        }
        .fee-table td {
            padding: 10px;
            border-bottom: 1px solid #f1f5f9;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 36px;
            padding-top: 20px;
            border-top: 1px dashed #cbd5e1;
            font-size: 12px;
            color: #64748b;
        }
        .sign-line {
            width: 180px;
            border-top: 1px solid #0f172a;
            text-align: center;
            padding-top: 6px;
            font-weight: 600;
            color: #0f172a;
        }
        .print-actions {
            max-width: 800px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .print-actions {
                display: none;
            }
            .receipt-card {
                box-shadow: none;
                border: 1px solid #000;
                page-break-inside: avoid;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>

<div class="print-actions">
    <a href="index.php" style="color: #0284c7; text-decoration: none; font-weight: 600; font-size: 14px;">← Back to Fee Ledger</a>
    <button onclick="window.print()" style="background: #0284c7; color: white; border: none; padding: 8px 20px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 14px;">🖨️ Print Receipt Voucher</button>
</div>

<div class="receipt-container">
    <!-- 1. INSTITUTE COPY -->
    <div class="receipt-card">
        <div class="receipt-copy-badge">Institute Copy</div>
        <div class="receipt-header">
            <div class="receipt-logo">ICT</div>
            <div>
                <h1 class="receipt-title">Islamia College of Technology</h1>
                <p class="receipt-sub">Main Road, Matta Swat, Khyber Pakhtunkhwa • Official Fee Receipt Voucher</p>
            </div>
        </div>

        <div class="receipt-meta-grid">
            <div>
                <div class="meta-label">Receipt Voucher No.</div>
                <div class="meta-val" style="color: #0284c7; font-family: monospace; font-size: 15px;"><?= htmlspecialchars($tx['receipt_no'] ?: $tx['receipt_voucher_no']) ?></div>
            </div>
            <div>
                <div class="meta-label">Payment Date</div>
                <div class="meta-val"><?= $tx['payment_date'] ? date('F d, Y', strtotime($tx['payment_date'])) : date('F d, Y') ?></div>
            </div>
            <div>
                <div class="meta-label">Student Name</div>
                <div class="meta-val"><?= htmlspecialchars($tx['full_name']) ?></div>
            </div>
            <div>
                <div class="meta-label">Father's Name</div>
                <div class="meta-val"><?= htmlspecialchars($tx['father_name'] ?: '—') ?></div>
            </div>
            <div>
                <div class="meta-label">Registration No.</div>
                <div class="meta-val" style="font-family: monospace;"><?= htmlspecialchars($tx['student_reg_no']) ?></div>
            </div>
            <div>
                <div class="meta-label">Course & Batch</div>
                <div class="meta-val"><?= htmlspecialchars($tx['course_name']) ?> (<?= htmlspecialchars($tx['batch_code']) ?>)</div>
            </div>
        </div>

        <table class="fee-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Payment Mode</th>
                    <th style="text-align: right;">Amount Paid</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Tuition Fee Payment</strong>
                        <?php if (!empty($tx['remarks'])): ?>
                            <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($tx['remarks']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="text-transform: capitalize;"><?= htmlspecialchars($tx['payment_method'] ?: 'Cash') ?></td>
                    <td style="text-align: right; font-weight: 700; color: #0f172a; font-size: 14px;">Rs. <?= number_format($tx['amount_paid'], 2) ?></td>
                </tr>
                <tr style="background: #f8fafc; font-weight: 600;">
                    <td colspan="2" style="text-align: right;">Total Agreed Course Fee:</td>
                    <td style="text-align: right;">Rs. <?= number_format($tx['total_agreed_fee'], 2) ?></td>
                </tr>
                <tr style="background: #f8fafc; font-weight: 600;">
                    <td colspan="2" style="text-align: right;">Cumulative Paid to Date:</td>
                    <td style="text-align: right; color: #16a34a;">Rs. <?= number_format($tx['cumulative_paid'], 2) ?></td>
                </tr>
                <tr style="background: #fef2f2; font-weight: 700;">
                    <td colspan="2" style="text-align: right; color: #991b1b;">Remaining Outstanding Balance:</td>
                    <td style="text-align: right; color: #b91c1c;">Rs. <?= number_format($remaining, 2) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="signatures">
            <div class="sign-line">Student Signature</div>
            <div class="sign-line">Cashier / Accountant</div>
        </div>
    </div>

    <!-- 2. STUDENT COPY -->
    <div class="receipt-card">
        <div class="receipt-copy-badge">Student Copy</div>
        <div class="receipt-header">
            <div class="receipt-logo">ICT</div>
            <div>
                <h1 class="receipt-title">Islamia College of Technology</h1>
                <p class="receipt-sub">Main Road, Matta Swat, Khyber Pakhtunkhwa • Official Fee Receipt Voucher</p>
            </div>
        </div>

        <div class="receipt-meta-grid">
            <div>
                <div class="meta-label">Receipt Voucher No.</div>
                <div class="meta-val" style="color: #0284c7; font-family: monospace; font-size: 15px;"><?= htmlspecialchars($tx['receipt_no'] ?: $tx['receipt_voucher_no']) ?></div>
            </div>
            <div>
                <div class="meta-label">Payment Date</div>
                <div class="meta-val"><?= $tx['payment_date'] ? date('F d, Y', strtotime($tx['payment_date'])) : date('F d, Y') ?></div>
            </div>
            <div>
                <div class="meta-label">Student Name</div>
                <div class="meta-val"><?= htmlspecialchars($tx['full_name']) ?></div>
            </div>
            <div>
                <div class="meta-label">Father's Name</div>
                <div class="meta-val"><?= htmlspecialchars($tx['father_name'] ?: '—') ?></div>
            </div>
            <div>
                <div class="meta-label">Registration No.</div>
                <div class="meta-val" style="font-family: monospace;"><?= htmlspecialchars($tx['student_reg_no']) ?></div>
            </div>
            <div>
                <div class="meta-label">Course & Batch</div>
                <div class="meta-val"><?= htmlspecialchars($tx['course_name']) ?> (<?= htmlspecialchars($tx['batch_code']) ?>)</div>
            </div>
        </div>

        <table class="fee-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Payment Mode</th>
                    <th style="text-align: right;">Amount Paid</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Tuition Fee Payment</strong>
                        <?php if (!empty($tx['remarks'])): ?>
                            <div style="font-size: 11px; color: #64748b;"><?= htmlspecialchars($tx['remarks']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="text-transform: capitalize;"><?= htmlspecialchars($tx['payment_method'] ?: 'Cash') ?></td>
                    <td style="text-align: right; font-weight: 700; color: #0f172a; font-size: 14px;">Rs. <?= number_format($tx['amount_paid'], 2) ?></td>
                </tr>
                <tr style="background: #f8fafc; font-weight: 600;">
                    <td colspan="2" style="text-align: right;">Total Agreed Course Fee:</td>
                    <td style="text-align: right;">Rs. <?= number_format($tx['total_agreed_fee'], 2) ?></td>
                </tr>
                <tr style="background: #f8fafc; font-weight: 600;">
                    <td colspan="2" style="text-align: right;">Cumulative Paid to Date:</td>
                    <td style="text-align: right; color: #16a34a;">Rs. <?= number_format($tx['cumulative_paid'], 2) ?></td>
                </tr>
                <tr style="background: #fef2f2; font-weight: 700;">
                    <td colspan="2" style="text-align: right; color: #991b1b;">Remaining Outstanding Balance:</td>
                    <td style="text-align: right; color: #b91c1c;">Rs. <?= number_format($remaining, 2) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="signatures">
            <div class="sign-line">Student Signature</div>
            <div class="sign-line">Authorized Signatory / Seal</div>
        </div>
    </div>
</div>

</body>
</html>
