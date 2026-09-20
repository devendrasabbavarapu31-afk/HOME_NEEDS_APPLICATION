<?php
session_start();

if (!isset($_SESSION['user_logged']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../db.php';

$success = '';
$error = '';

// Handle Master Product Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_product') {
    $company  = trim($_POST['company'] ?? '');
    $name     = trim($_POST['name'] ?? '');
    $model    = trim($_POST['model'] ?? '');
    $category = trim($_POST['category'] ?? 'Home Appliances');
    $specs    = trim($_POST['specifications'] ?? '');

    if (empty($company) || empty($name) || empty($model)) {
        $error = "Brand/Company, Product Title, and Model Number are required.";
    } else {
        // Prevent duplicate company + model registration
        $checkStmt = $conn->prepare("SELECT id FROM products WHERE LOWER(company) = LOWER(?) AND LOWER(model) = LOWER(?)");
        $checkStmt->execute([$company, $model]);
        if ($checkStmt->fetch()) {
            $error = "Model '{$model}' under brand '{$company}' is already registered in the system.";
        } else {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO products (company, name, model, category, specifications)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$company, $name, $model, $category, $specs]);
                $newId = $conn->lastInsertId();
                $success = "Item <strong>[#{$newId}] {$company} - {$name} ({$model})</strong> registered successfully!";
            } catch (PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// Handle Delete Product
if (isset($_GET['delete_product'])) {
    $delId = (int)$_GET['delete_product'];
    try {
        // Check if stock_intake relies on this product
        $checkIntake = $conn->prepare("SELECT COUNT(*) FROM stock_intake WHERE product_id = ?");
        $checkIntake->execute([$delId]);
        if ($checkIntake->fetchColumn() > 0) {
            $error = "Cannot delete: Intake stock shipments exist for this product. Remove stock entries first.";
        } else {
            $conn->prepare("DELETE FROM products WHERE id = ?")->execute([$delId]);
            header("Location: register_product.php?deleted=1");
            exit;
        }
    } catch (PDOException $e) {
        $error = "Deletion error: " . $e->getMessage();
    }
}

// Fetch all registered products along with current live warehouse pieces
$products = $conn->query("
    SELECT 
        p.*,
        COALESCE(SUM(s.remaining_pieces), 0) AS current_live_stock,
        COUNT(s.id) AS total_intake_batches
    FROM products p
    LEFT JOIN stock_intake s ON p.id = s.product_id
    GROUP BY p.id
    ORDER BY p.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$totalRegistered = count($products);
$currentPage = 'registry';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Product Registry - SAI GANAPATHI</title>
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
                    <h2 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">Master Product Registry</h2>
                    <p class="text-xs text-slate-500 font-medium">Add base appliance models before logging stock shipments</p>
                </div>
            </div>
            
            <div class="flex items-center gap-2.5">
                <a href="stock_intake.php" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1.5">
                    <span>📥</span>
                    <span>Go to Stock Intake</span>
                </a>
            </div>
        </header>

        <main class="p-6 max-w-7xl w-full mx-auto space-y-6">

            <!-- Alerts -->
            <?php if (!empty($success) || isset($_GET['deleted'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-bold flex items-center justify-between shadow-xs">
                    <span>✅ <?= !empty($success) ? $success : "Product registry record removed successfully!" ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-2xl text-xs font-bold flex items-center justify-between shadow-xs">
                    <span>⚠️ <?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                
                <!-- Registration Form -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs sticky top-24">
                    <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Register New Item</h3>
                            <p class="text-[11px] text-slate-400">Creates a master catalog record</p>
                        </div>
                        <span class="text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200 px-2 py-0.5 rounded-md">Catalog</span>
                    </div>

                    <form method="POST" action="register_product.php" class="space-y-3.5">
                        <input type="hidden" name="action" value="register_product">

                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Company / Brand *</label>
                            <input type="text" name="company" required placeholder="e.g. IFB, LG, Samsung, Whirlpool" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-amber-500 uppercase">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Product Title / Name *</label>
                            <input type="text" name="name" required placeholder="e.g. Front Load Washing Machine" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-amber-500">
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Model Number *</label>
                                <input type="text" name="model" required placeholder="e.g. Senator WSS 8014" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold font-mono focus:outline-none focus:border-amber-500">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Category</label>
                                <select name="category" class="w-full px-2.5 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:border-amber-500">
                                    <option value="Washing Machines">Washing Machine</option>
                                    <option value="Refrigerators">Refrigerator</option>
                                    <option value="Air Conditioners">Air Conditioner</option>
                                    <option value="Microwaves">Microwave Oven</option>
                                    <option value="Dishwashers">Dishwasher</option>
                                    <option value="Small Appliances">Small Appliances</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Specifications / Features</label>
                            <textarea name="specifications" rows="3" placeholder="e.g. 8.0 Kg | 1400 RPM | Steam Wash | Inverter Motor | 5 Star" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-amber-500"></textarea>
                        </div>

                        <button type="submit" class="w-full mt-2 py-2.5 bg-slate-900 hover:bg-slate-800 text-amber-300 font-bold text-xs uppercase tracking-wider rounded-xl shadow-xs transition cursor-pointer">
                            + Register Master Item
                        </button>
                    </form>
                </div>

                <!-- Products Table -->
                <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Registered Appliance Catalog</h3>
                            <p class="text-[11px] text-slate-400">Total <?= $totalRegistered ?> models in database</p>
                        </div>
                        <input type="text" id="registrySearch" placeholder="Filter models..." class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:border-indigo-500">
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500 font-bold uppercase text-[10px] tracking-wider border-b border-slate-200">
                                    <th class="p-3.5">ID & Brand</th>
                                    <th class="p-3.5">Product & Model</th>
                                    <th class="p-3.5">Category</th>
                                    <th class="p-3.5 text-center">Live Stock</th>
                                    <th class="p-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="registryTableBody" class="divide-y divide-slate-100">
                                <?php if (empty($products)): ?>
                                    <tr>
                                        <td colspan="5" class="p-8 text-center text-slate-400 font-semibold">No products registered yet. Use the form to add one.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($products as $p): ?>
                                        <tr class="registry-row hover:bg-slate-50/70 transition">
                                            <td class="p-3.5 whitespace-nowrap">
                                                <span class="font-mono text-slate-400 font-bold text-[10px] block">#<?= $p['id'] ?></span>
                                                <span class="text-[10px] font-black uppercase text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100 inline-block mt-0.5">
                                                    <?= htmlspecialchars($p['company']) ?>
                                                </span>
                                            </td>

                                            <td class="p-3.5">
                                                <span class="font-bold text-slate-900 text-xs block"><?= htmlspecialchars($p['name']) ?></span>
                                                <span class="font-mono text-[10px] text-slate-500 font-semibold block mt-0.5">Model: <?= htmlspecialchars($p['model']) ?></span>
                                                <?php if (!empty($p['specifications'])): ?>
                                                    <p class="text-[10px] text-slate-400 line-clamp-1 mt-0.5"><?= htmlspecialchars($p['specifications']) ?></p>
                                                <?php endif; ?>
                                            </td>

                                            <td class="p-3.5 text-slate-600 font-medium whitespace-nowrap">
                                                <?= htmlspecialchars($p['category']) ?>
                                            </td>

                                            <td class="p-3.5 text-center whitespace-nowrap font-mono">
                                                <span class="text-xs font-bold <?= $p['current_live_stock'] > 0 ? 'text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md' : 'text-slate-400 bg-slate-100 px-2 py-0.5 rounded-md' ?>">
                                                    <?= $p['current_live_stock'] ?> pcs
                                                </span>
                                                <span class="block text-[9px] text-slate-400 mt-0.5"><?= $p['total_intake_batches'] ?> batches</span>
                                            </td>

                                            <td class="p-3.5 text-right whitespace-nowrap">
                                                <a href="stock_intake.php" class="px-2 py-1 text-[11px] font-bold bg-blue-50 text-blue-700 hover:bg-blue-100 rounded-lg transition mr-1">
                                                    + Stock
                                                </a>
                                                <a href="register_product.php?delete_product=<?= $p['id'] ?>" 
                                                   onclick="return confirm('Delete <?= htmlspecialchars(addslashes($p['name'])) ?> from master registry?')" 
                                                   class="px-2 py-1 text-[11px] font-bold text-rose-600 hover:text-rose-800 hover:bg-rose-50 rounded-lg transition">
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

        // Live Filter
        document.getElementById('registrySearch').addEventListener('input', function() {
            const term = this.value.toLowerCase().trim();
            document.querySelectorAll('.registry-row').forEach(row => {
                row.classList.toggle('hidden', !row.innerText.toLowerCase().includes(term));
            });
        });
    </script>
</body>
</html>