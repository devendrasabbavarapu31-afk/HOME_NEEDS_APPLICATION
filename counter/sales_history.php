<?php
session_start();
require '../db.php';

// Filter Variables
$searchQuery = trim($_GET['search'] ?? '');
$filterDate  = trim($_GET['date'] ?? '');
$filterMode  = trim($_GET['mode'] ?? 'all');

// Build query conditions
$whereConditions = [];
$queryParams = [];

if (!empty($searchQuery)) {
    $whereConditions[] = "(cs.bill_no LIKE ? OR cs.customer_name LIKE ? OR cs.customer_phone LIKE ?)";
    $like = "%{$searchQuery}%";
    $queryParams[] = $like;
    $queryParams[] = $like;
    $queryParams[] = $like;
}

if (!empty($filterDate)) {
    $whereConditions[] = "DATE(cs.created_at) = ?";
    $queryParams[] = $filterDate;
}

if (!empty($filterMode) && $filterMode !== 'all') {
    $whereConditions[] = "cs.payment_mode = ?";
    $queryParams[] = $filterMode;
}

$whereSql = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Fetch Filtered Sales
$sql = "
    SELECT 
        cs.*, 
        COUNT(csi.id) AS total_items,
        COALESCE(SUM(csi.quantity), 0) AS total_units
    FROM counter_sales cs
    LEFT JOIN counter_sale_items csi ON cs.id = csi.sale_id
    {$whereSql}
    GROUP BY cs.id
    ORDER BY cs.id DESC
";
$stmt = $conn->prepare($sql);
$stmt->execute($queryParams);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch All Line Items mapped by Sale ID for the modal viewer
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

// Global Accounting KPI Aggregates
$kpiTotals = $conn->query("
    SELECT 
        COUNT(id) AS total_bills,
        COALESCE(SUM(subtotal), 0) AS lifetime_gross,
        COALESCE(SUM(discount), 0) AS lifetime_discounts,
        COALESCE(SUM(grand_total), 0) AS lifetime_net
    FROM counter_sales
")->fetch();

$totalUnitsSold = (int)$conn->query("SELECT COALESCE(SUM(quantity), 0) FROM counter_sale_items")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Audit History - SAI GANAPATHI HOME NEEDS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex flex-col text-slate-800 antialiased">

    <!-- Top Header Navigation -->
    <header class="bg-white border-b border-slate-200 px-6 py-3.5 flex items-center justify-between shadow-xs shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-slate-950 text-amber-400 font-black flex items-center justify-center text-sm shadow-xs">
                📜
            </div>
            <div>
                <h1 class="text-sm font-black text-slate-900 tracking-tight leading-none">Showroom Sales Ledger</h1>
                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mt-0.5">Counter Invoices & Receipt Archive</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="index.php" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl transition shadow-xs flex items-center gap-1.5">
                <span>⚡</span>
                <span>Open POS Desk</span>
            </a>
            <a href="../admin/admin_dashboard.php" class="px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-amber-300 font-bold text-xs rounded-xl transition">
                Admin Hub
            </a>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto p-4 sm:p-6 space-y-5">

        <!-- Accounting Metrics Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Total Realized Revenue</span>
                <span class="text-xl sm:text-2xl font-black text-slate-900 mt-1 block font-mono">
                    ₹<?= number_format($kpiTotals['lifetime_net'], 2) ?>
                </span>
                <span class="text-[10px] text-emerald-600 font-bold mt-0.5 block">Net Received</span>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Total Bills Invoiced</span>
                <span class="text-xl sm:text-2xl font-black text-indigo-700 mt-1 block font-mono">
                    <?= number_format($kpiTotals['total_bills']) ?>
                </span>
                <span class="text-[10px] text-slate-500 mt-0.5 block">Registered Receipts</span>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Total Units Dispatched</span>
                <span class="text-xl sm:text-2xl font-black text-blue-600 mt-1 block font-mono">
                    <?= number_format($totalUnitsSold) ?> pcs
                </span>
                <span class="text-[10px] text-slate-500 mt-0.5 block">Floor Stock Delivered</span>
            </div>

            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Total Discounts Given</span>
                <span class="text-xl sm:text-2xl font-black text-amber-600 mt-1 block font-mono">
                    ₹<?= number_format($kpiTotals['lifetime_discounts'], 2) ?>
                </span>
                <span class="text-[10px] text-slate-500 mt-0.5 block">Admin + Spot Concessions</span>
            </div>
        </div>

        <!-- Filter / Search Console -->
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs">
            <form method="GET" action="sales_history.php" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-1">Search Customer / Bill #</label>
                    <input 
                        type="text" 
                        name="search" 
                        value="<?= htmlspecialchars($searchQuery) ?>" 
                        placeholder="e.g. SGHN, Ramesh, 98765..." 
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:border-amber-500 focus:bg-white"
                    >
                </div>

                <div>
                    <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-1">Filter by Date</label>
                    <input 
                        type="date" 
                        name="date" 
                        value="<?= htmlspecialchars($filterDate) ?>" 
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:border-amber-500 focus:bg-white"
                    >
                </div>

                <div>
                    <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-1">Payment Method</label>
                    <select name="mode" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:border-amber-500 focus:bg-white">
                        <option value="all">All Payment Methods</option>
                        <option value="upi" <?= $filterMode === 'upi' ? 'selected' : '' ?>>UPI / QR Scan</option>
                        <option value="cash" <?= $filterMode === 'cash' ? 'selected' : '' ?>>Cash</option>
                        <option value="card" <?= $filterMode === 'card' ? 'selected' : '' ?>>Credit/Debit Card</option>
                        <option value="mixed" <?= $filterMode === 'mixed' ? 'selected' : '' ?>>Mixed / Split</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="w-full py-2 bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs rounded-xl transition cursor-pointer">
                        Filter Records
                    </button>
                    <a href="sales_history.php" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition text-center">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Sales Ledger Table -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Completed Sales Transactions</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">Displaying <?= count($sales) ?> recorded invoices</p>
                </div>
            </div>

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
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($sales)): ?>
                            <tr>
                                <td colspan="9" class="p-12 text-center text-slate-400 font-semibold">
                                    <span class="text-3xl block mb-1.5">🧾</span>
                                    No sales records found matching the specified criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sales as $s): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="p-3.5 whitespace-nowrap">
                                        <button onclick='openInvoiceModal(<?= json_encode($s) ?>, <?= json_encode($itemsBySale[$s['id']] ?? []) ?>)' 
                                                class="font-mono font-bold text-indigo-600 hover:underline cursor-pointer">
                                            <?= htmlspecialchars($s['bill_no']) ?>
                                        </button>
                                        <span class="block text-[9px] text-slate-400">By: <?= htmlspecialchars($s['cashier_name']) ?></span>
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
                                        <button onclick='openInvoiceModal(<?= json_encode($s) ?>, <?= json_encode($itemsBySale[$s['id']] ?? []) ?>)' 
                                                class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition cursor-pointer">
                                            Details
                                        </button>
                                        <a href="print_bill.php?id=<?= $s['id'] ?>" target="_blank" class="px-2.5 py-1 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold transition">
                                            🖨️ Print
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

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

            <!-- Modal Line Items -->
            <div id="modalItemsBody" class="py-4 overflow-y-auto flex-1"></div>

            <!-- Modal Footer Totals -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-500 font-bold block">Net Grand Total:</span>
                    <span id="modalGrandTotal" class="text-lg font-black font-mono text-emerald-700">₹0.00</span>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="closeInvoiceModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl cursor-pointer">
                        Close
                    </button>
                    <a id="modalPrintLink" href="#" target="_blank" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl transition">
                        🖨️ Print Invoice
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openInvoiceModal(sale, items) {
            document.getElementById('modalBillNo').textContent = `Invoice: ${sale.bill_no}`;
            document.getElementById('modalBillMeta').textContent = `Customer: ${sale.customer_name} (${sale.customer_phone || 'Walk-in'}) • Date: ${sale.created_at}`;
            document.getElementById('modalGrandTotal').textContent = '₹' + parseFloat(sale.grand_total).toLocaleString('en-IN', {minimumFractionDigits: 2});
            document.getElementById('modalPrintLink').href = `print_bill.php?id=${sale.id}`;

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
                            <span class="font-bold text-slate-900 block">${it.product_name}</span>
                            <span class="text-[10px] text-slate-400 font-mono">${it.company} - ${it.model}</span>
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