<?php
session_start();
require '../db.php';

// Counter Role Access Control
if (!isset($_SESSION['user_logged']) || !in_array($_SESSION['role'] ?? '', ['counter', 'admin'])) {
    header("Location: ../login.php");
    exit;
}

$cashierName = $_SESSION['username'] ?? $_SESSION['user_name'] ?? 'Staff Counter';
$success = '';
$error = '';
$lastSaleId = null;

// Shift Timing Calculation for Header Badge
$currentTime = date('H:i:s');
$isMorningSlot = ($currentTime >= '09:00:00' && $currentTime <= '10:00:00');
$isEveningSlot = ($currentTime >= '20:00:00' && $currentTime <= '21:00:00');
$isShiftOpen = ($isMorningSlot || $isEveningSlot);

// 1. Handle Bill Completion & Real-Time Stock Deduction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_sale') {
    $custName        = trim($_POST['customer_name'] ?? '');
    $custName        = empty($custName) ? 'Walk-in Customer' : $custName;
    $custPhone       = trim($_POST['customer_phone'] ?? '');
    $payMode         = $_POST['payment_mode'] ?? 'upi';
    $cartData        = json_decode($_POST['cart_json'] ?? '[]', true);
    $counterDiscount = max(0, (float)($_POST['counter_discount'] ?? 0));

    if (empty($cartData)) {
        $error = "Cannot process checkout: The billing cart is empty.";
    } else {
        try {
            $conn->beginTransaction();

            $grossSubtotal = 0;
            $adminDiscountTotal = 0;
            $itemsToProcess = [];

            foreach ($cartData as $item) {
                $intakeId = (int)$item['intake_id'];
                $qty      = max(1, (int)$item['quantity']);

                $stockStmt = $conn->prepare("
                    SELECT 
                        s.id, s.product_id, s.name, s.model, s.company, s.remaining_pieces, s.per_piece_price, s.counter_price,
                        p_pub.selling_price, p_pub.discount AS pub_discount
                    FROM stock_intake s
                    LEFT JOIN public_products p_pub ON s.product_id = p_pub.product_id
                    WHERE s.id = ? FOR UPDATE
                ");
                $stockStmt->execute([$intakeId]);
                $stockRecord = $stockStmt->fetch(PDO::FETCH_ASSOC);

                if (!$stockRecord || (int)$stockRecord['remaining_pieces'] < $qty) {
                    $avail = $stockRecord['remaining_pieces'] ?? 0;
                    throw new Exception("Stock mismatch: Only {$avail} units available for '{$stockRecord['name']}'.");
                }

                if ((float)$stockRecord['counter_price'] > 0) {
                    $originalPrice  = (float)$stockRecord['counter_price'];
                    $discountPct    = 0;
                    $finalUnitPrice = $originalPrice;
                } elseif (!empty($stockRecord['selling_price'])) {
                    $originalPrice  = (float)$stockRecord['selling_price'];
                    $discountPct    = (float)$stockRecord['pub_discount'];
                    $finalUnitPrice = max(0, $originalPrice - ($originalPrice * ($discountPct / 100)));
                } else {
                    $originalPrice  = (float)$stockRecord['per_piece_price'] * 1.15;
                    $discountPct    = 0;
                    $finalUnitPrice = $originalPrice;
                }

                $lineGross    = $qty * $originalPrice;
                $lineDiscount = $qty * ($originalPrice - $finalUnitPrice);
                $lineNet      = $qty * $finalUnitPrice;

                $grossSubtotal      += $lineGross;
                $adminDiscountTotal += $lineDiscount;

                $itemsToProcess[] = [
                    'intake_id'     => $intakeId,
                    'name'          => $stockRecord['name'],
                    'model'         => $stockRecord['model'],
                    'company'       => $stockRecord['company'],
                    'quantity'      => $qty,
                    'unit_price'    => $finalUnitPrice,
                    'line_total'    => $lineNet,
                    'new_remaining' => (int)$stockRecord['remaining_pieces'] - $qty
                ];
            }

            $subtotalAfterAdmin = max(0, $grossSubtotal - $adminDiscountTotal);
            $counterDiscount    = min($counterDiscount, $subtotalAfterAdmin);
            $grandTotal         = max(0, $subtotalAfterAdmin - $counterDiscount);
            $totalCombinedDiscount = $adminDiscountTotal + $counterDiscount;
            $billNo             = 'SGHN-' . date('ymd') . '-' . rand(1000, 9999);

            $saleStmt = $conn->prepare("
                INSERT INTO counter_sales (bill_no, customer_name, customer_phone, subtotal, discount, grand_total, payment_mode, cashier_name)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $saleStmt->execute([
                $billNo,
                $custName,
                $custPhone,
                $grossSubtotal,
                $totalCombinedDiscount,
                $grandTotal,
                $payMode,
                $cashierName
            ]);
            $saleId = (int)$conn->lastInsertId();

            $itemStmt = $conn->prepare("
                INSERT INTO counter_sale_items (sale_id, intake_id, product_name, model, company, quantity, unit_price, line_total)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $updateStockStmt = $conn->prepare("
                UPDATE stock_intake 
                SET remaining_pieces = ?, status = IF(? <= 0, 'out_of_stock', 'in_stock') 
                WHERE id = ?
            ");

            foreach ($itemsToProcess as $proc) {
                $itemStmt->execute([
                    $saleId,
                    $proc['intake_id'],
                    $proc['name'],
                    $proc['model'],
                    $proc['company'],
                    $proc['quantity'],
                    $proc['unit_price'],
                    $proc['line_total']
                ]);

                $updateStockStmt->execute([
                    $proc['new_remaining'],
                    $proc['new_remaining'],
                    $proc['intake_id']
                ]);
            }

            $conn->commit();
            $lastSaleId = $saleId;
            $success = "Invoice <strong>{$billNo}</strong> finalized and saved successfully!";
        } catch (Exception $e) {
            $conn->rollBack();
            $error = $e->getMessage();
        }
    }
}

// 2. Fetch Active Floor Stock
$stockItems = $conn->query("
    SELECT 
        s.id AS intake_id,
        s.company,
        s.name,
        s.model,
        s.remaining_pieces,
        s.per_piece_price,
        s.counter_price,
        p_pub.selling_price AS mrp,
        p_pub.discount AS pub_discount,
        CASE 
            WHEN s.counter_price > 0 THEN s.counter_price
            WHEN p_pub.selling_price IS NOT NULL THEN (p_pub.selling_price - (p_pub.selling_price * (p_pub.discount / 100)))
            ELSE s.per_piece_price * 1.15
        END AS showroom_price,
        CASE 
            WHEN s.counter_price > 0 THEN s.counter_price
            WHEN p_pub.selling_price IS NOT NULL THEN p_pub.selling_price
            ELSE s.per_piece_price * 1.15
        END AS original_mrp,
        CASE 
            WHEN s.counter_price > 0 THEN 0
            WHEN p_pub.discount IS NOT NULL THEN p_pub.discount
            ELSE 0
        END AS discount_percent
    FROM stock_intake s
    LEFT JOIN public_products p_pub ON s.product_id = p_pub.product_id
    WHERE s.remaining_pieces > 0
    ORDER BY s.company ASC, s.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$brands = array_values(array_filter(array_unique(array_column($stockItems, 'company'))));
sort($brands);

// 3. Today's Metrics
$todayStats = $conn->query("
    SELECT 
        COUNT(id) AS bills_count,
        COALESCE(SUM(grand_total), 0) AS revenue
    FROM counter_sales 
    WHERE DATE(created_at) = CURRENT_DATE()
")->fetch(PDO::FETCH_ASSOC);

// 4. Fetch All Sales History & Line Items
$salesHistory = $conn->query("
    SELECT 
        cs.*, 
        (SELECT COUNT(id) FROM counter_sale_items WHERE sale_id = cs.id) AS total_items,
        (SELECT COALESCE(SUM(quantity), 0) FROM counter_sale_items WHERE sale_id = cs.id) AS total_units
    FROM counter_sales cs
    ORDER BY cs.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$itemsQuery = $conn->query("
    SELECT 
        sale_id, product_name, model, company, quantity, unit_price, line_total 
    FROM counter_sale_items 
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$itemsBySale = [];
foreach ($itemsQuery as $it) {
    $itemsBySale[$it['sale_id']][] = $it;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Billing Terminal - Counter Staff</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex flex-col text-slate-800 antialiased overflow-hidden">

    <!-- Top Header -->
    <header class="bg-white border-b border-slate-200 px-6 py-2.5 flex items-center justify-between shadow-xs shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-amber-500 text-slate-950 font-black flex items-center justify-center text-sm shadow-xs">
                SG
            </div>
            <div>
                <h1 class="text-sm font-black text-slate-900 tracking-tight leading-none">SAI GANAPATHI HOME NEEDS</h1>
                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mt-0.5">
                    Terminal Logged In: <span class="text-indigo-600 font-mono"><?= htmlspecialchars($cashierName) ?></span>
                </p>
            </div>
        </div>

        <!-- View Switcher -->
        <div class="flex items-center bg-slate-100 p-1 rounded-2xl border border-slate-200">
            <button onclick="switchView('pos')" id="tabBtnPos" class="px-3.5 py-1.5 rounded-xl text-xs font-black transition cursor-pointer bg-white text-slate-900 shadow-xs">
                Billing Desk
            </button>
            <button onclick="switchView('history')" id="tabBtnHistory" class="px-3.5 py-1.5 rounded-xl text-xs font-black transition cursor-pointer text-slate-500 hover:text-slate-900">
                Sales History (<?= count($salesHistory) ?>)
            </button>
        </div>

        <div class="flex items-center gap-3">
            <!-- Attendance Shift Status Pill -->
            <a href="attendance.php" target="_blank" class="px-3 py-1.5 rounded-xl border text-xs font-bold transition flex items-center gap-1.5 <?= $isShiftOpen ? 'bg-emerald-50 text-emerald-800 border-emerald-300 hover:bg-emerald-100' : 'bg-rose-50 text-rose-800 border-rose-300 hover:bg-rose-100' ?>">
                <span>📸</span>
                <span><?= $isShiftOpen ? 'Attendance Open' : 'Attendance Closed' ?></span>
            </a>

            <!-- Today's Cashier Sales Counter -->
            <div class="hidden sm:flex items-center gap-2 bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-xl">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-[11px] font-bold text-slate-700">
                    Today: <strong>₹<?= number_format($todayStats['revenue'], 0) ?></strong> (<?= $todayStats['bills_count'] ?> bills)
                </span>
            </div>

            <!-- Sign Out Button -->
            <a href="../logout.php" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-bold rounded-xl border border-rose-200 transition">
                Sign Out
            </a>
        </div>
    </header>

    <!-- Notification Alerts -->
    <?php if (!empty($success)): ?>
        <div class="bg-emerald-600 text-white px-6 py-2 text-xs font-bold flex items-center justify-between shadow-xs shrink-0">
            <span><?= $success ?></span>
            <?php if ($lastSaleId): ?>
                <div class="flex items-center gap-2">
                    <button onclick="printReceipt(<?= $lastSaleId ?>)" class="px-3 py-1 bg-white text-emerald-900 rounded-lg text-xs font-black uppercase tracking-wider shadow-xs cursor-pointer hover:bg-slate-50 transition">
                        🖨️ Print Receipt
                    </button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="bg-rose-600 text-white px-6 py-2 text-xs font-bold flex items-center justify-between shadow-xs shrink-0">
            <span>⚠️ <?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- VIEW 1: Point of Sale Terminal -->
    <div id="viewPos" class="flex-1 flex min-h-0 overflow-hidden">
        
        <!-- LEFT PANE: Floor Inventory Browser -->
        <section class="flex-1 flex flex-col min-w-0 bg-slate-50 border-r border-slate-200">
            
            <div class="p-4 bg-white border-b border-slate-200 space-y-3">
                <div class="relative">
                    <input 
                        type="text" 
                        id="catalogSearch" 
                        placeholder="Search product, model number, or brand..." 
                        class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:border-amber-500 focus:bg-white transition"
                    >
                    <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
                </div>

                <!-- Brand Filter Tabs -->
                <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
                    <button type="button" class="brand-tab px-3 py-1 bg-slate-900 text-white text-[11px] font-bold rounded-full transition shrink-0 cursor-pointer" data-brand="all">
                        All Items (<?= count($stockItems) ?>)
                    </button>
                    <?php foreach ($brands as $b): ?>
                        <button type="button" class="brand-tab px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 text-[11px] font-bold rounded-full transition shrink-0 cursor-pointer" data-brand="<?= htmlspecialchars(strtolower($b)) ?>">
                            <?= htmlspecialchars($b) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Floor Stock Grid -->
            <div class="flex-1 overflow-y-auto p-4">
                <div id="catalogGrid" class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
                    <?php if (empty($stockItems)): ?>
                        <div class="col-span-full text-center py-20 text-slate-400">
                            <span class="text-4xl block mb-2">📦</span>
                            <p class="text-xs font-bold">No items available in stock.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($stockItems as $item): 
                            $itemJson = htmlspecialchars(json_encode([
                                'id'         => (int)$item['intake_id'],
                                'name'       => $item['name'],
                                'model'      => $item['model'],
                                'mrp'        => (float)$item['original_mrp'],
                                'price'      => (float)$item['showroom_price'],
                                'discount'   => (float)$item['discount_percent'],
                                'remaining'  => (int)$item['remaining_pieces']
                            ]), ENT_QUOTES, 'UTF-8');
                        ?>
                            <div class="catalog-card bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs hover:border-amber-400 hover:shadow-md transition flex flex-col justify-between group cursor-pointer"
                                 data-brand="<?= htmlspecialchars(strtolower($item['company'])) ?>"
                                 data-search="<?= htmlspecialchars(strtolower($item['company'] . ' ' . $item['name'] . ' ' . $item['model'])) ?>"
                                 data-item="<?= $itemJson ?>"
                                 onclick="handleCardClick(this)">
                                
                                <div>
                                    <div class="flex items-center justify-between gap-1 mb-1">
                                        <span class="text-[9px] font-black uppercase text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded">
                                            <?= htmlspecialchars($item['company']) ?>
                                        </span>
                                        <span class="text-[9px] font-mono text-slate-400 truncate">
                                            <?= htmlspecialchars($item['model']) ?>
                                        </span>
                                    </div>
                                    <h4 class="text-xs font-bold text-slate-900 leading-snug line-clamp-2 mt-1">
                                        <?= htmlspecialchars($item['name']) ?>
                                    </h4>
                                    <?php if ($item['discount_percent'] > 0): ?>
                                        <span class="inline-block mt-1 text-[9px] font-black bg-rose-50 text-rose-600 px-1.5 py-0.5 rounded border border-rose-100">
                                            <?= number_format($item['discount_percent'], 0) ?>% OFF
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between gap-1">
                                    <div>
                                        <?php if ($item['discount_percent'] > 0): ?>
                                            <span class="text-[9px] text-slate-400 line-through font-mono block">₹<?= number_format($item['original_mrp'], 0) ?></span>
                                        <?php endif; ?>
                                        <span class="text-xs font-black text-slate-900 font-mono block">₹<?= number_format($item['showroom_price'], 2) ?></span>
                                        <span class="text-[9px] font-bold text-emerald-600 block"><?= $item['remaining_pieces'] ?> in stock</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <button type="button" onclick="event.stopPropagation(); directBill(this)" data-item="<?= $itemJson ?>" class="px-2 py-1 bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-[10px] uppercase rounded-lg transition shadow-2xs">
                                            Direct Bill
                                        </button>
                                        <span class="w-6 h-6 rounded-lg bg-slate-900 group-hover:bg-slate-700 text-white flex items-center justify-center text-xs font-bold transition">
                                            +
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div id="noResultsText" class="hidden text-center py-20 text-slate-400">
                    <p class="text-xs font-bold">No matching stock items found.</p>
                </div>
            </div>

        </section>

        <!-- RIGHT PANE: Bill Register & Checkout Terminal -->
        <aside class="w-full lg:w-[440px] bg-white flex flex-col shrink-0 h-full shadow-lg">
            
            <div class="p-4 border-b border-slate-100 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2">
                    <span class="text-base">🛒</span>
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-900">Current Order Register</h2>
                </div>
                <button type="button" onclick="clearCart()" class="text-[11px] font-bold text-rose-600 hover:text-rose-800 transition cursor-pointer">
                    Reset Cart
                </button>
            </div>

            <!-- Cart Line Items -->
            <div id="cartItemsList" class="flex-1 overflow-y-auto p-4 space-y-2 divide-y divide-slate-100">
                <div id="emptyCartMessage" class="text-center py-20 text-slate-400">
                    <span class="text-4xl block mb-2">🧾</span>
                    <p class="text-xs font-semibold">Cart is currently empty.</p>
                    <p class="text-[10px] mt-0.5">Click any floor item or "Direct Bill" to start.</p>
                </div>
            </div>

            <!-- Billing Details & Form -->
            <form method="POST" action="" onsubmit="return validateCheckout()" class="p-4 border-t border-slate-200 bg-slate-50/70 space-y-3 shrink-0">
                <input type="hidden" name="action" value="complete_sale">
                <input type="hidden" id="cartJsonInput" name="cart_json" value="[]">

                <!-- Customer Details with Live Recognition -->
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-0.5">Phone Number</label>
                        <input type="tel" id="customerPhoneInput" name="customer_phone" maxlength="10" placeholder="9876543210" class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold font-mono focus:outline-none focus:border-amber-500 transition">
                    </div>
                    <div>
                        <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-0.5">Customer Name</label>
                        <input type="text" id="customerNameInput" name="customer_name" placeholder="Walk-in Customer" class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-amber-500 transition">
                    </div>
                </div>

                <!-- Payment Method & Counter Discount Field -->
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-0.5">Payment Method</label>
                        <select name="payment_mode" class="w-full px-2.5 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-amber-500">
                            <option value="upi">UPI / QR Scan</option>
                            <option value="cash">Cash Counter</option>
                            <option value="card">Card POS</option>
                            <option value="mixed">Mixed / Split</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-extrabold uppercase text-amber-700 mb-0.5">Counter Discount (₹)</label>
                        <input type="number" step="0.01" min="0" id="counterDiscountInput" name="counter_discount" value="0.00" oninput="calculateTotals()" placeholder="0.00" class="w-full px-2.5 py-1.5 bg-amber-50 border border-amber-300 rounded-xl text-xs font-bold text-amber-900 focus:outline-none focus:border-amber-500 font-mono">
                    </div>
                </div>

                <!-- Bill Totals Matrix -->
                <div class="p-3 bg-white border border-slate-200 rounded-xl space-y-1.5 font-mono text-xs shadow-2xs">
                    <div class="flex items-center justify-between text-slate-500">
                        <span>Gross MRP Subtotal:</span>
                        <span id="subtotalDisplay">₹0.00</span>
                    </div>
                    <div class="flex items-center justify-between text-emerald-600 font-semibold">
                        <span class="flex items-center gap-1">
                            <span>Admin Catalog Discount:</span>
                            <span id="discountItemsLabel" class="text-[10px] text-slate-400 font-normal"></span>
                        </span>
                        <span id="adminDiscountDisplay">-₹0.00</span>
                    </div>
                    <div class="flex items-center justify-between text-amber-700 font-semibold">
                        <span>Counter Spot Discount:</span>
                        <span id="counterDiscountDisplay">-₹0.00</span>
                    </div>
                    <div class="flex items-center justify-between text-sm font-black text-slate-900 pt-2 border-t border-slate-100">
                        <span>Net Payable:</span>
                        <span id="grandTotalDisplay" class="text-emerald-700 text-base">₹0.00</span>
                    </div>
                </div>

                <!-- Checkout & Print Action Button -->
                <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white font-black text-xs uppercase tracking-wider rounded-xl shadow-sm transition cursor-pointer flex items-center justify-center gap-1.5">
                    <span>🖨️</span>
                    <span>Finalize, Save & Print Bill ↵</span>
                </button>
            </form>

        </aside>

    </div>

    <!-- VIEW 2: Sales History Ledger -->
    <div id="viewHistory" class="hidden flex-1 overflow-y-auto p-4 sm:p-6 space-y-4">
        <div class="max-w-7xl mx-auto space-y-4">
            <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-black text-slate-900">Completed Sales & Invoices</h2>
                    <p class="text-xs text-slate-400">Audit log of all registered showroom receipts</p>
                </div>
                <div class="relative w-full sm:w-80">
                    <input 
                        type="text" 
                        id="historySearch" 
                        placeholder="Search invoice #, customer, phone..." 
                        class="w-full pl-9 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:border-amber-500 focus:bg-white"
                    >
                    <span class="absolute left-3 top-2 text-slate-400 text-xs">🔍</span>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-slate-50 text-slate-500 font-bold uppercase text-[10px] tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="p-3.5">Invoice #</th>
                                <th class="p-3.5">Date & Time</th>
                                <th class="p-3.5">Customer</th>
                                <th class="p-3.5 text-center">Units Sold</th>
                                <th class="p-3.5 text-right">Subtotal</th>
                                <th class="p-3.5 text-right">Discount</th>
                                <th class="p-3.5 text-right">Net Paid</th>
                                <th class="p-3.5 text-center">Mode</th>
                                <th class="p-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody" class="divide-y divide-slate-100">
                            <?php if (empty($salesHistory)): ?>
                                <tr>
                                    <td colspan="9" class="p-12 text-center text-slate-400 font-semibold">
                                        <span class="text-3xl block mb-1.5">🧾</span>
                                        No sales records created yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($salesHistory as $s): 
                                    $sJson = htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8');
                                    $itJson = htmlspecialchars(json_encode($itemsBySale[$s['id']] ?? []), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <tr class="history-row hover:bg-slate-50/80 transition"
                                        data-search="<?= htmlspecialchars(strtolower($s['bill_no'] . ' ' . $s['customer_name'] . ' ' . $s['customer_phone'])) ?>">
                                        
                                        <td class="p-3.5 whitespace-nowrap">
                                            <button data-sale="<?= $sJson ?>" data-items="<?= $itJson ?>" onclick="handleModalClick(this)"
                                                    class="font-mono font-bold text-indigo-600 hover:underline cursor-pointer">
                                                <?= htmlspecialchars($s['bill_no']) ?>
                                            </button>
                                            <span class="block text-[9px] text-slate-400">Cashier: <?= htmlspecialchars($s['cashier_name']) ?></span>
                                        </td>

                                        <td class="p-3.5 whitespace-nowrap">
                                            <span class="font-bold text-slate-800 block"><?= date('d M Y', strtotime($s['created_at'])) ?></span>
                                            <span class="text-[10px] text-slate-400 font-mono"><?= date('h:i A', strtotime($s['created_at'])) ?></span>
                                        </td>

                                        <td class="p-3.5">
                                            <span class="font-bold text-slate-900 block"><?= htmlspecialchars($s['customer_name']) ?></span>
                                            <span class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($s['customer_phone'] ?: 'Walk-in') ?></span>
                                        </td>

                                        <td class="p-3.5 text-center font-mono">
                                            <span class="px-2 py-0.5 rounded-lg bg-blue-50 text-blue-700 font-bold text-xs">
                                                <?= $s['total_units'] ?> pcs
                                            </span>
                                            <span class="block text-[9px] text-slate-400 mt-0.5">(<?= $s['total_items'] ?> items)</span>
                                        </td>

                                        <td class="p-3.5 text-right font-mono text-slate-500 whitespace-nowrap">
                                            ₹<?= number_format($s['subtotal'], 2) ?>
                                        </td>

                                        <td class="p-3.5 text-right font-mono text-rose-600 whitespace-nowrap">
                                            <?= $s['discount'] > 0 ? '-₹' . number_format($s['discount'], 2) : '₹0.00' ?>
                                        </td>

                                        <td class="p-3.5 text-right font-mono font-black text-slate-900 whitespace-nowrap">
                                            ₹<?= number_format($s['grand_total'], 2) ?>
                                        </td>

                                        <td class="p-3.5 text-center whitespace-nowrap">
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider <?= $s['payment_mode'] === 'cash' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-indigo-50 text-indigo-700 border border-indigo-200' ?>">
                                                <?= $s['payment_mode'] ?>
                                            </span>
                                        </td>

                                        <td class="p-3.5 text-right whitespace-nowrap space-x-1">
                                            <button data-sale="<?= $sJson ?>" data-items="<?= $itJson ?>" onclick="handleModalClick(this)"
                                                    class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition cursor-pointer">
                                                Details
                                            </button>
                                            <button onclick="printReceipt(<?= $s['id'] ?>)" class="px-2.5 py-1 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold transition cursor-pointer">
                                                Print
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Registered Customer Recognition Popup Modal -->
    <div id="customerAlertModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-sm w-full p-5 relative transform transition-all">
            <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                <div class="w-10 h-10 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center text-lg font-black shrink-0">
                    ⭐
                </div>
                <div>
                    <h4 class="text-xs font-black uppercase tracking-wider text-slate-900">Existing Customer Found!</h4>
                    <p class="text-[10px] text-slate-400 font-medium">Customer profile active in database</p>
                </div>
                <button onclick="closeCustomerModal()" class="ml-auto text-slate-400 hover:text-slate-700 text-lg font-bold cursor-pointer">&times;</button>
            </div>

            <div class="py-3.5 space-y-2 text-xs">
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400 font-medium">Customer Name:</span>
                    <span id="custModalName" class="font-black text-slate-900"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400 font-medium">Phone:</span>
                    <span id="custModalPhone" class="font-mono font-bold text-slate-700"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400 font-medium">Member Since:</span>
                    <span id="custModalMemberSince" class="font-medium text-slate-600"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-50">
                    <span class="text-slate-400 font-medium">Total Past Visits:</span>
                    <span id="custModalVisits" class="font-bold text-indigo-600"></span>
                </div>
                <div class="flex justify-between py-1">
                    <span class="text-slate-400 font-medium">Total Lifetime Spend:</span>
                    <span id="custModalSpent" class="font-black text-emerald-600 font-mono"></span>
                </div>
            </div>

            <button type="button" onclick="closeCustomerModal()" class="w-full py-2 bg-slate-900 hover:bg-slate-800 text-amber-300 font-bold text-xs rounded-xl shadow-xs transition cursor-pointer">
                Continue with Auto-Filled Details ✓
            </button>
        </div>
    </div>

    <!-- Invoice Details Modal -->
    <div id="invoiceModal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-2xl w-full p-6 relative max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h3 id="modalBillNo" class="text-sm font-black uppercase tracking-wider text-slate-900">Invoice Details</h3>
                    <p id="modalBillMeta" class="text-[11px] text-slate-400 mt-0.5"></p>
                </div>
                <button onclick="closeInvoiceModal()" class="text-slate-400 hover:text-slate-700 text-xl font-bold cursor-pointer">&times;</button>
            </div>

            <div id="modalItemsBody" class="py-4 overflow-y-auto flex-1"></div>

            <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-500 font-bold block">Net Grand Total:</span>
                    <span id="modalGrandTotal" class="text-lg font-black font-mono text-emerald-700">₹0.00</span>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="closeInvoiceModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl cursor-pointer">
                        Close
                    </button>
                    <button id="modalPrintBtn" onclick="" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl transition cursor-pointer flex items-center gap-1.5">
                        <span>🖨️</span>
                        <span>Print Invoice</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Interactive Scripts -->
    <script>
        function printReceipt(saleId) {
            const printWindow = window.open(`print_bill.php?id=${saleId}`, 'ReceiptWindow', 'width=450,height=700');
            if (printWindow) {
                printWindow.focus();
            }
        }

        // Auto print right after checkout if newly generated
        <?php if ($lastSaleId): ?>
            window.addEventListener('DOMContentLoaded', () => {
                printReceipt(<?= (int)$lastSaleId ?>);
            });
        <?php endif; ?>

        function switchView(view) {
            const posView = document.getElementById('viewPos');
            const historyView = document.getElementById('viewHistory');
            const tabBtnPos = document.getElementById('tabBtnPos');
            const tabBtnHistory = document.getElementById('tabBtnHistory');

            if (view === 'pos') {
                posView.classList.remove('hidden');
                historyView.classList.add('hidden');
                tabBtnPos.className = "px-3.5 py-1.5 rounded-xl text-xs font-black transition cursor-pointer bg-white text-slate-900 shadow-xs";
                tabBtnHistory.className = "px-3.5 py-1.5 rounded-xl text-xs font-black transition cursor-pointer text-slate-500 hover:text-slate-900";
            } else {
                posView.classList.add('hidden');
                historyView.classList.remove('hidden');
                tabBtnHistory.className = "px-3.5 py-1.5 rounded-xl text-xs font-black transition cursor-pointer bg-white text-slate-900 shadow-xs";
                tabBtnPos.className = "px-3.5 py-1.5 rounded-xl text-xs font-black transition cursor-pointer text-slate-500 hover:text-slate-900";
            }
        }

        let cart = [];

        function handleCardClick(el) {
            try {
                const item = JSON.parse(el.getAttribute('data-item'));
                addToCart(item.id, item.name, item.model, item.mrp, item.price, item.discount, item.remaining);
            } catch (e) {
                console.error("Failed to parse card data", e);
            }
        }

        // Direct Bill: Replaces cart with selected item and highlights the checkout inputs
        function directBill(btn) {
            try {
                const item = JSON.parse(btn.getAttribute('data-item'));
                cart = [{
                    intake_id: item.id,
                    name: item.name,
                    model: item.model,
                    mrp: parseFloat(item.mrp),
                    unit_price: parseFloat(item.price),
                    discount_pct: parseFloat(item.discount),
                    quantity: 1,
                    max_stock: parseInt(item.remaining)
                }];
                renderCart();

                // Focus customer phone field for rapid checkout
                const phoneField = document.getElementById('customerPhoneInput');
                if (phoneField) {
                    phoneField.focus();
                }
            } catch (e) {
                console.error("Failed to parse Direct Bill data", e);
            }
        }

        function addToCart(intakeId, name, model, originalMrp, finalPrice, discountPct, maxStock) {
            const existing = cart.find(i => i.intake_id === intakeId);
            if (existing) {
                if (existing.quantity < maxStock) {
                    existing.quantity++;
                } else {
                    alert(`Maximum floor stock reached (${maxStock} units).`);
                }
            } else {
                cart.push({
                    intake_id: intakeId,
                    name: name,
                    model: model,
                    mrp: parseFloat(originalMrp),
                    unit_price: parseFloat(finalPrice),
                    discount_pct: parseFloat(discountPct),
                    quantity: 1,
                    max_stock: parseInt(maxStock)
                });
            }
            renderCart();
        }

        function updateQty(intakeId, delta) {
            const item = cart.find(i => i.intake_id === intakeId);
            if (!item) return;

            item.quantity += delta;
            if (item.quantity <= 0) {
                cart = cart.filter(i => i.intake_id !== intakeId);
            } else if (item.quantity > item.max_stock) {
                item.quantity = item.max_stock;
                alert(`Stock limit reached (${item.max_stock} units).`);
            }
            renderCart();
        }

        function clearCart() {
            if (cart.length > 0 && confirm("Clear current billing items?")) {
                cart = [];
                document.getElementById('counterDiscountInput').value = '0.00';
                renderCart();
            }
        }

        function renderCart() {
            const list = document.getElementById('cartItemsList');

            if (cart.length === 0) {
                list.innerHTML = `
                    <div id="emptyCartMessage" class="text-center py-20 text-slate-400">
                        <span class="text-4xl block mb-2">🧾</span>
                        <p class="text-xs font-semibold">Cart is currently empty.</p>
                        <p class="text-[10px] mt-0.5">Click any floor item or "Direct Bill" to start.</p>
                    </div>
                `;
            } else {
                list.innerHTML = cart.map(item => `
                    <div class="p-2.5 bg-slate-50 border border-slate-200/80 rounded-xl flex items-center justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <h5 class="text-xs font-bold text-slate-900 truncate leading-snug">${escapeHtml(item.name)}</h5>
                            <div class="flex items-center gap-1.5 text-[10px] text-slate-400 font-mono">
                                <span>${escapeHtml(item.model)}</span>
                                <span>•</span>
                                ${item.discount_pct > 0 ? `<span class="line-through">₹${item.mrp.toLocaleString('en-IN')}</span>` : ''}
                                <span class="font-bold text-slate-700">₹${item.unit_price.toLocaleString('en-IN')}</span>
                                ${item.discount_pct > 0 ? `<span class="text-rose-600 font-bold">(${item.discount_pct}% OFF)</span>` : ''}
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <div class="flex items-center border border-slate-200 rounded-lg bg-white overflow-hidden text-xs">
                                <button type="button" onclick="updateQty(${item.intake_id}, -1)" class="px-2 py-0.5 hover:bg-slate-100 font-black text-slate-600 cursor-pointer">-</button>
                                <span class="px-2 py-0.5 font-bold font-mono text-slate-800">${item.quantity}</span>
                                <button type="button" onclick="updateQty(${item.intake_id}, 1)" class="px-2 py-0.5 hover:bg-slate-100 font-black text-slate-600 cursor-pointer">+</button>
                            </div>
                            <span class="text-xs font-black font-mono text-slate-900 w-20 text-right">
                                ₹${(item.quantity * item.unit_price).toLocaleString('en-IN', {minimumFractionDigits: 2})}
                            </span>
                        </div>
                    </div>
                `).join('');
            }

            calculateTotals();
        }

        function calculateTotals() {
            let grossTotal = 0;
            let subtotalAfterAdmin = 0;
            let discountedItemsCount = 0;

            cart.forEach(item => {
                grossTotal += (item.quantity * item.mrp);
                subtotalAfterAdmin += (item.quantity * item.unit_price);
                if (item.discount_pct > 0) {
                    discountedItemsCount += item.quantity;
                }
            });

            const adminDiscount = Math.max(0, grossTotal - subtotalAfterAdmin);
            
            let counterDiscount = parseFloat(document.getElementById('counterDiscountInput').value) || 0;
            if (counterDiscount < 0) counterDiscount = 0;
            if (counterDiscount > subtotalAfterAdmin) {
                counterDiscount = subtotalAfterAdmin;
                document.getElementById('counterDiscountInput').value = counterDiscount.toFixed(2);
            }

            const netTotal = Math.max(0, subtotalAfterAdmin - counterDiscount);

            document.getElementById('subtotalDisplay').textContent = '₹' + grossTotal.toLocaleString('en-IN', {minimumFractionDigits: 2});
            document.getElementById('adminDiscountDisplay').textContent = '-₹' + adminDiscount.toLocaleString('en-IN', {minimumFractionDigits: 2});
            document.getElementById('counterDiscountDisplay').textContent = '-₹' + counterDiscount.toLocaleString('en-IN', {minimumFractionDigits: 2});
            document.getElementById('grandTotalDisplay').textContent = '₹' + netTotal.toLocaleString('en-IN', {minimumFractionDigits: 2});
            
            document.getElementById('discountItemsLabel').textContent = discountedItemsCount > 0 ? `(${discountedItemsCount} items)` : '';
            document.getElementById('cartJsonInput').value = JSON.stringify(cart);
        }

        function validateCheckout() {
            if (cart.length === 0) {
                alert("Please add at least one item to checkout.");
                return false;
            }
            return true;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Live Customer Phone Lookup
        const phoneInput = document.getElementById('customerPhoneInput');
        const nameInput  = document.getElementById('customerNameInput');

        let typingTimer;
        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                clearTimeout(typingTimer);
                const phone = this.value.trim();

                if (phone.length === 10) {
                    typingTimer = setTimeout(() => {
                        fetch(`check_customer.php?phone=${encodeURIComponent(phone)}`)
                            .then(res => res.json())
                            .then(data => {
                                if (data.found) {
                                    if (nameInput) {
                                        nameInput.value = data.name;
                                        nameInput.classList.add('bg-emerald-50', 'border-emerald-400');
                                    }

                                    document.getElementById('custModalName').textContent = data.name;
                                    document.getElementById('custModalPhone').textContent = data.phone;
                                    document.getElementById('custModalMemberSince').textContent = data.member_since;
                                    document.getElementById('custModalVisits').textContent = `${data.total_bills} past bills`;
                                    document.getElementById('custModalSpent').textContent = `₹${data.total_spent.toLocaleString('en-IN', {minimumFractionDigits: 2})}`;
                                    
                                    document.getElementById('customerAlertModal').classList.remove('hidden');
                                }
                            })
                            .catch(err => console.error("Customer lookup error:", err));
                    }, 250);
                }
            });
        }

        function closeCustomerModal() {
            document.getElementById('customerAlertModal').classList.add('hidden');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('catalogSearch');
            const cards = document.querySelectorAll('.catalog-card');
            const noResults = document.getElementById('noResultsText');
            const brandTabs = document.querySelectorAll('.brand-tab');
            let activeBrand = 'all';

            function filterCatalog() {
                const query = searchInput.value.toLowerCase().trim();
                let count = 0;

                cards.forEach(card => {
                    const searchData = card.getAttribute('data-search') || '';
                    const cardBrand  = card.getAttribute('data-brand') || '';
                    const matchesQuery = searchData.includes(query);
                    const matchesBrand = (activeBrand === 'all' || cardBrand === activeBrand);

                    if (matchesQuery && matchesBrand) {
                        card.classList.remove('hidden');
                        count++;
                    } else {
                        card.classList.add('hidden');
                    }
                });

                if (noResults) noResults.classList.toggle('hidden', count > 0);
            }

            if (searchInput) searchInput.addEventListener('input', filterCatalog);

            brandTabs.forEach(tab => {
                tab.addEventListener('click', function () {
                    brandTabs.forEach(t => {
                        t.classList.remove('bg-slate-900', 'text-white');
                        t.classList.add('bg-slate-100', 'text-slate-600');
                    });
                    this.classList.remove('bg-slate-100', 'text-slate-600');
                    this.classList.add('bg-slate-900', 'text-white');

                    activeBrand = this.getAttribute('data-brand');
                    filterCatalog();
                });
            });

            const historySearch = document.getElementById('historySearch');
            if (historySearch) {
                historySearch.addEventListener('input', function () {
                    const val = this.value.toLowerCase().trim();
                    document.querySelectorAll('.history-row').forEach(row => {
                        const text = row.getAttribute('data-search') || '';
                        row.classList.toggle('hidden', !text.includes(val));
                    });
                });
            }
        });

        function handleModalClick(btn) {
            try {
                const sale = JSON.parse(btn.getAttribute('data-sale'));
                const items = JSON.parse(btn.getAttribute('data-items'));
                openInvoiceModal(sale, items);
            } catch (e) {
                console.error("Modal parse error", e);
            }
        }

        function openInvoiceModal(sale, items) {
            document.getElementById('modalBillNo').textContent = `Invoice: ${sale.bill_no}`;
            document.getElementById('modalBillMeta').textContent = `Customer: ${sale.customer_name} (${sale.customer_phone || 'Walk-in'}) | Date: ${sale.created_at}`;
            document.getElementById('modalGrandTotal').textContent = '₹' + parseFloat(sale.grand_total).toLocaleString('en-IN', {minimumFractionDigits: 2});
            
            document.getElementById('modalPrintBtn').setAttribute('onclick', `printReceipt(${sale.id})`);

            let html = `
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 font-bold uppercase text-[10px] tracking-wider border-b">
                                <th class="p-2.5">Item Description</th>
                                <th class="p-2.5 text-center">Qty</th>
                                <th class="p-2.5 text-right">Unit Price</th>
                                <th class="p-2.5 text-right">Line Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
            `;

            items.forEach(it => {
                html += `
                    <tr class="hover:bg-slate-50">
                        <td class="p-2.5">
                            <span class="font-bold text-slate-900 block">${escapeHtml(it.product_name)}</span>
                            <span class="text-[10px] text-slate-400 font-mono">${escapeHtml(it.company)} - ${escapeHtml(it.model)}</span>
                        </td>
                        <td class="p-2.5 text-center font-mono font-bold">${it.quantity}</td>
                        <td class="p-2.5 text-right font-mono">₹${parseFloat(it.unit_price).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                        <td class="p-2.5 text-right font-mono font-bold text-slate-900">₹${parseFloat(it.line_total).toLocaleString('en-IN', {minimumFractionDigits: 2})}</td>
                    </tr>
                `;
            });

            html += `
                        </tbody>
                    </table>
                    <div class="mt-3 p-3 bg-slate-50 rounded-xl space-y-1 font-mono text-xs border border-slate-200">
                        <div class="flex justify-between text-slate-500">
                            <span>Gross Subtotal:</span>
                            <span>₹${parseFloat(sale.subtotal).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                        </div>
                        <div class="flex justify-between text-rose-600 font-bold">
                            <span>Total Discounts:</span>
                            <span>-₹${parseFloat(sale.discount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                        </div>
                        <div class="flex justify-between text-slate-900 font-black border-t border-slate-200 pt-1">
                            <span>Net Paid (${sale.payment_mode.toUpperCase()}):</span>
                            <span>₹${parseFloat(sale.grand_total).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                        </div>
                    </div>
                </div>`;

            document.getElementById('modalItemsBody').innerHTML = html;
            document.getElementById('invoiceModal').classList.remove('hidden');
        }

        function closeInvoiceModal() {
            document.getElementById('invoiceModal').classList.add('hidden');
        }
    </script>
</body>
</html>