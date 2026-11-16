<?php
/**
 * Official Printable Institutional Fee Receipt Voucher
 * Islamia College of Technology College Management System
 * Dual-Copy Design (Institute Copy + Student Copy)
 * Strictly enforces:
 * - Academy name & verified official credentials
 * - Student details (Name, Father, Admission No, Roll No, Class/Course)
 * - Complete fee breakdown (Tuition fee, previous dues, approved discounts, fines, total payable)
 * - Amount received & remaining outstanding dues
 * - Accurate partial payment status indicator (Never marked as fully paid if balance remains)
 * - Unique receipt number & cashier audit trail
 * - Cancelled/Voided status protection
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

$whereClause = ($txId > 0) ? "f.id = ?" : "(f.receipt_no = ? OR f.receipt_voucher_no = ?)";
$param = ($txId > 0) ? [$txId] : [$receiptNo, $receiptNo];

$sql = "
    SELECT f.*, 
           s.id as student_id, s.full_name, s.student_reg_no, s.father_name, 
           s.contact_number, s.address, s.cnic,
           e.roll_number, e.total_agreed_fee, e.admission_fee, e.discount_amount as approved_discount,
           e.tuition_class, e.class_level,
           b.batch_code, b.batch_name, 
           c.course_name, c.course_code, c.standard_fee,
           (
               SELECT COALESCE(SUM(amount_paid), 0) 
               FROM fee_transactions 
               WHERE enrollment_id = e.id AND id <= f.id AND is_cancelled = 0
           ) as cumulative_paid_to_date,
           (
               SELECT COALESCE(SUM(amount_paid), 0) 
               FROM fee_transactions 
               WHERE enrollment_id = e.id AND id < f.id AND is_cancelled = 0
           ) as prior_paid_amount,
           (
               SELECT COALESCE(SUM(fine_amount), 0) 
               FROM fee_transactions 
               WHERE enrollment_id = e.id AND id <= f.id AND is_cancelled = 0
           ) as total_fines_to_date,
           (
               SELECT COALESCE(SUM(fine_amount), 0) 
               FROM fee_transactions 
               WHERE enrollment_id = e.id AND id < f.id AND is_cancelled = 0
           ) as prior_fines_amount
    FROM fee_transactions f
    JOIN enrollments e ON f.enrollment_id = e.id
    JOIN students s ON e.student_id = s.id
    JOIN batches b ON e.batch_id = b.id
    JOIN courses c ON b.course_id = c.id
    WHERE {$whereClause}
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute($param);
$tx = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tx) {
    echo "<!DOCTYPE html><html><body style='font-family:sans-serif; text-align:center; padding:60px; background:#f8fafc;'>";
    echo "<div style='max-width:480px; margin:0 auto; background:white; padding:32px; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 4px 12px rgba(0,0,0,0.05);'>";
    echo "<h2 style='color:#e11d48; margin-top:0;'>Receipt Not Found</h2>";
    echo "<p style='color:#64748b;'>The requested fee transaction voucher does not exist or invalid reference provided.</p>";
    echo "<a href='index.php' style='display:inline-block; margin-top:12px; padding:10px 20px; background:#0284c7; color:white; text-decoration:none; border-radius:6px; font-weight:600;'>Return to Fee Ledger</a>";
    echo "</div></body></html>";
    exit;
}

// Financial calculations
$totalAgreed = (float)$tx['total_agreed_fee'];
$admissionFee = (float)$tx['admission_fee'];
$approvedDiscount = (float)$tx['approved_discount'];
$currentPayment = (float)$tx['amount_paid'];
$currentFine = (float)($tx['fine_amount'] ?? 0.0);
$priorPaid = (float)$tx['prior_paid_amount'];
$priorFines = (float)$tx['prior_fines_amount'];

// Previous dues before this payment
$previousDues = max(0.0, ($totalAgreed + $priorFines) - $priorPaid);

// Total payable at the moment this voucher was transacted
$totalPayable = $previousDues + $currentFine;

// Cumulative paid through this payment
$cumulativePaid = (float)$tx['cumulative_paid_to_date'];
$totalFines = (float)$tx['total_fines_to_date'];

// Remaining outstanding balance after this payment
$remainingDues = max(0.0, ($totalAgreed + $totalFines) - $cumulativePaid);

// Status logic (Crucial requirement: Partial payments MUST NEVER be marked as fully paid)
$isCancelled = (int)($tx['is_cancelled'] ?? 0);
if ($isCancelled) {
    $statusText = "VOID / CANCELLED";
    $statusColor = "#dc2626";
    $statusBg = "#fef2f2";
} elseif ($remainingDues <= 0.00 && $totalAgreed > 0) {
    $statusText = "FULLY PAID / SETTLED";
    $statusColor = "#0012E2";
    $statusBg = "#eff6ff";
} else {
    $statusText = "PARTIAL PAYMENT (Pending: Rs. " . number_format($remainingDues, 0) . ")";
    $statusColor = "#b45309";
    $statusBg = "#fefce8";
}

$classLabel = $tx['tuition_class'] ?: ($tx['class_level'] ?: $tx['batch_code']);
$receiptIdentifier = htmlspecialchars($tx['receipt_no'] ?: ($tx['receipt_voucher_no'] ?: 'REC-' . $tx['id']));
$autoPrint = isset($_GET['print']) && $_GET['print'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Receipt #<?= $receiptIdentifier ?> - Islamia College of Technology</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f1f5f9;
            color: #0f172a;
            padding: 24px 12px;
            margin: 0;
            line-height: 1.45;
        }
        .page-wrapper {
            max-width: 820px;
            margin: 0 auto;
        }
        .no-print-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #0f172a;
            color: #ffffff;
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }
        .btn-print {
            background: #0012e2;
            color: #ffffff;
        }
        .btn-print:hover {
            background: #000b94;
        }
        .btn-back {
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
        }
        .btn-back:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* RECEIPT CARD */
        .voucher-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #cbd5e1;
            padding: 24px 28px;
            margin-bottom: 20px;
            position: relative;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
        }
        .watermark-void {
            position: absolute;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-25deg);
            font-size: 64px;
            font-weight: 900;
            color: rgba(220, 38, 38, 0.15);
            border: 6px dashed rgba(220, 38, 38, 0.3);
            padding: 10px 40px;
            border-radius: 16px;
            pointer-events: none;
            letter-spacing: 4px;
            text-transform: uppercase;
            z-index: 5;
        }
        .voucher-badge {
            position: absolute;
            top: 18px;
            right: 24px;
            padding: 4px 12px;
            background: #e0f2fe;
            color: #0012e2;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-radius: 20px;
            border: 1px solid #bae6fd;
        }

        /* HEADER */
        .voucher-header {
            display: flex;
            align-items: center;
            gap: 16px;
            border-bottom: 2.5px solid #0012e2;
            padding-bottom: 14px;
            margin-bottom: 16px;
        }
        .voucher-logo {
            width: 58px;
            height: 58px;
            border-radius: 10px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #ffffff;
            border: 1px solid #e2e8f0;
        }
        .voucher-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .voucher-title-wrap h1 {
            margin: 0 0 2px 0;
            font-size: 20px;
            font-weight: 800;
            color: #0012e2;
            letter-spacing: -0.3px;
        }
        .voucher-title-wrap p {
            margin: 0;
            font-size: 11.5px;
            color: #1e293b;
        }
        .voucher-title-wrap .sub-tag {
            font-size: 11px;
            font-weight: 700;
            color: #0284c7;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        /* METADATA GRID */
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 16px;
            font-size: 12px;
        }
        .meta-item .meta-label {
            font-size: 10.5px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 2px;
        }
        .meta-item .meta-value {
            font-weight: 700;
            color: #0f172a;
            word-break: break-word;
        }
        .meta-item .meta-highlight {
            color: #0012e2;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 13px;
            font-weight: 800;
        }

        /* FEE BREAKDOWN TABLE */
        .fee-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
            margin-bottom: 14px;
        }
        .fee-table th {
            background: #f0f6fe;
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            color: #0012e2;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .fee-table td {
            border: 1px solid #e2e8f0;
            padding: 7px 10px;
        }
        .fee-table tr:hover {
            background: #fafafa;
        }
        .fee-table .num-col {
            text-align: right;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 600;
        }
        .highlight-row {
            background: #f8fafc;
            font-weight: 700;
        }
        .received-row {
            background: #eff6ff;
            font-weight: 800;
            font-size: 13.5px;
        }
        .received-row td {
            border-top: 2px solid #0012e2;
            border-bottom: 2px solid #0012e2;
            color: #0012e2;
        }
        .dues-row {
            background: #fefce8;
            font-weight: 800;
            font-size: 13px;
        }
        .dues-row td {
            color: #b45309;
            border-bottom: 1.5px solid #facc15;
        }

        /* FOOTER & SIGNATURES */
        .voucher-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 20px;
            padding-top: 14px;
            border-top: 1px dashed #cbd5e1;
            font-size: 11px;
        }
        .notes-area {
            max-width: 55%;
            color: #64748b;
        }
        .notes-area strong {
            color: #334155;
        }
        .sign-area {
            display: flex;
            gap: 32px;
            text-align: center;
        }
        .sign-box {
            width: 140px;
            border-top: 1px solid #0f172a;
            padding-top: 4px;
            font-size: 11px;
            font-weight: 600;
            color: #0f172a;
        }

        /* CUT SEPARATOR */
        .cut-line {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 18px 0;
            color: #94a3b8;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .cut-line::before, .cut-line::after {
            content: "";
            flex: 1;
            border-top: 1.5px dashed #cbd5e1;
        }

        /* PRINT STYLES */
        @media print {
            body {
                background: #ffffff;
                padding: 0;
                margin: 0;
                font-size: 11px;
            }
            .no-print-bar {
                display: none !important;
            }
            .page-wrapper {
                max-width: 100%;
                margin: 0;
            }
            .voucher-card {
                border: 1px solid #64748b;
                box-shadow: none;
                margin-bottom: 12px;
                padding: 16px 20px;
                page-break-inside: avoid;
            }
            .cut-line {
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>

<div class="page-wrapper">
    <!-- Screen Actions Toolbar -->
    <div class="no-print-bar">
        <div>
            <span style="font-weight: 700; font-size: 15px; color: #38bdf8;">Official Receipt Voucher</span>
            <span style="margin-left: 12px; opacity: 0.8; font-size: 13px;">#<?= $receiptIdentifier ?></span>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="index.php" class="btn-action btn-back">← Back to Fee Module</a>
            <button onclick="window.print()" class="btn-action btn-print">🖨️ Print Receipt Voucher</button>
        </div>
    </div>

    <?php 
    // Render Dual Copies: 1) Institute Copy, 2) Student Copy
    $copies = [
        ['type' => 'Institute Copy', 'tag' => 'Accounts & Audit Record'],
        ['type' => 'Student Copy',   'tag' => 'Parent & Student Acknowledgment']
    ];

    foreach ($copies as $index => $copy): 
    ?>
    <div class="voucher-card">
        <?php if ($isCancelled): ?>
            <div class="watermark-void">CANCELLED</div>
        <?php endif; ?>

        <div class="voucher-badge"><?= $copy['type'] ?></div>

        <!-- 1. ACADEMY NAME & HEADER -->
        <div class="voucher-header">
            <div class="voucher-logo">
                <img src="../../assets/images/logo.png" alt="ICT Logo">
            </div>
            <div class="voucher-title-wrap">
                <h1>Islamia College of Technology</h1>
                <p>Main Road, Matta Swat, Khyber Pakhtunkhwa • Contact: +92 946 795900 / 0345-9450000</p>
                <div class="sub-tag">Trade Testing Board (TTB) Peshawar &amp; KP-TEVTA Recognized Institution</div>
            </div>
        </div>

        <!-- 2. STUDENT & TRANSACTION METADATA -->
        <div class="meta-grid">
            <div class="meta-item">
                <div class="meta-label">Receipt Voucher No.</div>
                <div class="meta-value meta-highlight"><?= $receiptIdentifier ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Payment Date</div>
                <div class="meta-value"><?= $tx['payment_date'] ? date('M d, Y', strtotime($tx['payment_date'])) : date('M d, Y') ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Payment Method</div>
                <div class="meta-value" style="text-transform: capitalize;">
                    <?= htmlspecialchars($tx['payment_method'] ?: $tx['payment_mode']) ?>
                    <?php if (!empty($tx['reference_no'])): ?>
                        <span style="font-size: 10.5px; color: #64748b;">(Ref: <?= htmlspecialchars($tx['reference_no']) ?>)</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Cashier / Received By</div>
                <div class="meta-value"><?= htmlspecialchars($tx['received_by'] ?: 'Accountant') ?></div>
            </div>

            <!-- Student Demographics -->
            <div class="meta-item">
                <div class="meta-label">Student Name</div>
                <div class="meta-value" style="font-size: 13.5px; color: #0f172a;"><?= htmlspecialchars($tx['full_name']) ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Father's Name</div>
                <div class="meta-value"><?= htmlspecialchars($tx['father_name'] ?: '—') ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Admission / Reg No.</div>
                <div class="meta-value" style="font-family: monospace; font-size: 12.5px;"><?= htmlspecialchars($tx['student_reg_no']) ?></div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Roll No / Class</div>
                <div class="meta-value"><?= htmlspecialchars($tx['roll_number'] ?: '—') ?> &bull; <?= htmlspecialchars($classLabel) ?></div>
            </div>

            <div class="meta-item" style="grid-column: span 2;">
                <div class="meta-label">Course / Program Enrolled</div>
                <div class="meta-value"><?= htmlspecialchars($tx['course_name']) ?> <span style="font-size: 11px; color:#64748b;">(Batch: <?= htmlspecialchars($tx['batch_code']) ?>)</span></div>
            </div>
            <div class="meta-item" style="grid-column: span 2;">
                <div class="meta-label">Payment Status Assessment</div>
                <div class="meta-value">
                    <span style="display:inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; background: <?= $statusBg ?>; color: <?= $statusColor ?>; border: 1px solid <?= $statusColor ?>;">
                        <?= $statusText ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- 3. VIEW FEE DETAILS & BREAKDOWN TABLE -->
        <table class="fee-table">
            <thead>
                <tr>
                    <th style="width: 55%;">Fee Component / Description</th>
                    <th style="width: 20%;">Term / Particulars</th>
                    <th style="width: 25%; text-align: right;">Amount (PKR)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Monthly Tuition Fee / Total Agreed Course Fee</strong>
                        <?php if ($admissionFee > 0): ?>
                            <div style="font-size: 11px; color: #64748b;">Includes Admission Registration Fee: Rs. <?= number_format($admissionFee, 0) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>Course Contract</td>
                    <td class="num-col">Rs. <?= number_format($totalAgreed, 2) ?></td>
                </tr>

                <?php if ($approvedDiscount > 0): ?>
                <tr>
                    <td style="color: #0284c7;">
                        <strong>Approved Concession / Institutional Discount</strong>
                        <div style="font-size: 11px; color: #0369a1;">Special concession granted at enrollment</div>
                    </td>
                    <td style="color: #0284c7;">Discount</td>
                    <td class="num-col" style="color: #0284c7;">- Rs. <?= number_format($approvedDiscount, 2) ?></td>
                </tr>
                <?php endif; ?>

                <?php if ($currentFine > 0): ?>
                <tr>
                    <td style="color: #b91c1c;">
                        <strong>Late Payment Fine / Administrative Surcharge</strong>
                        <div style="font-size: 11px; color: #dc2626;">Applied late fee adjustment</div>
                    </td>
                    <td style="color: #b91c1c;">Fine</td>
                    <td class="num-col" style="color: #b91c1c;">+ Rs. <?= number_format($currentFine, 2) ?></td>
                </tr>
                <?php endif; ?>

                <tr class="highlight-row">
                    <td colspan="2" style="text-align: right;">Previous Outstanding Dues (before this payment):</td>
                    <td class="num-col" style="color: #475569;">Rs. <?= number_format($previousDues, 2) ?></td>
                </tr>

                <tr class="highlight-row">
                    <td colspan="2" style="text-align: right;">Net Total Payable Now:</td>
                    <td class="num-col" style="color: #0f172a;">Rs. <?= number_format($totalPayable, 2) ?></td>
                </tr>

                <!-- ACTUAL AMOUNT RECEIVED -->
                <tr class="received-row">
                    <td colspan="2" style="text-align: right;">
                        <span style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Actual Amount Received:</span>
                        <?php if (!empty($tx['remarks'])): ?>
                            <div style="font-size: 11px; font-weight: normal; color: #0012e2; margin-top: 2px;">Note: <?= htmlspecialchars($tx['remarks']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="num-col" style="font-size: 15px;">Rs. <?= number_format($currentPayment, 2) ?></td>
                </tr>

                <!-- REMAINING OUTSTANDING DUES -->
                <tr class="dues-row">
                    <td colspan="2" style="text-align: right;">
                        <span>Remaining Outstanding Dues / Balance:</span>
                        <?php if ($remainingDues > 0): ?>
                            <div style="font-size: 10.5px; font-weight: normal; color: #b45309;">Student fee remains in partial payment status until full settlement.</div>
                        <?php else: ?>
                            <div style="font-size: 10.5px; font-weight: normal; color: #0012e2;">All current fee obligations have been fully cleared.</div>
                        <?php endif; ?>
                    </td>
                    <td class="num-col" style="font-size: 14px;">Rs. <?= number_format($remainingDues, 2) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- 4. SIGNATURES & VERIFICATION -->
        <div class="voucher-footer">
            <div class="notes-area">
                <strong>Notice &amp; Terms:</strong><br>
                1. Fees once paid are non-refundable &amp; non-transferable under college policy.<br>
                2. Retain this verified printed voucher for semester board exam clearance and fee audit.<br>
                <?php if ($isCancelled): ?>
                    <span style="color: #dc2626; font-weight: bold;">
                        VOID NOTICE: Cancelled by <?= htmlspecialchars($tx['cancelled_by']) ?> on <?= htmlspecialchars($tx['cancelled_at']) ?>. Reason: <?= htmlspecialchars($tx['cancellation_reason']) ?>
                    </span>
                <?php else: ?>
                    <span>Transaction Recorded: <?= date('Y-m-d H:i:s', strtotime($tx['created_at'])) ?> &bull; Computer Generated Document</span>
                <?php endif; ?>
            </div>

            <div class="sign-area">
                <div class="sign-box">Student / Depositor Signature</div>
                <div class="sign-box">Authorized Cashier / Stamp</div>
            </div>
        </div>
    </div>

    <?php if ($index === 0): ?>
        <div class="cut-line">✂ Cut Here — Institute Copy Above / Student Copy Below</div>
    <?php endif; ?>

    <?php endforeach; ?>
</div>

<?php if ($autoPrint): ?>
<script>
    window.addEventListener('load', function() {
        setTimeout(function() {
            window.print();
        }, 500);
    });
</script>
<?php endif; ?>

</body>
</html>
