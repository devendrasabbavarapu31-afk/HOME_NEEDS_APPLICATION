<?php
session_start();

// Strict Access Guard: Only authenticated administrators
if (!isset($_SESSION['user_logged']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../db.php';

// Active indicator for sidebar.php
$currentPage = 'dashboard';

// Month & Year Filter Configuration
$selectedYear  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

if ($selectedMonth < 1 || $selectedMonth > 12) {
    $selectedMonth = (int)date('m');
}
if ($selectedYear < 2020 || $selectedYear > 2040) {
    $selectedYear = (int)date('Y');
}

$monthName = date('F', mktime(0, 0, 0, $selectedMonth, 10));

// Handle Inline Remaining Stock Adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_adjust_stock') {
    $intakeId     = (int)$_POST['intake_id'];
    $newRemaining = max(0, (int)$_POST['remaining_pieces']);
    $newStatus    = ($newRemaining == 0) ? 'out_of_stock' : 'in_stock';

    $stmt = $conn->prepare("UPDATE stock_intake SET remaining_pieces = ?, status = ? WHERE id = ?");
    $stmt->execute([$newRemaining, $newStatus, $intakeId]);
    header("Location: admin_dashboard.php?month={$selectedMonth}&year={$selectedYear}&adjusted=1");
    exit;
}

// 1. Counter Panel Analytics (Today, Lifetime)
$todaySales = $conn->query("
    SELECT 
        COUNT(id) AS today_bills,
        COALESCE(SUM(grand_total), 0) AS today_revenue
    FROM counter_sales
    WHERE DATE(created_at) = CURRENT_DATE()
")->fetch(PDO::FETCH_ASSOC);

$overallSales = $conn->query("
    SELECT 
        COUNT(id) AS lifetime_bills,
        COALESCE(SUM(grand_total), 0) AS lifetime_revenue
    FROM counter_sales
")->fetch(PDO::FETCH_ASSOC);

// 2. Realized Gross Margin from Sales (Selling Price - Purchase Price)
$profitStmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(csi.quantity * (csi.unit_price - s.per_piece_price)), 0) AS month_gross_margin,
        COALESCE(SUM(csi.line_total), 0) AS month_counter_revenue
    FROM counter_sale_items csi
    INNER JOIN counter_sales cs ON csi.sale_id = cs.id
    INNER JOIN stock_intake s ON csi.intake_id = s.id
    WHERE YEAR(cs.created_at) = ? AND MONTH(cs.created_at) = ?
");
$profitStmt->execute([$selectedYear, $selectedMonth]);
$profitData = $profitStmt->fetch(PDO::FETCH_ASSOC);

$monthGrossMargin    = (float)$profitData['month_gross_margin'];
$monthCounterRevenue = (float)$profitData['month_counter_revenue'];

// 3. Monthly Operational Expenses & Staff Salaries Breakdown
$monthExpenseTotal = 0.00;
$monthStaffSalaries = 0.00;
$catExpenses = [];
try {
    // Total expenses of month
    $expStmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total_exp 
        FROM expenses 
        WHERE YEAR(expense_date) = ? AND MONTH(expense_date) = ?
    ");
    $expStmt->execute([$selectedYear, $selectedMonth]);
    $monthExpenseTotal = (float)$expStmt->fetchColumn();

    // Specific Staff Salary Outflow
    $salStmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) 
        FROM expenses 
        WHERE category = 'Staff Salary' AND YEAR(expense_date) = ? AND MONTH(expense_date) = ?
    ");
    $salStmt->execute([$selectedYear, $selectedMonth]);
    $monthStaffSalaries = (float)$salStmt->fetchColumn();

    // Categorized breakdown for chart
    $catExpStmt = $conn->prepare("
        SELECT category, COALESCE(SUM(amount), 0) AS total_cat
        FROM expenses
        WHERE YEAR(expense_date) = ? AND MONTH(expense_date) = ?
        GROUP BY category
        ORDER BY total_cat DESC
    ");
    $catExpStmt->execute([$selectedYear, $selectedMonth]);
    $catExpenses = $catExpStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $monthExpenseTotal = 0.00;
    $monthStaffSalaries = 0.00;
}

// 4. Vendor Dues & Intake Balances
$vendorDuesStmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(total_amount), 0) AS month_intake_total,
        COALESCE(SUM(paid_amount), 0) AS month_intake_paid,
        COALESCE(SUM(total_amount - paid_amount), 0) AS month_vendor_due
    FROM stock_intake
    WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?
");
$vendorDuesStmt->execute([$selectedYear, $selectedMonth]);
$vendorData = $vendorDuesStmt->fetch(PDO::FETCH_ASSOC);

$monthIntakeTotal = (float)$vendorData['month_intake_total'];
$monthIntakePaid  = (float)$vendorData['month_intake_paid'];
$monthVendorDue   = (float)$vendorData['month_vendor_due'];

// Lifetime Vendor Pending Dues
$lifetimeVendorDue = (float)$conn->query("
    SELECT COALESCE(SUM(total_amount - paid_amount), 0) 
    FROM stock_intake 
    WHERE payment_status != 'paid'
")->fetchColumn();

// 5. TRUE REALIZED TAKE-HOME PROFIT (Sales Gross Margin minus All Store Expenses)
$monthNetTakeHomeProfit = $monthGrossMargin - $monthExpenseTotal;

// 6. Stock Intake Counts
$metricStmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(stock_pieces), 0) AS total_intake,
        COALESCE(SUM(remaining_pieces), 0) AS live_remaining,
        COUNT(id) AS batch_count
    FROM stock_intake
    WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?
");
$metricStmt->execute([$selectedYear, $selectedMonth]);
$monthlyMetrics = $metricStmt->fetch(PDO::FETCH_ASSOC);

$totalIntakeUnits  = (int)$monthlyMetrics['total_intake'];
$totalLiveUnits    = (int)$monthlyMetrics['live_remaining'];
$totalUnitsSold    = max(0, $totalIntakeUnits - $totalLiveUnits);

// 7. Today's Late Attendees (Without Approved Concession)
$todayLateAttendees = $conn->query("
    SELECT 
        sa.*, 
        sm.name AS staff_name, 
        sm.staff_code, 
        sm.designation
    FROM staff_attendance sa
    JOIN staff_members sm ON sm.id = sa.staff_id
    WHERE DATE(sa.punch_time) = CURRENT_DATE()
      AND (
          NOT (
              (TIME(sa.punch_time) BETWEEN '09:00:00' AND '10:00:00') OR 
              (TIME(sa.punch_time) BETWEEN '20:00:00' AND '21:00:00')
          )
      )
      AND sa.is_concession = 0
    ORDER BY sa.punch_time DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 8. Detailed Batch Ledger with Realized Profit per Item Batch
$ledgerStmt = $conn->prepare("
    SELECT 
        s.id AS intake_id,
        s.vendor_name,
        s.company,
        s.name,
        s.model,
        s.stock_pieces,
        s.remaining_pieces,
        (s.stock_pieces - s.remaining_pieces) AS sold_pieces,
        s.per_piece_price,
        s.counter_price,
        s.total_amount,
        s.paid_amount,
        (s.total_amount - s.paid_amount) AS vendor_balance,
        s.payment_status,
        s.status,
        s.created_at,
        p_pub.selling_price AS public_price,
        p_pub.discount AS public_discount,
        COALESCE((
            SELECT SUM(csi.quantity * (csi.unit_price - s.per_piece_price))
            FROM counter_sale_items csi
            WHERE csi.intake_id = s.id
        ), 0) AS batch_realized_profit
    FROM stock_intake s
    LEFT JOIN public_products p_pub ON s.product_id = p_pub.product_id
    WHERE YEAR(s.created_at) = ? AND MONTH(s.created_at) = ?
    ORDER BY s.id DESC
");
$ledgerStmt->execute([$selectedYear, $selectedMonth]);
$ledgerRecords = $ledgerStmt->fetchAll(PDO::FETCH_ASSOC);

// 9. Daily Sales Revenue Trend
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
$dailyRevenueMap = array_fill(1, $daysInMonth, 0.0);

$dailyStmt = $conn->prepare("
    SELECT DAY(created_at) AS sale_day, SUM(grand_total) AS daily_rev
    FROM counter_sales
    WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?
    GROUP BY DAY(created_at)
");
$dailyStmt->execute([$selectedYear, $selectedMonth]);
while ($r = $dailyStmt->fetch(PDO::FETCH_ASSOC)) {
    $dailyRevenueMap[(int)$r['sale_day']] = (float)$r['daily_rev'];
}
$chartLabels = array_keys($dailyRevenueMap);
$chartValues = array_values($dailyRevenueMap);

$availableYears = $conn->query("
    SELECT DISTINCT YEAR(created_at) AS intake_year 
    FROM stock_intake 
    ORDER BY intake_year DESC
")->fetchAll(PDO::FETCH_COLUMN) ?: [(int)date('Y')];
if (!in_array((int)date('Y'), $availableYears)) {
    array_unshift($availableYears, (int)date('Y'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Overview & Profit Ledger - SAI GANAPATHI</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Privacy-friendly CDN mirror without storage tracking issues -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>

    <!-- Global Sidebar Toggle Functions Loaded Before DOM Calls -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('mainSidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (!sidebar) return;

            const isDesktop = window.innerWidth >= 1024;

            if (isDesktop) {
                sidebar.classList.toggle('lg:hidden');
            } else {
                sidebar.classList.toggle('-translate-x-full');
                if (backdrop) backdrop.classList.toggle('hidden');
            }

            const isHidden = sidebar.classList.contains('lg:hidden') || sidebar.classList.contains('-translate-x-full');
            try {
                localStorage.setItem('sidebar_collapsed', isHidden ? '1' : '0');
            } catch (e) {}
        }

        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('mainSidebar');
            if (!sidebar) return;

            if (window.innerWidth < 1024) {
                sidebar.classList.add('-translate-x-full');
            } else {
                try {
                    if (localStorage.getItem('sidebar_collapsed') === '1') {
                        sidebar.classList.add('lg:hidden');
                    }
                } catch (e) {}
            }
        });
    </script>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800 antialiased flex">

    <?php include 'sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-x-hidden overflow-y-auto min-h-screen">
        
        <!-- Header with 3-Dots Sidebar Toggle -->
        <header class="bg-white/95 backdrop-blur-md border-b border-slate-200/80 sticky top-0 z-30 px-4 sm:px-6 py-3.5 flex flex-col md:flex-row md:items-center md:justify-between gap-3 shadow-xs">
            <div class="flex items-center gap-3 min-w-0">
                <button type="button" 
                        onclick="toggleSidebar()" 
                        title="Toggle Sidebar Navigation" 
                        class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-amber-400 hover:text-slate-950 text-slate-700 flex items-center justify-center text-lg font-black transition-all cursor-pointer shrink-0 border border-slate-200/80 shadow-xs">
                    <span class="leading-none select-none">&#x22EE;</span>
                </button>

                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse shrink-0"></span>
                        <h2 class="text-sm sm:text-base md:text-lg font-black text-slate-900 tracking-tight truncate">
                            Executive Overview & Profit Ledger: <?= $monthName ?> <?= $selectedYear ?>
                        </h2>
                    </div>
                    <p class="text-[11px] text-slate-500 font-medium truncate">
                        Realized Gross Sales Margins, Vendor Dues, Staff Salaries & Store Accounting
                    </p>
                </div>
            </div>
            
            <div class="flex items-center gap-2 shrink-0 self-end md:self-auto">
                <a href="expenses.php" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200/80 text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1.5">
                    <span>💸</span>
                    <span class="hidden sm:inline">Expenses</span>
                </a>
                <a href="attendance_logs.php" class="px-3 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200/80 text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1.5">
                    <span>⏱️</span>
                    <span class="hidden sm:inline">Staff Logs</span>
                </a>
                <a href="../counter/index.php" target="_blank" class="px-3.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-amber-300 text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1.5">
                    <span>⚡</span>
                    <span>POS Desk ↗</span>
                </a>
            </div>
        </header>

        <main class="p-4 sm:p-6 max-w-7xl w-full mx-auto space-y-6">

            <!-- Month / Year Selector Filter -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <form method="GET" action="admin_dashboard.php" class="flex items-center gap-2.5 flex-wrap">
                    <span class="text-xs font-black uppercase text-slate-500">Period:</span>
                    <select name="month" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:border-indigo-500">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= ($m === $selectedMonth) ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>

                    <select name="year" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:border-indigo-500">
                        <?php foreach ($availableYears as $yr): ?>
                            <option value="<?= $yr ?>" <?= ($yr === $selectedYear) ? 'selected' : '' ?>><?= $yr ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-amber-300 font-bold text-xs rounded-xl transition cursor-pointer">
                        Filter Overview
                    </button>

                    <?php if ($selectedMonth !== (int)date('m') || $selectedYear !== (int)date('Y')): ?>
                        <a href="admin_dashboard.php" class="px-3 py-2 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200/80 font-bold text-xs rounded-xl transition">
                            Back to Current Month
                        </a>
                    <?php endif; ?>
                </form>

                <div class="relative w-full md:w-72">
                    <input type="text" id="ledgerSearch" placeholder="Search product, model, or vendor..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:border-indigo-500">
                    <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
                </div>
            </div>

            <!-- PRIMARY FINANCIAL AUDIT: Realized Gross Profit, Expenses, Salaries, & Net Take-Home -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <!-- 1. Gross Realized Margin -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-[10px] uppercase font-extrabold tracking-wider">Gross Sales Margin</span>
                        <span class="p-1.5 bg-blue-50 text-blue-700 rounded-lg text-xs">📈</span>
                    </div>
                    <span class="text-2xl font-black text-slate-900 mt-2 block font-mono">
                        ₹<?= number_format($monthGrossMargin, 2) ?>
                    </span>
                    <span class="text-[11px] text-slate-400 font-medium mt-1 block">
                        Sales Price - Purchase Cost
                    </span>
                </div>

                <!-- 2. Deducted Store Expenses -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-[10px] uppercase font-extrabold tracking-wider text-rose-600">Total Overhead Expenses</span>
                        <span class="p-1.5 bg-rose-50 text-rose-700 rounded-lg text-xs">💸</span>
                    </div>
                    <span class="text-2xl font-black text-rose-600 mt-2 block font-mono">
                        -₹<?= number_format($monthExpenseTotal, 2) ?>
                    </span>
                    <span class="text-[11px] text-rose-500/80 font-medium mt-1 block">
                        Includes Rent, Power, Tech & Salaries
                    </span>
                </div>

                <!-- 3. Staff Salary Outflow -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-[10px] uppercase font-extrabold tracking-wider text-amber-700">Staff Salaries Paid</span>
                        <span class="p-1.5 bg-amber-50 text-amber-700 rounded-lg text-xs">👥</span>
                    </div>
                    <span class="text-2xl font-black text-amber-700 mt-2 block font-mono">
                        ₹<?= number_format($monthStaffSalaries, 2) ?>
                    </span>
                    <span class="text-[11px] text-amber-600 font-medium mt-1 block">
                        Monthly Team Payroll Total
                    </span>
                </div>

                <!-- 4. TRUE REALIZED NET PROFIT -->
                <div class="bg-white p-5 rounded-2xl border <?= $monthNetTakeHomeProfit >= 0 ? 'border-emerald-200 bg-emerald-50/20' : 'border-rose-200 bg-rose-50/20' ?> shadow-xs">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-[10px] uppercase font-extrabold tracking-wider <?= $monthNetTakeHomeProfit >= 0 ? 'text-emerald-800' : 'text-rose-800' ?>">Net Store Profit</span>
                        <span class="p-1.5 <?= $monthNetTakeHomeProfit >= 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' ?> rounded-lg text-xs">💵</span>
                    </div>
                    <span class="text-2xl font-black <?= $monthNetTakeHomeProfit >= 0 ? 'text-emerald-600' : 'text-rose-600' ?> mt-2 block font-mono">
                        <?= ($monthNetTakeHomeProfit >= 0 ? '+' : '') ?>₹<?= number_format($monthNetTakeHomeProfit, 2) ?>
                    </span>
                    <span class="text-[11px] <?= $monthNetTakeHomeProfit >= 0 ? 'text-emerald-700' : 'text-rose-700' ?> font-bold mt-1 block">
                        (Gross Margin - Expenses)
                    </span>
                </div>

            </div>

            <!-- Operational Volume, Vendor Dues & Intake Metrics -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Today's POS Sales</span>
                    <span class="text-xl font-black text-slate-900 mt-1 block font-mono">₹<?= number_format($todaySales['today_revenue'], 2) ?></span>
                    <span class="text-[10px] text-emerald-600 font-bold mt-0.5 block"><?= (int)$todaySales['today_bills'] ?> invoices created today</span>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider"><?= $monthName ?> Sales Revenue</span>
                    <span class="text-xl font-black text-indigo-700 mt-1 block font-mono">₹<?= number_format($monthCounterRevenue, 2) ?></span>
                    <span class="text-[10px] text-slate-500 mt-0.5 block">Total counter invoices</span>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider"><?= $monthName ?> Vendor Pending</span>
                    <span class="text-xl font-black text-rose-600 mt-1 block font-mono">₹<?= number_format($monthVendorDue, 2) ?></span>
                    <span class="text-[10px] text-rose-500 font-bold mt-0.5 block">Due from <?= number_format($monthIntakeTotal, 0) ?> intake</span>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Lifetime Vendor Balance</span>
                    <span class="text-xl font-black text-purple-700 mt-1 block font-mono">₹<?= number_format($lifetimeVendorDue, 2) ?></span>
                    <span class="text-[10px] text-purple-600 font-bold mt-0.5 block">Pending across all shipments</span>
                </div>
            </div>

            <!-- Visual Charts Matrix -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                
                <!-- Daily Revenue Chart (2 Cols) -->
                <div class="lg:col-span-2 bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                    <div class="flex items-center justify-between pb-3 mb-2 border-b border-slate-100">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Daily Sales Revenue (₹)</h3>
                            <p class="text-[11px] text-slate-400">Day-by-day counter receipts for <?= $monthName ?> <?= $selectedYear ?></p>
                        </div>
                    </div>
                    <div class="h-64 relative">
                        <canvas id="dailyRevenueChart"></canvas>
                    </div>
                </div>

                <!-- Monthly Expenses Breakdown (1 Col) -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                    <div class="pb-3 mb-2 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Overhead Spending</h3>
                            <p class="text-[11px] text-slate-400">Category cost distributions</p>
                        </div>
                        <a href="expenses.php" class="text-[10px] text-indigo-600 font-bold hover:underline">View All →</a>
                    </div>
                    <div class="h-64 flex items-center justify-center relative">
                        <canvas id="expensesChart"></canvas>
                    </div>
                </div>

            </div>

            <!-- Item-Wise Profit, Intake & Vendor Ledger Table -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-black uppercase tracking-wider text-slate-900">
                            Batch Intake, Vendor Dues & Realized Gross Profit: <?= $monthName ?> <?= $selectedYear ?>
                        </h3>
                        <p class="text-xs text-slate-400">Per-unit gross profit realization and vendor pending balances</p>
                    </div>
                    <span class="text-xs font-bold text-slate-500 bg-slate-100 px-3 py-1 rounded-full">
                        <?= count($ledgerRecords) ?> Batches Logged
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50/80 text-slate-500 font-extrabold uppercase text-[10px] tracking-wider border-b border-slate-200">
                                <th class="p-3.5">Batch / Vendor</th>
                                <th class="p-3.5">Product & Model</th>
                                <th class="p-3.5 text-right">Cost / Unit</th>
                                <th class="p-3.5 text-right">Selling Price</th>
                                <th class="p-3.5 text-center">Unit Margin</th>
                                <th class="p-3.5 text-center">Stock (Rem / Sold)</th>
                                <th class="p-3.5 text-right">Vendor Due</th>
                                <th class="p-3.5 text-right">Realized Profit</th>
                                <th class="p-3.5 text-right">Floor Adj.</th>
                            </tr>
                        </thead>
                        <tbody id="ledgerTableBody" class="divide-y divide-slate-100">
                            <?php if (empty($ledgerRecords)): ?>
                                <tr>
                                    <td colspan="9" class="p-12 text-center text-slate-400 font-semibold">
                                        No intake batches recorded for <?= $monthName ?> <?= $selectedYear ?>.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ledgerRecords as $row): 
                                    $costPrice = (float)$row['per_piece_price'];
                                    
                                    // Determine Effective Selling Price
                                    if ((float)$row['counter_price'] > 0) {
                                        $sellingPrice = (float)$row['counter_price'];
                                    } elseif (!empty($row['public_price'])) {
                                        $mrp = (float)$row['public_price'];
                                        $disc = (float)$row['public_discount'];
                                        $sellingPrice = $mrp - ($mrp * ($disc / 100));
                                    } else {
                                        $sellingPrice = $costPrice * 1.15;
                                    }

                                    $unitMargin = $sellingPrice - $costPrice;
                                    $batchProfit = (float)$row['batch_realized_profit'];
                                    $isOut = ($row['remaining_pieces'] <= 0);
                                    $vBalance = (float)$row['vendor_balance'];
                                ?>
                                    <tr class="ledger-row hover:bg-slate-50/80 transition">
                                        
                                        <td class="p-3.5 whitespace-nowrap">
                                            <span class="font-mono text-slate-500 font-bold block text-[11px]">#<?= str_pad($row['intake_id'], 4, '0', STR_PAD_LEFT) ?></span>
                                            <span class="font-bold text-slate-800 block text-[11px]"><?= htmlspecialchars($row['vendor_name']) ?></span>
                                            <span class="text-[10px] text-slate-400 block"><?= date('d M Y', strtotime($row['created_at'])) ?></span>
                                        </td>

                                        <td class="p-3.5">
                                            <span class="text-[9px] font-black uppercase text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100">
                                                <?= htmlspecialchars($row['company']) ?>
                                            </span>
                                            <p class="font-bold text-slate-900 text-xs mt-1 leading-snug"><?= htmlspecialchars($row['name']) ?></p>
                                            <span class="font-mono text-[10px] text-slate-500 block">Model: <?= htmlspecialchars($row['model']) ?></span>
                                        </td>

                                        <td class="p-3.5 text-right whitespace-nowrap font-mono">
                                            <span class="font-bold text-slate-700 block">₹<?= number_format($costPrice, 2) ?></span>
                                            <span class="text-[9px] text-slate-400">Total: ₹<?= number_format($row['total_amount'], 2) ?></span>
                                        </td>

                                        <td class="p-3.5 text-right whitespace-nowrap font-mono">
                                            <span class="font-bold text-slate-900 block">₹<?= number_format($sellingPrice, 2) ?></span>
                                            <?php if ((float)$row['counter_price'] > 0): ?>
                                                <span class="text-[9px] text-indigo-600 font-bold">Counter Fixed</span>
                                            <?php else: ?>
                                                <span class="text-[9px] text-slate-400">Showcase Price</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="p-3.5 text-center whitespace-nowrap font-mono">
                                            <span class="font-extrabold <?= $unitMargin >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>">
                                                <?= $unitMargin >= 0 ? '+' : '' ?>₹<?= number_format($unitMargin, 2) ?>
                                            </span>
                                            <span class="block text-[9px] text-slate-400">
                                                <?= $costPrice > 0 ? number_format(($unitMargin / $costPrice) * 100, 1) : 0 ?>%
                                            </span>
                                        </td>

                                        <td class="p-3.5 text-center whitespace-nowrap font-mono">
                                            <span class="font-black <?= $isOut ? 'text-rose-600' : 'text-blue-600' ?>">
                                                <?= $row['remaining_pieces'] ?> Left
                                            </span>
                                            <span class="block text-[10px] text-slate-500 font-bold">
                                                <?= $row['sold_pieces'] ?> Sold / <?= $row['stock_pieces'] ?> Total
                                            </span>
                                        </td>

                                        <td class="p-3.5 text-right whitespace-nowrap font-mono">
                                            <?php if ($vBalance > 0): ?>
                                                <span class="text-xs font-black text-rose-600 block">
                                                    ₹<?= number_format($vBalance, 2) ?>
                                                </span>
                                                <span class="text-[9px] font-bold text-rose-500 uppercase">Pending</span>
                                            <?php else: ?>
                                                <span class="text-xs font-black text-emerald-600 block">₹0.00</span>
                                                <span class="text-[9px] font-bold text-emerald-600 uppercase">Settled</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="p-3.5 text-right whitespace-nowrap font-mono">
                                            <span class="text-xs font-black <?= $batchProfit > 0 ? 'text-emerald-600' : 'text-slate-500' ?> block">
                                                ₹<?= number_format($batchProfit, 2) ?>
                                            </span>
                                            <span class="text-[9px] text-slate-400 block font-sans">Gross Realized</span>
                                        </td>

                                        <td class="p-3.5 text-right whitespace-nowrap">
                                            <form method="POST" action="admin_dashboard.php?month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>" class="inline-flex items-center gap-1 justify-end">
                                                <input type="hidden" name="action" value="quick_adjust_stock">
                                                <input type="hidden" name="intake_id" value="<?= $row['intake_id'] ?>">
                                                <input type="number" name="remaining_pieces" value="<?= $row['remaining_pieces'] ?>" min="0" max="<?= $row['stock_pieces'] ?>" class="w-12 px-1.5 py-0.5 bg-slate-50 border border-slate-200 rounded text-center text-xs font-bold">
                                                <button type="submit" class="p-1 bg-slate-100 hover:bg-slate-200 rounded text-xs transition cursor-pointer">💾</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TODAY'S LATE ATTENDANCE AUDIT ALERT BANNER (Below All Ledgers) -->
            <div class="bg-white rounded-2xl border border-rose-200 p-5 shadow-xs">
                <div class="flex items-center justify-between pb-3 border-b border-rose-100">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-rose-500 animate-ping"></span>
                        <h3 class="text-xs font-black uppercase tracking-wider text-rose-700">
                            Today's Late / Restricted Attendance Alerts
                        </h3>
                    </div>
                    <span class="text-[11px] font-bold text-rose-600 bg-rose-50 border border-rose-200 px-2.5 py-0.5 rounded-full">
                        <?= count($todayLateAttendees) ?> Unauthorized Punches
                    </span>
                </div>

                <?php if (empty($todayLateAttendees)): ?>
                    <div class="py-6 text-center text-slate-400 text-xs font-semibold">
                        ✅ Excellent! All staff punches today are strictly on-time or have approved concessions.
                    </div>
                <?php else: ?>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="text-slate-400 font-bold uppercase text-[10px] border-b border-slate-100">
                                    <th class="py-2 px-3">Staff Member</th>
                                    <th class="py-2 px-3">Designation</th>
                                    <th class="py-2 px-3">Punch Type</th>
                                    <th class="py-2 px-3">Punch Time</th>
                                    <th class="py-2 px-3">Reason / Status</th>
                                    <th class="py-2 px-3 text-right">Admin Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 font-mono">
                                <?php foreach ($todayLateAttendees as $late): ?>
                                    <tr class="hover:bg-rose-50/40 transition">
                                        <td class="py-2.5 px-3">
                                            <span class="font-bold text-slate-900 block"><?= htmlspecialchars($late['staff_name']) ?></span>
                                            <span class="text-[10px] text-slate-400"><?= htmlspecialchars($late['staff_code']) ?></span>
                                        </td>
                                        <td class="py-2.5 px-3 text-slate-600 font-sans">
                                            <?= htmlspecialchars($late['designation']) ?>
                                        </td>
                                        <td class="py-2.5 px-3">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-slate-100 text-slate-800">
                                                <?= strtoupper($late['punch_type']) ?>
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-3 font-bold text-rose-600">
                                            <?= date('h:i:s A', strtotime($late['punch_time'])) ?>
                                        </td>
                                        <td class="py-2.5 px-3 font-sans">
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-rose-50 text-rose-700 border border-rose-300">
                                                Outside Allowed Slots
                                            </span>
                                        </td>
                                        <td class="py-2.5 px-3 text-right font-sans">
                                            <a href="attendance_logs.php?log_date=<?= date('Y-m-d') ?>&staff_id=<?= $late['staff_id'] ?>" class="px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-[10px] font-bold shadow-xs transition inline-block">
                                                Grant Concession ↗
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <!-- Chart Configuration Script -->
    <script>
        // 1. Daily Revenue Bar Chart
        const dailyLabels = <?= json_encode($chartLabels) ?>;
        const dailyValues = <?= json_encode($chartValues) ?>;

        const ctxDailyElem = document.getElementById('dailyRevenueChart');
        if (ctxDailyElem) {
            new Chart(ctxDailyElem.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: dailyLabels,
                    datasets: [{
                        label: 'Daily Revenue (₹)',
                        data: dailyValues,
                        backgroundColor: 'rgba(79, 70, 229, 0.85)',
                        borderRadius: 6,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false } },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(val) { return '₹' + val.toLocaleString('en-IN'); }
                            }
                        }
                    }
                }
            });
        }

        // 2. Expenses Category Breakdown Chart
        const expLabels = <?= json_encode(array_column($catExpenses, 'category')) ?>;
        const expAmounts = <?= json_encode(array_column($catExpenses, 'total_cat')) ?>;

        const ctxExpElem = document.getElementById('expensesChart');
        if (ctxExpElem) {
            new Chart(ctxExpElem.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: expLabels.length ? expLabels : ['No Expenses Logged'],
                    datasets: [{
                        data: expAmounts.length ? expAmounts : [1],
                        backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#8b5cf6', '#10b981', '#64748b']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } }
                    }
                }
            });
        }

        // Search Filter for Item Ledger
        const ledgerSearchInput = document.getElementById('ledgerSearch');
        if (ledgerSearchInput) {
            ledgerSearchInput.addEventListener('input', function() {
                const term = this.value.toLowerCase().trim();
                document.querySelectorAll('.ledger-row').forEach(r => {
                    r.classList.toggle('hidden', !r.innerText.toLowerCase().includes(term));
                });
            });
        }
    </script>
</body>
</html>