<?php
session_start();

if (!isset($_SESSION['user_logged']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../db.php';

$success = '';
$error = '';

// Handle New Stock Intake Entry with Initial Payment, Counter Price, and Bill Photo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_intake') {
    $productId     = (int)($_POST['product_id'] ?? 0);
    $vendorName    = trim($_POST['vendor_name'] ?? '');
    $invoiceNo     = trim($_POST['invoice_number'] ?? '');
    $pieces        = (int)($_POST['stock_pieces'] ?? 0);
    $perPiecePrice = (float)($_POST['per_piece_price'] ?? 0);
    $counterPrice  = (float)($_POST['counter_price'] ?? 0);
    $initialPaid   = (float)($_POST['initial_paid'] ?? 0);
    $payMode       = $_POST['payment_mode'] ?? 'upi';
    $specs         = trim($_POST['specifications'] ?? '');

    // Pull verified model & company from products table
    $pStmt = $conn->prepare("SELECT id, company, name, model FROM products WHERE id = ?");
    $pStmt->execute([$productId]);
    $baseProduct = $pStmt->fetch();

    if (!$baseProduct) {
        $error = "Selected item not found in Products Registry.";
    } elseif (empty($vendorName)) {
        $error = "Please select a registered vendor from the dropdown.";
    } elseif ($pieces <= 0) {
        $error = "Stock pieces must be at least 1.";
    } elseif ($perPiecePrice <= 0) {
        $error = "Please enter a valid per-piece purchase cost.";
    } elseif ($counterPrice <= 0) {
        $error = "Please enter a valid counter selling price greater than ₹0.";
    } else {
        $totalAmount = $pieces * $perPiecePrice;
        $initialPaid = max(0, min($initialPaid, $totalAmount));
        
        $payStatus = 'pending';
        if ($initialPaid >= $totalAmount) {
            $payStatus = 'paid';
        } elseif ($initialPaid > 0) {
            $payStatus = 'partial';
        }

        $stockStatus = ($pieces > 0) ? 'in_stock' : 'out_of_stock';

        // Bill Photo Upload Handler
        $billPhotoPath = null;
        if (!empty($_FILES['bill_photo']['name'])) {
            $targetDir = "../uploads/bills/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            $fileName = 'bill_' . time() . '_' . preg_replace("/[^a-zA-Z0-9._-]/", "", basename($_FILES['bill_photo']['name']));
            $targetFilePath = $targetDir . $fileName;
            $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

            if (in_array($fileType, $allowed)) {
                if (move_uploaded_file($_FILES['bill_photo']['tmp_name'], $targetFilePath)) {
                    $billPhotoPath = 'uploads/bills/' . $fileName;
                } else {
                    $error = "Failed to upload bill photo.";
                }
            } else {
                $error = "Bill document must be an image (JPG, PNG, WEBP) or PDF.";
            }
        }

        if (empty($error)) {
            try {
                $conn->beginTransaction();

                $stmt = $conn->prepare("
                    INSERT INTO stock_intake 
                    (product_id, company, name, model, vendor_name, invoice_number, bill_photo, specifications, stock_pieces, remaining_pieces, per_piece_price, counter_price, total_amount, paid_amount, payment_status, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $baseProduct['id'],
                    $baseProduct['company'],
                    $baseProduct['name'],
                    $baseProduct['model'],
                    $vendorName,
                    $invoiceNo,
                    $billPhotoPath,
                    $specs,
                    $pieces,
                    $pieces,
                    $perPiecePrice,
                    $counterPrice,
                    $totalAmount,
                    $initialPaid,
                    $payStatus,
                    $stockStatus
                ]);

                $intakeId = $conn->lastInsertId();

                if ($initialPaid > 0) {
                    $payStmt = $conn->prepare("
                        INSERT INTO vendor_payment_ledger (intake_id, payment_amount, payment_mode, note) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $payStmt->execute([$intakeId, $initialPaid, $payMode, 'Initial Payment at Intake']);
                }

                $conn->commit();
                $success = "Stock batch, counter price (₹" . number_format($counterPrice, 2) . ") & initial payment logged successfully!";
            } catch (PDOException $e) {
                $conn->rollBack();
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// Handle Add Subsequent Vendor Payment Installment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_payment') {
    $intakeId    = (int)$_POST['intake_id'];
    $payAmount   = (float)$_POST['payment_amount'];
    $payMode     = $_POST['payment_mode'] ?? 'upi';
    $payNote     = trim($_POST['note'] ?? 'Installment payment');

    $intakeStmt = $conn->prepare("SELECT total_amount, paid_amount FROM stock_intake WHERE id = ?");
    $intakeStmt->execute([$intakeId]);
    $item = $intakeStmt->fetch();

    if ($item && $payAmount > 0) {
        $remainingDue = max(0, $item['total_amount'] - $item['paid_amount']);
        $actualPay    = min($payAmount, $remainingDue);
        $newPaidTotal = $item['paid_amount'] + $actualPay;
        $newPayStatus = ($newPaidTotal >= $item['total_amount']) ? 'paid' : 'partial';

        try {
            $conn->beginTransaction();

            $stmt1 = $conn->prepare("INSERT INTO vendor_payment_ledger (intake_id, payment_amount, payment_mode, note) VALUES (?, ?, ?, ?)");
            $stmt1->execute([$intakeId, $actualPay, $payMode, $payNote]);

            $stmt2 = $conn->prepare("UPDATE stock_intake SET paid_amount = ?, payment_status = ? WHERE id = ?");
            $stmt2->execute([$newPaidTotal, $newPayStatus, $intakeId]);

            $conn->commit();
            $success = "Payment installment of ₹" . number_format($actualPay, 2) . " recorded!";
        } catch (PDOException $e) {
            $conn->rollBack();
            $error = "Error updating payment: " . $e->getMessage();
        }
    }
}

// Quick Inline Sold / Remaining Units & Counter Price Adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_stock_row') {
    $intakeId     = (int)$_POST['intake_id'];
    $remaining    = max(0, (int)$_POST['remaining_pieces']);
    $counterPrice = max(0, (float)$_POST['counter_price']);
    $newStatus    = ($remaining == 0) ? 'out_of_stock' : 'in_stock';

    $stmt = $conn->prepare("UPDATE stock_intake SET remaining_pieces = ?, counter_price = ?, status = ? WHERE id = ?");
    $stmt->execute([$remaining, $counterPrice, $newStatus, $intakeId]);
    $success = "Stock units and counter price updated.";
}

// Handle Delete Intake Entry
if (isset($_GET['delete_intake'])) {
    $delId = (int)$_GET['delete_intake'];
    $conn->prepare("DELETE FROM stock_intake WHERE id = ?")->execute([$delId]);
    header("Location: stock_intake.php?deleted=1");
    exit;
}

// Base products and vendors
$storeProducts = $conn->query("SELECT id, company, name, model FROM products ORDER BY name ASC")->fetchAll();
$vendorsList   = $conn->query("SELECT id, vendor_name, phone FROM vendors ORDER BY vendor_name ASC")->fetchAll();

// KPIs
$lifetimeBilledUnits    = $conn->query("SELECT COALESCE(SUM(stock_pieces), 0) FROM stock_intake")->fetchColumn();
$lifetimeRemainingUnits = $conn->query("SELECT COALESCE(SUM(remaining_pieces), 0) FROM stock_intake")->fetchColumn();
$lifetimeUnitsSold      = max(0, $lifetimeBilledUnits - $lifetimeRemainingUnits);
$lifetimeInvoiceAmount  = $conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM stock_intake")->fetchColumn() ?: 0.00;
$lifetimePaidAmount     = $conn->query("SELECT COALESCE(SUM(paid_amount), 0) FROM stock_intake")->fetchColumn() ?: 0.00;
$lifetimeDueBalance     = max(0, $lifetimeInvoiceAmount - $lifetimePaidAmount);

// Fetch all Intake Records
$intakeList = $conn->query("
    SELECT 
        si.*,
        (si.total_amount - si.paid_amount) AS due_amount,
        (SELECT COUNT(*) FROM vendor_payment_ledger vpl WHERE vpl.intake_id = si.id) AS installment_count
    FROM stock_intake si 
    ORDER BY si.id DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Intake & Counter Pricing - SAI GANAPATHI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800 antialiased flex">

    <?php 
    $currentPage = 'intake';
    include 'sidebar.php'; 
    ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <header class="bg-white/95 backdrop-blur-md border-b border-slate-200 sticky top-0 z-30 px-6 py-4 flex items-center justify-between shadow-xs">
            <div>
                <h2 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">Stock Intake & Counter Pricing</h2>
                <p class="text-xs text-slate-500 font-medium">Record procurement invoices, upload bill copies, set counter prices, and track debt</p>
            </div>
            
            <div class="flex items-center gap-2.5">
                <a href="vendors.php" class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-amber-300 text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1.5">
                    <span>🏢</span> Vendor Directory
                </a>
                <a href="publish_product.php" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1.5">
                    <span>🚀</span> Publish Showcase
                </a>
            </div>
        </header>

        <main class="p-6 max-w-7xl w-full mx-auto space-y-6">

            <?php if (!empty($success) || isset($_GET['deleted'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-bold shadow-xs">
                    ✅ <?= !empty($success) ? htmlspecialchars($success) : "Ledger record updated successfully!" ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-2xl text-xs font-bold shadow-xs">
                    ⚠️ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Metric Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Lifetime Invoiced</span>
                    <span class="text-2xl font-black text-slate-900 mt-1 block">₹<?= number_format($lifetimeInvoiceAmount, 2) ?></span>
                    <span class="text-[11px] text-slate-500 mt-0.5 block"><?= number_format($lifetimeBilledUnits) ?> Units Procured</span>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                    <span class="text-[10px] uppercase font-bold text-emerald-600 block tracking-wider">Paid to Vendors</span>
                    <span class="text-2xl font-black text-emerald-600 mt-1 block">₹<?= number_format($lifetimePaidAmount, 2) ?></span>
                    <span class="text-[11px] text-slate-500 mt-0.5 block">Cleared Installments</span>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                    <span class="text-[10px] uppercase font-bold text-rose-600 block tracking-wider">Remaining Due</span>
                    <span class="text-2xl font-black text-rose-600 mt-1 block">₹<?= number_format($lifetimeDueBalance, 2) ?></span>
                    <span class="text-[11px] text-slate-500 mt-0.5 block">Payable Balance</span>
                </div>
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                    <span class="text-[10px] uppercase font-bold text-blue-600 block tracking-wider">Units on Hand</span>
                    <span class="text-2xl font-black text-blue-600 mt-1 block"><?= number_format($lifetimeRemainingUnits) ?> / <?= number_format($lifetimeBilledUnits) ?></span>
                    <span class="text-[11px] text-slate-500 mt-0.5 block"><?= number_format($lifetimeUnitsSold) ?> Sold Lifetime</span>
                </div>
            </div>

            <!-- Two-Column Form & Table -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                
                <!-- Stock Intake Form -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs sticky top-24">
                    <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Record Stock Intake</h3>
                            <p class="text-[11px] text-slate-400">Add inventory, bill photo & counter price</p>
                        </div>
                        <span class="text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-100 px-2 py-0.5 rounded-md">Procurement</span>
                    </div>

                    <form method="POST" action="stock_intake.php" enctype="multipart/form-data" class="space-y-3">
                        <input type="hidden" name="action" value="add_intake">

                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Select Product *</label>
                            <select name="product_id" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500">
                                <option value="">-- Select Registered Item --</option>
                                <?php foreach ($storeProducts as $sp): ?>
                                    <option value="<?= $sp['id'] ?>">
                                        [#<?= $sp['id'] ?>] <?= htmlspecialchars($sp['company']) ?> - <?= htmlspecialchars($sp['name']) ?> (<?= htmlspecialchars($sp['model']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-[11px] font-bold uppercase text-slate-500">Select Vendor *</label>
                                <a href="vendors.php" target="_blank" class="text-[10px] font-bold text-indigo-600 hover:underline">+ Add New</a>
                            </div>
                            <select name="vendor_name" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500">
                                <option value="">-- Choose Registered Vendor --</option>
                                <?php foreach ($vendorsList as $v): ?>
                                    <option value="<?= htmlspecialchars($v['vendor_name']) ?>">
                                        <?= htmlspecialchars($v['vendor_name']) ?> (<?= htmlspecialchars($v['phone']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Invoice / Bill #</label>
                                <input type="text" name="invoice_number" placeholder="INV-2026-041" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Upload Bill Copy</label>
                                <input type="file" name="bill_photo" accept="image/*,.pdf" class="w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-slate-900 file:text-white hover:file:bg-indigo-600 cursor-pointer">
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <div>
                                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Pieces *</label>
                                <input type="number" id="piecesInput" name="stock_pieces" min="1" value="10" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Cost / Pc (₹) *</label>
                                <input type="number" id="priceInput" step="0.01" name="per_piece_price" placeholder="10000" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold uppercase text-indigo-700 mb-1">Counter Price *</label>
                                <input type="number" id="counterPriceInput" step="0.01" name="counter_price" placeholder="12500" required class="w-full px-3 py-2 bg-indigo-50/50 border border-indigo-200 rounded-xl text-xs font-bold text-indigo-900 focus:outline-none focus:border-indigo-500">
                            </div>
                        </div>

                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between text-xs">
                            <span class="text-slate-500 font-bold">Total Cost Invoice:</span>
                            <span id="calculatedTotal" class="font-black text-slate-900 text-sm">₹0.00</span>
                        </div>

                        <!-- Initial Payment Fields -->
                        <div class="p-3 bg-amber-50/70 border border-amber-200/80 rounded-xl space-y-2">
                            <span class="text-[10px] uppercase tracking-wider font-extrabold text-amber-800 block">Initial Vendor Payment</span>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-0.5">Amount Paid (₹)</label>
                                    <input type="number" step="0.01" name="initial_paid" value="0.00" class="w-full px-2.5 py-1.5 bg-white border border-amber-200 rounded-lg text-xs font-bold focus:outline-none focus:border-amber-500">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold uppercase text-slate-500 mb-0.5">Payment Mode</label>
                                    <select name="payment_mode" class="w-full px-2 py-1.5 bg-white border border-amber-200 rounded-lg text-xs font-semibold focus:outline-none focus:border-amber-500">
                                        <option value="upi">UPI / Online</option>
                                        <option value="bank_transfer">Bank Transfer / NEFT</option>
                                        <option value="cash">Cash</option>
                                        <option value="cheque">Cheque</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Specifications</label>
                            <textarea name="specifications" rows="2" placeholder="e.g. 5 Star Inverter Front Load | Dark Silver" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-indigo-500"></textarea>
                        </div>

                        <button type="submit" class="w-full mt-2 py-2.5 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-xs transition cursor-pointer">
                            📥 Record Stock & Bill
                        </button>
                    </form>
                </div>

                <!-- Ledger Table -->
                <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Procurement & Counter Ledger</h3>
                            <p class="text-[11px] text-slate-400">Total <?= count($intakeList) ?> intake records tracked</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50/80 text-slate-500 font-bold uppercase text-[10px] tracking-wider border-b border-slate-200/80">
                                    <th class="p-3.5">Product & Vendor</th>
                                    <th class="p-3.5 text-right">Cost & Invoice</th>
                                    <th class="p-3.5 text-center">Remaining & Counter ₹</th>
                                    <th class="p-3.5 text-center">Bill Copy</th>
                                    <th class="p-3.5 text-center">Payment</th>
                                    <th class="p-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($intakeList)): ?>
                                    <tr>
                                        <td colspan="6" class="p-8 text-center text-slate-400 font-semibold">No stock intake recorded yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($intakeList as $item): 
                                        $due = max(0, $item['total_amount'] - $item['paid_amount']);
                                    ?>
                                        <tr class="hover:bg-slate-50/70 transition">
                                            
                                            <td class="p-3.5">
                                                <span class="text-[10px] font-black uppercase text-indigo-700 bg-indigo-50 border border-indigo-100/80 px-2 py-0.5 rounded-md">
                                                    <?= htmlspecialchars($item['company']) ?>
                                                </span>
                                                <p class="font-bold text-slate-900 text-xs mt-1 leading-snug"><?= htmlspecialchars($item['name']) ?></p>
                                                <p class="text-[11px] text-slate-500 font-mono"><?= htmlspecialchars($item['model']) ?></p>
                                                <div class="mt-1 text-[10px] text-slate-400">
                                                    <span>Vendor: <strong class="text-slate-600"><?= htmlspecialchars($item['vendor_name']) ?></strong></span>
                                                    <?php if (!empty($item['invoice_number'])): ?>
                                                        <span class="ml-1.5">• Inv: <strong class="text-slate-600 font-mono"><?= htmlspecialchars($item['invoice_number']) ?></strong></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <td class="p-3.5 text-right whitespace-nowrap font-mono">
                                                <span class="text-xs font-black text-slate-900 block">Total: ₹<?= number_format($item['total_amount'], 2) ?></span>
                                                <span class="text-[10px] text-slate-400 block font-sans">Cost: ₹<?= number_format($item['per_piece_price'], 2) ?>/pc</span>
                                                <?php if ($due > 0): ?>
                                                    <span class="text-[10px] text-rose-600 font-extrabold block mt-0.5">Due: ₹<?= number_format($due, 2) ?></span>
                                                <?php else: ?>
                                                    <span class="text-[10px] text-emerald-700 font-bold block mt-0.5">✓ Cleared</span>
                                                <?php endif; ?>
                                            </td>

                                            <td class="p-3.5 whitespace-nowrap text-center">
                                                <form method="POST" action="stock_intake.php" class="flex flex-col gap-1 items-center justify-center">
                                                    <input type="hidden" name="action" value="update_stock_row">
                                                    <input type="hidden" name="intake_id" value="<?= $item['id'] ?>">

                                                    <div class="flex items-center gap-1">
                                                        <input type="number" name="remaining_pieces" value="<?= $item['remaining_pieces'] ?>" min="0" max="<?= $item['stock_pieces'] ?>" class="w-14 px-1.5 py-0.5 border border-slate-200 rounded-md text-center text-xs font-bold font-mono">
                                                        <span class="text-[10px] text-slate-400 font-mono">/ <?= $item['stock_pieces'] ?></span>
                                                    </div>

                                                    <div class="flex items-center gap-1">
                                                        <span class="text-[10px] font-bold text-slate-400">Sell: ₹</span>
                                                        <input type="number" step="0.01" name="counter_price" value="<?= $item['counter_price'] ?>" class="w-20 px-1.5 py-0.5 border border-indigo-200 bg-indigo-50/40 rounded-md text-xs font-bold font-mono text-indigo-900">
                                                        <button type="submit" title="Save" class="p-1 bg-slate-100 hover:bg-slate-200 rounded text-[10px]">💾</button>
                                                    </div>
                                                </form>
                                            </td>

                                            <!-- Bill Photo Link / Thumbnail -->
                                            <td class="p-3.5 text-center whitespace-nowrap">
                                                <?php if (!empty($item['bill_photo'])): ?>
                                                    <a href="../<?= htmlspecialchars($item['bill_photo']) ?>" target="_blank" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 text-[10px] font-bold">
                                                        <span>📄 View Bill</span>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-[10px] text-slate-300 font-semibold italic">No Copy</span>
                                                <?php endif; ?>
                                            </td>

                                            <td class="p-3.5 text-center whitespace-nowrap">
                                                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-extrabold <?= $item['payment_status'] === 'paid' ? 'bg-emerald-50 text-emerald-700' : ($item['payment_status'] === 'partial' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700') ?>">
                                                    <?= strtoupper($item['payment_status']) ?>
                                                </span>
                                            </td>

                                            <td class="p-3.5 text-right whitespace-nowrap">
                                                <?php if ($due > 0): ?>
                                                    <button onclick="openPaymentModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['name'])) ?>', <?= $due ?>)" 
                                                            class="px-2.5 py-1 text-[11px] font-bold bg-amber-500 hover:bg-amber-600 text-white rounded-lg transition mr-1 cursor-pointer">
                                                        + Pay Due
                                                    </button>
                                                <?php endif; ?>
                                                <a href="stock_intake.php?delete_intake=<?= $item['id'] ?>" 
                                                   onclick="return confirm('Delete this intake batch?')" 
                                                   class="px-2 py-1 text-[11px] font-bold text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition">
                                                    Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

        </main>
    </div>

    <!-- Vendor Payment Modal -->
    <div id="paymentModal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full p-5 relative">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Record Vendor Installment</h3>
                <button onclick="closePaymentModal()" class="text-slate-400 hover:text-slate-600 text-base font-bold cursor-pointer">&times;</button>
            </div>

            <form method="POST" action="stock_intake.php" class="space-y-3 mt-4">
                <input type="hidden" name="action" value="add_payment">
                <input type="hidden" id="modalIntakeId" name="intake_id" value="">

                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-500 mb-0.5">Product</label>
                    <p id="modalProductName" class="text-xs font-bold text-slate-800 bg-slate-50 p-2 rounded-lg border border-slate-200/80"></p>
                </div>

                <div class="p-2.5 bg-rose-50 border border-rose-100 rounded-xl flex items-center justify-between text-xs">
                    <span class="text-rose-800 font-bold">Outstanding Balance:</span>
                    <span id="modalDueText" class="font-black text-rose-700">₹0.00</span>
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Installment Amount (₹) *</label>
                    <input type="number" step="0.01" id="modalPayAmount" name="payment_amount" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-amber-500">
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Payment Mode</label>
                    <select name="payment_mode" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:border-amber-500">
                        <option value="upi">UPI / PhonePe / GPay</option>
                        <option value="bank_transfer">Bank Transfer / NEFT</option>
                        <option value="cash">Cash</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Ledger Note / Reference</label>
                    <input type="text" name="note" placeholder="e.g. 2nd Installment Paid via UPI" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-amber-500">
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <button type="button" onclick="closePaymentModal()" class="w-1/2 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="w-1/2 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs cursor-pointer">
                        Save Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const piecesInput = document.getElementById('piecesInput');
        const priceInput  = document.getElementById('priceInput');
        const totalBox    = document.getElementById('calculatedTotal');

        function updateTotal() {
            const pieces = parseFloat(piecesInput.value) || 0;
            const price  = parseFloat(priceInput.value) || 0;
            const total  = pieces * price;
            totalBox.textContent = '₹' + total.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        piecesInput.addEventListener('input', updateTotal);
        priceInput.addEventListener('input', updateTotal);

        function openPaymentModal(id, name, due) {
            document.getElementById('modalIntakeId').value = id;
            document.getElementById('modalProductName').textContent = name;
            document.getElementById('modalDueText').textContent = '₹' + due.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('modalPayAmount').max = due;
            document.getElementById('modalPayAmount').value = due;
            document.getElementById('paymentModal').classList.remove('hidden');
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').classList.add('hidden');
        }
    </script>
</body>
</html>