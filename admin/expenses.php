<?php
session_start();

if (!isset($_SESSION['user_logged']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../db.php';

$currentPage = 'expenses';
$success = '';
$error = '';

// Filters
$filterMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$filterYear  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$filterCategory = trim($_GET['category'] ?? 'all');

if ($filterMonth < 1 || $filterMonth > 12) $filterMonth = (int)date('m');
if ($filterYear < 2020 || $filterYear > 2040) $filterYear = (int)date('Y');
$monthName = date('F', mktime(0, 0, 0, $filterMonth, 10));

// Handle Adding Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_expense') {
    $category  = trim($_POST['category'] ?? '');
    $title     = trim($_POST['title'] ?? '');
    $amount    = (float)($_POST['amount'] ?? 0);
    $expDate   = trim($_POST['expense_date'] ?? date('Y-m-d'));
    $payMode   = $_POST['payment_mode'] ?? 'upi';
    $recipient = trim($_POST['recipient_name'] ?? '');
    $refNo     = trim($_POST['reference_no'] ?? '');
    $notes     = trim($_POST['notes'] ?? '');

    if (empty($category) || empty($title) || $amount <= 0) {
        $error = "Category, Description Title, and a valid Amount greater than ₹0 are required.";
    } else {
        $receiptPath = null;
        if (!empty($_FILES['receipt_photo']['name'])) {
            $uploadDir = "../uploads/expenses/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $filename = 'exp_' . time() . '_' . preg_replace("/[^a-zA-Z0-9._-]/", "", basename($_FILES['receipt_photo']['name']));
            $target = $uploadDir . $filename;
            $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));

            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'])) {
                if (move_uploaded_file($_FILES['receipt_photo']['tmp_name'], $target)) {
                    $receiptPath = 'uploads/expenses/' . $filename;
                }
            }
        }

        try {
            $stmt = $conn->prepare("
                INSERT INTO expenses (category, title, amount, expense_date, payment_mode, recipient_name, reference_no, receipt_photo, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$category, $title, $amount, $expDate, $payMode, $recipient, $refNo, $receiptPath, $notes]);
            $success = "Expense entry of ₹" . number_format($amount, 2) . " logged successfully!";
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Handle Delete Expense
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $conn->prepare("DELETE FROM expenses WHERE id = ?")->execute([$delId]);
    header("Location: expenses.php?month={$filterMonth}&year={$filterYear}&deleted=1");
    exit;
}

// Scoped Monthly Aggregates
$sumStmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(amount), 0) AS total_month_expense,
        COUNT(id) AS total_entries
    FROM expenses 
    WHERE YEAR(expense_date) = ? AND MONTH(expense_date) = ?
");
$sumStmt->execute([$filterYear, $filterMonth]);
$monthKpi = $sumStmt->fetch();
$totalMonthExpense = (float)$monthKpi['total_month_expense'];

// Categorized Breakdown for Month
$catBreakdownStmt = $conn->prepare("
    SELECT category, COALESCE(SUM(amount), 0) AS cat_total
    FROM expenses 
    WHERE YEAR(expense_date) = ? AND MONTH(expense_date) = ?
    GROUP BY category
    ORDER BY cat_total DESC
");
$catBreakdownStmt->execute([$filterYear, $filterMonth]);
$catTotals = $catBreakdownStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// List Records
$queryParts = ["YEAR(expense_date) = ?", "MONTH(expense_date) = ?"];
$queryParams = [$filterYear, $filterMonth];

if ($filterCategory !== 'all') {
    $queryParts[] = "category = ?";
    $queryParams[] = $filterCategory;
}

$whereSql = "WHERE " . implode(" AND ", $queryParts);
$recordsStmt = $conn->prepare("SELECT * FROM expenses {$whereSql} ORDER BY expense_date DESC, id DESC");
$recordsStmt->execute($queryParams);
$expenseList = $recordsStmt->fetchAll();

$categories = [
    'Staff Salary'        => '👥',
    'Showroom Rent'       => '🏢',
    'Electricity / Power' => '⚡',
    'Labour / Unloading'  => '📦',
    'Store Maintenance'   => '🛠️',
    'Website & Tech'      => '🌐',
    'Transport / Freight' => '🚚',
    'Other'               => '📝'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Expense Ledger - SAI GANAPATHI HOME NEEDS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800 antialiased flex">

    <?php include 'sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <header class="bg-white/95 backdrop-blur-md border-b border-slate-200 sticky top-0 z-30 px-6 py-4 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-3">
                <button type="button" onclick="toggleSidebar()" class="lg:hidden p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <div>
                    <h2 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">Showroom Expenses & Overhead Ledger</h2>
                    <p class="text-xs text-slate-500 font-medium">Record staff payroll, showroom rent, power bills, site tech, and maintenance</p>
                </div>
            </div>
            <span class="text-xs font-bold text-rose-700 bg-rose-50 border border-rose-200 px-3 py-1.5 rounded-xl font-mono">
                <?= $monthName ?> Total: ₹<?= number_format($totalMonthExpense, 2) ?>
            </span>
        </header>

        <main class="p-6 max-w-7xl w-full mx-auto space-y-6">

            <?php if (!empty($success) || isset($_GET['deleted'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-bold shadow-xs">
                    ✅ <?= !empty($success) ? htmlspecialchars($success) : "Expense record deleted successfully!" ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-2xl text-xs font-bold shadow-xs">
                    ⚠️ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Category Spend Summary -->
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2.5">
                <?php foreach ($categories as $catName => $catIcon): 
                    $catAmount = $catTotals[$catName] ?? 0;
                ?>
                    <div class="bg-white p-3 rounded-xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                        <span class="text-base"><?= $catIcon ?></span>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tight block mt-1 leading-tight truncate"><?= $catName ?></span>
                        <span class="text-xs font-black font-mono mt-1 <?= $catAmount > 0 ? 'text-rose-600' : 'text-slate-400' ?>">
                            ₹<?= number_format($catAmount, 0) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Filter Console -->
            <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-3">
                <form method="GET" action="expenses.php" class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-black uppercase text-slate-500">Period:</span>
                    <select name="month" class="px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $m === $filterMonth ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>

                    <select name="year" class="px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
                        <?php for ($y = 2024; $y <= 2028; $y++): ?>
                            <option value="<?= $y ?>" <?= $y === $filterYear ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>

                    <select name="category" class="px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
                        <option value="all">All Expense Categories</option>
                        <?php foreach (array_keys($categories) as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= $filterCategory === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="px-3.5 py-1.5 bg-slate-900 text-amber-300 font-bold text-xs rounded-xl cursor-pointer">
                        Filter
                    </button>
                </form>

                <input type="text" id="expenseSearch" placeholder="Search entries..." class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs w-full md:w-64">
            </div>

            <!-- Two-Column Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- Left 4 Cols: Add Expense Form -->
                <div class="lg:col-span-4 bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs sticky top-24">
                    <div class="pb-3 mb-4 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Record Expenditure</h3>
                        <span class="text-[10px] font-bold bg-rose-50 text-rose-700 px-2 py-0.5 rounded">Payout</span>
                    </div>

                    <form method="POST" action="expenses.php" enctype="multipart/form-data" class="space-y-3">
                        <input type="hidden" name="action" value="add_expense">

                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Expense Category *</label>
                            <select name="category" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-rose-500">
                                <option value="">-- Choose Category --</option>
                                <?php foreach ($categories as $catName => $catIcon): ?>
                                    <option value="<?= htmlspecialchars($catName) ?>">
                                        <?= $catIcon ?> <?= htmlspecialchars($catName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Description / Title *</label>
                            <input type="text" name="title" required placeholder="e.g. Counter Staff Salary (Raju)" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Amount (₹) *</label>
                                <input type="number" step="0.01" min="1" name="amount" required placeholder="5000.00" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold font-mono focus:outline-none focus:border-rose-500">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Payment Date *</label>
                                <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Payment Mode</label>
                                <select name="payment_mode" class="w-full px-2.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:border-rose-500">
                                    <option value="upi">UPI / GPay / PhonePe</option>
                                    <option value="cash">Cash In Hand</option>
                                    <option value="bank_transfer">Bank Transfer / NEFT</option>
                                    <option value="cheque">Cheque</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Paid To / Recipient</label>
                                <input type="text" name="recipient_name" placeholder="Name or Company" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Receipt / Proof</label>
                                <input type="file" name="receipt_photo" accept="image/*,.pdf" class="w-full text-[10px] text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-[10px] file:font-bold file:bg-slate-900 file:text-white cursor-pointer">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Ref / UTR #</label>
                                <input type="text" name="reference_no" placeholder="UPI Ref / Cheque #" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono focus:outline-none focus:border-rose-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Notes / Ledger Remarks</label>
                            <textarea name="notes" rows="2" placeholder="e.g. Paid for August 2026 month showroom rent" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-rose-500"></textarea>
                        </div>

                        <button type="submit" class="w-full py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-xs transition cursor-pointer">
                            - Record Expense
                        </button>
                    </form>
                </div>

                <!-- Right 8 Cols: Expense Ledger Table -->
                <div class="lg:col-span-8 bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900"><?= $monthName ?> <?= $filterYear ?> Expenditure</h3>
                            <p class="text-[11px] text-slate-400">Total <?= count($expenseList) ?> recorded transactions</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50/80 text-slate-500 font-bold uppercase text-[10px] tracking-wider border-b border-slate-200/80">
                                    <th class="p-3.5">Date</th>
                                    <th class="p-3.5">Category & Description</th>
                                    <th class="p-3.5 text-right">Amount</th>
                                    <th class="p-3.5 text-center">Mode</th>
                                    <th class="p-3.5 text-center">Proof</th>
                                    <th class="p-3.5 text-right">Manage</th>
                                </tr>
                            </thead>
                            <tbody id="expenseTableBody" class="divide-y divide-slate-100">
                                <?php if (empty($expenseList)): ?>
                                    <tr>
                                        <td colspan="6" class="p-8 text-center text-slate-400 font-semibold">No expenses recorded for <?= $monthName ?> <?= $filterYear ?>.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($expenseList as $exp): ?>
                                        <tr class="expense-row hover:bg-slate-50/70 transition">
                                            <td class="p-3.5 whitespace-nowrap">
                                                <span class="font-bold text-slate-800 block"><?= date('d M Y', strtotime($exp['expense_date'])) ?></span>
                                                <span class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($exp['reference_no'] ?: 'No Ref') ?></span>
                                            </td>

                                            <td class="p-3.5">
                                                <span class="text-[10px] font-black uppercase tracking-wider text-slate-700 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded-md inline-block">
                                                    <?= htmlspecialchars($exp['category']) ?>
                                                </span>
                                                <p class="font-bold text-slate-900 text-xs mt-1"><?= htmlspecialchars($exp['title']) ?></p>
                                                <?php if (!empty($exp['recipient_name'])): ?>
                                                    <span class="text-[10px] text-slate-500">Paid to: <strong><?= htmlspecialchars($exp['recipient_name']) ?></strong></span>
                                                <?php endif; ?>
                                            </td>

                                            <td class="p-3.5 text-right whitespace-nowrap font-mono">
                                                <span class="text-xs font-black text-rose-600 block">₹<?= number_format($exp['amount'], 2) ?></span>
                                            </td>

                                            <td class="p-3.5 text-center whitespace-nowrap">
                                                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-slate-100 text-slate-700 border border-slate-200">
                                                    <?= $exp['payment_mode'] ?>
                                                </span>
                                            </td>

                                            <td class="p-3.5 text-center whitespace-nowrap">
                                                <?php if (!empty($exp['receipt_photo'])): ?>
                                                    <a href="../<?= htmlspecialchars($exp['receipt_photo']) ?>" target="_blank" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded text-[10px] font-bold text-indigo-600">
                                                        📄 View Proof
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-[10px] text-slate-300 italic">None</span>
                                                <?php endif; ?>
                                            </td>

                                            <td class="p-3.5 text-right whitespace-nowrap">
                                                <a href="expenses.php?delete=<?= $exp['id'] ?>&month=<?= $filterMonth ?>&year=<?= $filterYear ?>" onclick="return confirm('Delete this expense entry?')" class="text-rose-600 hover:text-rose-800 font-bold text-xs transition">
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

    <script>
        // Sidebar Toggle for Mobile View
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar') || document.querySelector('aside');
            if (sidebar) {
                sidebar.classList.toggle('hidden');
                sidebar.classList.toggle('block');
            }
        }

        // Live Table Search
        document.getElementById('expenseSearch').addEventListener('input', function () {
            const term = this.value.toLowerCase().trim();
            document.querySelectorAll('.expense-row').forEach(row => {
                row.classList.toggle('hidden', !row.innerText.toLowerCase().includes(term));
            });
        });
    </script>
</body>
</html>