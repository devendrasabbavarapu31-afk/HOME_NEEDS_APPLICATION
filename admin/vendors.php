<?php
session_start();

if (!isset($_SESSION['user_logged']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../db.php';

$success = '';
$error = '';

// Handle New Vendor Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_vendor') {
    $vendorName    = trim($_POST['vendor_name'] ?? '');
    $contactPerson = trim($_POST['contact_person'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $address       = trim($_POST['address'] ?? '');

    if (empty($vendorName) || empty($phone)) {
        $error = "Vendor Name and Phone Number are required fields.";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO vendors (vendor_name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$vendorName, $contactPerson, $phone, $email, $address]);
            $success = "Vendor '{$vendorName}' successfully created!";
        } catch (PDOException $e) {
            $error = "Database Error (Vendor name might already exist): " . $e->getMessage();
        }
    }
}

// Handle Delete Vendor
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    try {
        $conn->prepare("DELETE FROM vendors WHERE id = ?")->execute([$delId]);
        header("Location: vendors.php?deleted=1");
        exit;
    } catch (PDOException $e) {
        $error = "Cannot delete vendor: They may have active stock intake bills attached to them.";
    }
}

// Fetch all vendors with aggregated financial stats
$vendorsListQuery = "
    SELECT 
        v.*,
        COUNT(s.id) AS total_batches,
        COALESCE(SUM(s.total_amount), 0) AS lifetime_billed,
        COALESCE(SUM(s.paid_amount), 0) AS lifetime_paid
    FROM vendors v
    LEFT JOIN stock_intake s ON v.vendor_name = s.vendor_name
    GROUP BY v.id
    ORDER BY v.id DESC
";
$vendorsList = $conn->query($vendorsListQuery)->fetchAll();
$totalVendors = count($vendorsList);

// Fetch all stock intake batches grouped by vendor (Embed into page so no AJAX failure can happen)
$allBatches = $conn->query("
    SELECT 
        id, vendor_name, invoice_number, company, name, model, 
        stock_pieces, remaining_pieces, per_piece_price, total_amount, paid_amount, payment_status, created_at 
    FROM stock_intake 
    ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$vendorBillsMap = [];
foreach ($allBatches as $batch) {
    $vName = $batch['vendor_name'];
    if (!isset($vendorBillsMap[$vName])) {
        $vendorBillsMap[$vName] = [];
    }
    $vendorBillsMap[$vName][] = $batch;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Directory & Ledger - SAI GANAPATHI HOME NEEDS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800 antialiased flex">

    <?php 
    $currentPage = 'vendors';
    include 'sidebar.php'; 
    ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <header class="bg-white/95 backdrop-blur-md border-b border-slate-200 sticky top-0 z-30 px-6 py-4 flex items-center justify-between shadow-xs">
            <div>
                <h2 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">Vendor Directory & Payment Ledgers</h2>
                <p class="text-xs text-slate-500 font-medium">Manage suppliers, view individual bill details, and track pending dues</p>
            </div>
            <a href="stock_intake.php" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-xs transition">
                ← Back to Stock Intake
            </a>
        </header>

        <main class="p-6 max-w-7xl w-full mx-auto space-y-6">

            <?php if (!empty($success) || isset($_GET['deleted'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-bold shadow-xs">
                    ✅ <?= !empty($success) ? htmlspecialchars($success) : "Vendor record deleted successfully!" ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-2xl text-xs font-bold shadow-xs">
                    ⚠️ <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                
                <!-- Create Vendor Form -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs sticky top-24">
                    <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Add New Vendor</h3>
                        <span class="text-[10px] bg-indigo-50 text-indigo-700 font-bold px-2 py-0.5 rounded">Supplier</span>
                    </div>

                    <form method="POST" action="vendors.php" class="space-y-3.5">
                        <input type="hidden" name="action" value="add_vendor">

                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Company / Vendor Name *</label>
                            <input type="text" name="vendor_name" required placeholder="e.g. IFB Industries Vizag" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-amber-500">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Contact Person</label>
                            <input type="text" name="contact_person" placeholder="e.g. Mr. Rajesh Kumar" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-amber-500">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Phone Number *</label>
                            <input type="tel" name="phone" required placeholder="e.g. 9876543210" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-amber-500">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Email Address</label>
                            <input type="email" name="email" placeholder="vendor@domain.com" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-amber-500">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Warehouse Address</label>
                            <textarea name="address" rows="2" placeholder="Main Road, Visakhapatnam" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-amber-500"></textarea>
                        </div>

                        <button type="submit" class="w-full mt-2 py-2.5 bg-slate-900 hover:bg-slate-800 text-amber-300 font-bold text-xs uppercase tracking-wider rounded-xl shadow-xs transition cursor-pointer">
                            + Save Vendor
                        </button>
                    </form>
                </div>

                <!-- Vendors Directory Table -->
                <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Registered Suppliers</h3>
                            <p class="text-[11px] text-slate-400">Total <?= $totalVendors ?> active vendors</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50/80 text-slate-500 font-bold uppercase text-[10px] tracking-wider border-b border-slate-200/80">
                                    <th class="p-3.5">Vendor Company</th>
                                    <th class="p-3.5 text-right">Billed / Due</th>
                                    <th class="p-3.5 text-center">Batches</th>
                                    <th class="p-3.5 text-center">Action</th>
                                    <th class="p-3.5 text-right">Manage</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($vendorsList)): ?>
                                    <tr>
                                        <td colspan="5" class="p-8 text-center text-slate-400 font-semibold">No vendors created yet. Use the form to add your first supplier.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($vendorsList as $v): 
                                        $due = max(0, $v['lifetime_billed'] - $v['lifetime_paid']);
                                    ?>
                                        <tr class="hover:bg-slate-50/70 transition">
                                            <td class="p-3.5">
                                                <span class="font-bold text-slate-900 block"><?= htmlspecialchars($v['vendor_name']) ?></span>
                                                <span class="text-[10px] text-slate-400 font-mono">📞 <?= htmlspecialchars($v['phone']) ?></span>
                                            </td>

                                            <td class="p-3.5 text-right whitespace-nowrap font-mono">
                                                <span class="font-black text-slate-900 block">₹<?= number_format($v['lifetime_billed'], 2) ?></span>
                                                <?php if ($due > 0): ?>
                                                    <span class="text-[10px] text-rose-600 font-extrabold block">Due: ₹<?= number_format($due, 2) ?></span>
                                                <?php else: ?>
                                                    <span class="text-[10px] text-emerald-600 font-bold block">✓ Cleared</span>
                                                <?php endif; ?>
                                            </td>

                                            <td class="p-3.5 text-center font-mono font-bold">
                                                <?= $v['total_batches'] ?>
                                            </td>

                                            <td class="p-3.5 text-center whitespace-nowrap">
                                                <button onclick="openVendorDetails('<?= htmlspecialchars(addslashes($v['vendor_name'])) ?>')" 
                                                        class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-extrabold rounded-lg transition cursor-pointer text-[11px]">
                                                    📦 View Bills & Stock
                                                </button>
                                            </td>

                                            <td class="p-3.5 text-right whitespace-nowrap">
                                                <a href="vendors.php?delete=<?= $v['id'] ?>" onclick="return confirm('Delete vendor <?= htmlspecialchars(addslashes($v['vendor_name'])) ?>?')" class="text-rose-600 hover:text-rose-800 font-bold text-xs transition">
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

    <!-- Detailed Vendor Bills & Stock Modal -->
    <div id="vendorModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-3xl w-full p-6 relative max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div>
                    <h3 id="modalVendorTitle" class="text-sm font-black uppercase tracking-wider text-slate-900">Vendor Stock & Bill Ledger</h3>
                    <p class="text-[11px] text-slate-400">Detailed breakdown of stock supplied and payment statuses</p>
                </div>
                <button onclick="closeVendorDetails()" class="text-slate-400 hover:text-slate-700 text-xl font-bold cursor-pointer">&times;</button>
            </div>

            <div id="modalVendorBody" class="py-4 overflow-y-auto flex-1 space-y-3"></div>

            <div class="pt-3 border-t border-slate-100 text-right">
                <button onclick="closeVendorDetails()" class="px-4 py-2 bg-slate-900 text-white text-xs font-bold rounded-xl cursor-pointer">
                    Close Window
                </button>
            </div>
        </div>
    </div>

    <!-- Direct Modal Script (No AJAX failure points) -->
    <script>
        const vendorBillsData = <?= json_encode($vendorBillsMap) ?>;

        function openVendorDetails(vendorName) {
            document.getElementById('modalVendorTitle').textContent = `Supplier Ledger: ${vendorName}`;
            const bills = vendorBillsData[vendorName] || [];

            if (bills.length === 0) {
                document.getElementById('modalVendorBody').innerHTML = `
                    <div class="text-center py-10">
                        <span class="text-3xl block mb-2">📋</span>
                        <p class="text-xs text-slate-400 font-semibold">No stock intake bills recorded for this vendor yet.</p>
                    </div>
                `;
            } else {
                let html = `
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500 font-bold uppercase text-[10px] tracking-wider border-b">
                                    <th class="p-2.5">Invoice / Date</th>
                                    <th class="p-2.5">Product & Model</th>
                                    <th class="p-2.5 text-center">Pieces</th>
                                    <th class="p-2.5 text-right">Invoice / Due</th>
                                    <th class="p-2.5 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                `;

                bills.forEach(b => {
                    const due = Math.max(0, parseFloat(b.total_amount) - parseFloat(b.paid_amount));
                    const statusClass = b.payment_status === 'paid' 
                        ? 'bg-emerald-50 text-emerald-700' 
                        : (b.payment_status === 'partial' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700');

                    html += `
                        <tr class="hover:bg-slate-50">
                            <td class="p-2.5 whitespace-nowrap">
                                <span class="font-mono font-bold text-slate-800 block">Inv: ${b.invoice_number || 'N/A'}</span>
                                <span class="text-[10px] text-slate-400 font-sans">${b.created_at}</span>
                            </td>
                            <td class="p-2.5">
                                <span class="font-bold text-slate-900 block">${b.name}</span>
                                <span class="text-[10px] font-mono text-slate-500">${b.company} - ${b.model}</span>
                            </td>
                            <td class="p-2.5 text-center font-mono font-bold">
                                <div>${b.stock_pieces} pcs</div>
                                <div class="text-[10px] text-slate-400">Rem: ${b.remaining_pieces}</div>
                            </td>
                            <td class="p-2.5 text-right whitespace-nowrap font-mono">
                                <span class="font-black text-slate-900 block">₹${parseFloat(b.total_amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>
                                ${due > 0 ? `<span class="text-[10px] text-rose-600 font-bold block">Due: ₹${due.toLocaleString('en-IN', {minimumFractionDigits: 2})}</span>` : `<span class="text-[10px] text-emerald-600 font-bold block">✓ Cleared</span>`}
                            </td>
                            <td class="p-2.5 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase ${statusClass}">
                                    ${b.payment_status}
                                </span>
                            </td>
                        </tr>
                    `;
                });

                html += `</tbody></table></div>`;
                document.getElementById('modalVendorBody').innerHTML = html;
            }

            document.getElementById('vendorModal').classList.remove('hidden');
        }

        function closeVendorDetails() {
            document.getElementById('vendorModal').classList.add('hidden');
        }
    </script>
</body>
</html>