<?php
session_start();
require '../db.php';

if (!isset($_SESSION['user_logged'])) {
    header("Location: ../login.php");
    exit;
}

$saleId = (int)($_GET['id'] ?? 0);
if ($saleId <= 0) {
    die("Invalid Bill Identifier.");
}

// Fetch Sale Header
$stmt = $conn->prepare("SELECT * FROM counter_sales WHERE id = ?");
$stmt->execute([$saleId]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    die("Invoice record not found.");
}

// Fetch Sale Line Items
$itemStmt = $conn->prepare("SELECT * FROM counter_sale_items WHERE sale_id = ? ORDER BY id ASC");
$itemStmt->execute([$saleId]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= htmlspecialchars($sale['bill_no']) ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Courier New', Courier, monospace;
            background: #e2e8f0;
            padding: 20px;
            color: #0f172a;
        }
        .receipt-container {
            max-width: 330px;
            background: #fff;
            margin: auto;
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        .border-top { border-top: 1px dashed #000; }
        .border-bottom { border-bottom: 1px dashed #000; }
        .my-1 { margin-top: 4px; margin-bottom: 4px; }
        .my-2 { margin-top: 8px; margin-bottom: 8px; }
        .py-1 { padding-top: 4px; padding-bottom: 4px; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 0; font-size: 11px; vertical-align: top; }
        
        .no-print-bar {
            max-width: 330px;
            margin: 0 auto 12px auto;
            display: flex;
            gap: 8px;
        }
        .btn {
            flex: 1;
            padding: 9px;
            font-size: 12px;
            font-weight: 800;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
        }
        .btn-print { background: #0f172a; color: #f8fafc; }
        .btn-close { background: #cbd5e1; color: #1e293b; }

        @media print {
            body { background: #fff; padding: 0; }
            .no-print-bar { display: none !important; }
            .receipt-container {
                max-width: 100%;
                width: 100%;
                box-shadow: none;
                padding: 4px 0;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>

    <!-- Non-printable Top Actions -->
    <div class="no-print-bar">
        <button class="btn btn-print" onclick="window.print()">🖨️ Print / Save PDF</button>
        <button class="btn btn-close" onclick="window.close()">✕ Close</button>
    </div>

    <div class="receipt-container">
        <!-- Store Header -->
        <div class="text-center">
            <h2 style="font-size: 16px; font-weight: 900; letter-spacing: 0.5px;">SAI GANAPATHI</h2>
            <p style="font-size: 11px; font-weight: bold;">HOME NEEDS & APPLIANCES</p>
            <p style="font-size: 10px;">Main Road, Narsipatnam, AP</p>
            <p style="font-size: 10px;">📞 +91 7893282348</p>
        </div>

        <!-- Meta Information -->
        <div class="border-top border-bottom my-2 py-1" style="font-size: 10px; line-height: 1.5;">
            <div><strong>BILL NO :</strong> <?= htmlspecialchars($sale['bill_no']) ?></div>
            <div><strong>DATE    :</strong> <?= date('d/m/Y h:i A', strtotime($sale['created_at'])) ?></div>
            <div><strong>CUSTOMER:</strong> <?= htmlspecialchars($sale['customer_name']) ?></div>
            <div><strong>PHONE   :</strong> <?= htmlspecialchars($sale['customer_phone'] ?: 'Walk-in') ?></div>
            <div><strong>CASHIER :</strong> <?= htmlspecialchars($sale['cashier_name']) ?></div>
        </div>

        <!-- Line Items -->
        <table>
            <thead>
                <tr class="border-bottom text-left" style="font-size: 10px;">
                    <th style="width: 52%;">ITEM / MODEL</th>
                    <th class="text-center" style="width: 14%;">QTY</th>
                    <th class="text-right" style="width: 34%;">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td>
                            <div class="font-bold"><?= htmlspecialchars($it['product_name']) ?></div>
                            <div style="font-size: 9px; color: #475569;"><?= htmlspecialchars($it['company']) ?> - <?= htmlspecialchars($it['model']) ?></div>
                        </td>
                        <td class="text-center font-bold"><?= $it['quantity'] ?></td>
                        <td class="text-right font-bold">₹<?= number_format($it['line_total'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals Calculation -->
        <div class="border-top my-2 py-1" style="font-size: 11px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                <span>Gross MRP Total:</span>
                <span>₹<?= number_format($sale['subtotal'], 2) ?></span>
            </div>
            <?php if ((float)$sale['discount'] > 0): ?>
                <div style="display: flex; justify-content: space-between; margin-bottom: 2px;">
                    <span>Total Discount:</span>
                    <span>-₹<?= number_format($sale['discount'], 2) ?></span>
                </div>
            <?php endif; ?>
            <div class="border-top my-1"></div>
            <div style="display: flex; justify-content: space-between; font-weight: 900; font-size: 14px;">
                <span>NET PAID:</span>
                <span>₹<?= number_format($sale['grand_total'], 2) ?></span>
            </div>
            <div style="font-size: 9px; margin-top: 2px; text-transform: uppercase;">
                Mode: <strong><?= htmlspecialchars($sale['payment_mode']) ?></strong>
            </div>
        </div>

        <div class="border-bottom my-2"></div>

        <!-- Footer -->
        <div class="text-center my-2" style="font-size: 9px; line-height: 1.4;">
            <p class="font-bold">*** THANK YOU FOR VISITING! ***</p>
            <p>Manufacturer showroom warranty applies to all appliances.</p>
        </div>
    </div>

    <script>
        // Automatically open the print dialog when opened
        window.addEventListener('load', () => {
            window.print();
        });
    </script>
</body>
</html>