<?php
session_start();

if (!isset($_SESSION['user_logged']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../db.php';

$success = '';
$error = '';

// Helper for file uploads
function handleImageUpload($fileInputName, &$errorMsg) {
    if (empty($_FILES[$fileInputName]['name'])) {
        return null;
    }

    $targetDir = "../uploads/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9._-]/", "", basename($_FILES[$fileInputName]['name']));
    $targetFilePath = $targetDir . $fileName;
    $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($fileType, $allowed)) {
        $errorMsg = "Allowed image formats: JPG, JPEG, PNG, WEBP.";
        return false;
    }

    if (move_uploaded_file($_FILES[$fileInputName]['tmp_name'], $targetFilePath)) {
        return 'uploads/' . $fileName;
    }

    $errorMsg = "Failed to move uploaded photo.";
    return false;
}

// 1. Handle Publishing to Public Showcase (MRP = stock_intake.counter_price)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publish') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $discount  = (float)($_POST['discount'] ?? 0);
    $offer     = trim($_POST['offer'] ?? '');
    $specs     = trim($_POST['specifications'] ?? '');

    // Prevent duplicate publishing
    $checkPub = $conn->prepare("SELECT id FROM public_products WHERE product_id = ?");
    $checkPub->execute([$productId]);
    if ($checkPub->fetch()) {
        $error = "This product is already published in the public showcase. Use the edit option below to update its details.";
    } else {
        // Fetch product details along with its latest intake counter_price
        $storeStmt = $conn->prepare("
            SELECT p.id, p.company, p.name, p.model,
                   COALESCE((
                       SELECT counter_price 
                       FROM stock_intake 
                       WHERE product_id = p.id 
                       ORDER BY id DESC LIMIT 1
                   ), 0.00) AS intake_counter_price,
                   COALESCE((
                       SELECT SUM(remaining_pieces) 
                       FROM stock_intake 
                       WHERE product_id = p.id
                   ), 0) AS total_available
            FROM products p 
            WHERE p.id = ?
        ");
        $storeStmt->execute([$productId]);
        $storeItem = $storeStmt->fetch(PDO::FETCH_ASSOC);

        $mrpPrice = (float)($storeItem['intake_counter_price'] ?? 0);
        $availableStock = (int)($storeItem['total_available'] ?? 0);

        if (!$storeItem) {
            $error = "Selected product was not found in the registry.";
        } elseif ($availableStock <= 0) {
            $error = "Cannot publish: '{$storeItem['name']} ({$storeItem['model']})' has 0 stock in intake. Add intake stock first!";
        } elseif ($mrpPrice <= 0) {
            $error = "Counter price in stock intake is ₹0.00. Set the counter price in Stock Intake first before publishing.";
        } else {
            $photoPath = 'https://images.unsplash.com/photo-1584992236310-6edddc08acff?w=500&auto=format&fit=crop';
            $uploaded = handleImageUpload('photo', $error);

            if ($uploaded !== false) {
                if ($uploaded !== null) {
                    $photoPath = $uploaded;
                }

                try {
                    $stmt = $conn->prepare("
                        INSERT INTO public_products (product_id, company, name, model, selling_price, discount, offer, specifications, photo, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                    ");
                    $stmt->execute([
                        $storeItem['id'],
                        $storeItem['company'],
                        $storeItem['name'],
                        $storeItem['model'],
                        $mrpPrice,
                        $discount,
                        $offer,
                        $specs,
                        $photoPath
                    ]);
                    $success = "Successfully published '{$storeItem['name']}' at Base MRP ₹" . number_format($mrpPrice, 2) . " with {$availableStock} units available!";
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    }
}

// 2. Handle Updating Existing Published Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_published') {
    $pubId    = (int)($_POST['pub_id'] ?? 0);
    $price    = (float)($_POST['edit_selling_price'] ?? 0);
    $discount = (float)($_POST['edit_discount'] ?? 0);
    $offer    = trim($_POST['edit_offer'] ?? '');
    $specs    = trim($_POST['edit_specifications'] ?? '');

    $currentStmt = $conn->prepare("SELECT photo FROM public_products WHERE id = ?");
    $currentStmt->execute([$pubId]);
    $currentPhoto = $currentStmt->fetchColumn();

    if ($price <= 0) {
        $error = "Base MRP / Counter price must be greater than 0.";
    } else {
        $newPhoto = handleImageUpload('edit_photo', $error);
        if ($newPhoto !== false) {
            $finalPhoto = $currentPhoto;

            if ($newPhoto !== null) {
                $finalPhoto = $newPhoto;
                if (!empty($currentPhoto) && strpos($currentPhoto, 'uploads/') === 0 && file_exists('../' . $currentPhoto)) {
                    @unlink('../' . $currentPhoto);
                }
            }

            try {
                $updateStmt = $conn->prepare("
                    UPDATE public_products 
                    SET selling_price = ?, discount = ?, offer = ?, specifications = ?, photo = ? 
                    WHERE id = ?
                ");
                $updateStmt->execute([$price, $discount, $offer, $specs, $finalPhoto, $pubId]);
                $success = "Showroom product record updated successfully!";
            } catch (PDOException $e) {
                $error = "Update failed: " . $e->getMessage();
            }
        }
    }
}

// 3. Handle Delete From Public Showcase
if (isset($_GET['delete_pub'])) {
    $delId = (int)$_GET['delete_pub'];
    $conn->prepare("DELETE FROM public_products WHERE id = ?")->execute([$delId]);
    header("Location: publish_product.php?deleted=1");
    exit;
}

// Fetch products with active intake stock that are NOT YET published
$storeProducts = $conn->query("
    SELECT 
        p.id, 
        p.company, 
        p.name, 
        p.model, 
        COALESCE(SUM(s.remaining_pieces), 0) AS active_stock,
        COALESCE((
            SELECT counter_price 
            FROM stock_intake 
            WHERE product_id = p.id 
            ORDER BY id DESC LIMIT 1
        ), 0.00) AS latest_counter_price
    FROM products p
    LEFT JOIN stock_intake s ON p.id = s.product_id
    WHERE NOT EXISTS (
        SELECT 1 FROM public_products pub WHERE pub.product_id = p.id
    )
    GROUP BY p.id
    ORDER BY p.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all published showroom items with live stock count
$publishedItems = $conn->query("
    SELECT 
        pub.*,
        COALESCE(SUM(s.remaining_pieces), 0) AS live_stock
    FROM public_products pub
    LEFT JOIN stock_intake s ON pub.product_id = s.product_id
    GROUP BY pub.id
    ORDER BY pub.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Metrics
$totalConfigured = count($publishedItems);
$activeVisibleCount = 0;
$hiddenStockoutCount = 0;

foreach ($publishedItems as $pItem) {
    if ($pItem['live_stock'] > 0) {
        $activeVisibleCount++;
    } else {
        $hiddenStockoutCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publish Showcase - SAI GANAPATHI HOME NEEDS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800 antialiased flex">

    <?php 
    $currentPage = 'publish';
    include 'sidebar.php'; 
    ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <header class="bg-white/95 backdrop-blur-md border-b border-slate-200 sticky top-0 z-30 px-6 py-4 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-3">
                <button type="button" onclick="toggleSidebar()" class="lg:hidden p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition cursor-pointer">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <div>
                    <h2 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">Showroom Publishing Hub</h2>
                    <p class="text-xs text-slate-500 font-medium">Base MRP pulled from stock intake counter price with promotional discounts</p>
                </div>
            </div>
            
            <div class="flex items-center gap-2.5">
                <a href="stock_intake.php" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1.5">
                    <span>📥</span>
                    <span>Stock Intake</span>
                </a>
                <a href="../index.php" target="_blank" class="px-3.5 py-2 bg-slate-900 hover:bg-amber-500 hover:text-slate-950 text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1.5">
                    <span>🌐</span>
                    <span>Live Store ↗</span>
                </a>
            </div>
        </header>

        <main class="p-6 max-w-7xl w-full mx-auto space-y-6">

            <!-- Alerts -->
            <?php if (!empty($success) || isset($_GET['deleted'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-bold flex items-center justify-between shadow-xs">
                    <span>✅ <?= !empty($success) ? $success : "Showroom product record updated successfully!" ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-2xl text-xs font-bold flex items-center justify-between shadow-xs">
                    <span>⚠️ <?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Metrics -->
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">Total Published</span>
                    <span class="text-2xl font-black text-slate-900 mt-1 block"><?= number_format($totalConfigured) ?></span>
                    <span class="text-[11px] text-slate-500 mt-0.5 block">Configured Showroom Items</span>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                    <span class="text-[10px] uppercase font-bold text-emerald-600 block tracking-wider">Active & Visible</span>
                    <span class="text-2xl font-black text-emerald-600 mt-1 block"><?= number_format($activeVisibleCount) ?></span>
                    <span class="text-[11px] text-slate-500 mt-0.5 block">Live Stock &gt; 0 Units</span>
                </div>

                <div class="bg-white p-4 rounded-2xl border border-slate-200/80 shadow-xs">
                    <span class="text-[10px] uppercase font-bold text-rose-600 block tracking-wider">Auto-Hidden</span>
                    <span class="text-2xl font-black text-rose-600 mt-1 block"><?= number_format($hiddenStockoutCount) ?></span>
                    <span class="text-[11px] text-slate-500 mt-0.5 block">0 Remaining in Warehouse</span>
                </div>
            </div>

            <!-- Main Workspace -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                
                <!-- Publishing Form -->
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs sticky top-24">
                    <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-100">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Publish New Product</h3>
                            <p class="text-[11px] text-slate-400">Only unpublished items are listed below</p>
                        </div>
                        <span class="text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 px-2 py-0.5 rounded-md">Showcase Entry</span>
                    </div>

                    <form method="POST" action="publish_product.php" enctype="multipart/form-data" class="space-y-3.5">
                        <input type="hidden" name="action" value="publish">

                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Select Unpublished In-Stock Item *</label>
                            <select id="productSelect" name="product_id" required onchange="handleProductSelection(this)" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-emerald-500 focus:bg-white transition">
                                <option value="" data-counter-price="0">-- Choose From Available Stock --</option>
                                <?php if (empty($storeProducts)): ?>
                                    <option value="" disabled class="text-slate-400">All registered items are already published.</option>
                                <?php else: ?>
                                    <?php foreach ($storeProducts as $sp): ?>
                                        <?php if ($sp['active_stock'] > 0): ?>
                                            <option value="<?= $sp['id'] ?>" data-counter-price="<?= $sp['latest_counter_price'] ?>">
                                                ✅ [#<?= $sp['id'] ?>] <?= htmlspecialchars($sp['company']) ?> - <?= htmlspecialchars($sp['name']) ?> (<?= htmlspecialchars($sp['model']) ?>) | Units: <?= $sp['active_stock'] ?> | MRP: ₹<?= number_format($sp['latest_counter_price'], 2) ?>
                                            </option>
                                        <?php else: ?>
                                            <option value="<?= $sp['id'] ?>" disabled class="text-slate-400 bg-slate-100">
                                                ❌ [#<?= $sp['id'] ?>] <?= htmlspecialchars($sp['company']) ?> - <?= htmlspecialchars($sp['name']) ?> (<?= htmlspecialchars($sp['model']) ?>) [NO STOCK]
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <p class="text-[10px] text-slate-400 mt-1">Already published products are hidden from this dropdown.</p>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Counter Price / Base MRP *</label>
                                <input type="number" step="0.01" id="mrpInput" name="selling_price" required readonly placeholder="0.00" class="w-full px-3 py-2 bg-slate-100 text-slate-700 font-bold border border-slate-200 rounded-xl text-xs focus:outline-none cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Discount (%)</label>
                                <input type="number" step="0.1" id="discountInput" name="discount" value="0.0" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-emerald-500 focus:bg-white transition">
                            </div>
                        </div>

                        <!-- Price Preview -->
                        <div class="p-3 bg-emerald-50/60 border border-emerald-100 rounded-xl flex items-center justify-between">
                            <span class="text-[11px] font-bold text-emerald-800">Final Showroom Deal Price:</span>
                            <span id="finalPricePreview" class="text-sm font-black text-emerald-700">₹0.00</span>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Promotional / Festive Offer</label>
                            <input type="text" name="offer" placeholder="e.g. Free Pods + 4 Yrs Warranty" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-emerald-500 focus:bg-white transition">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Specifications</label>
                            <textarea name="specifications" rows="2" placeholder="e.g. 8.0 Kg | 1400 RPM | Steam Wash" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-emerald-500 focus:bg-white transition"></textarea>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Upload Product Photo</label>
                            <input type="file" name="photo" accept="image/*" class="w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-900 file:text-white hover:file:bg-emerald-600 transition cursor-pointer">
                        </div>

                        <button type="submit" class="w-full mt-2 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-xs transition cursor-pointer">
                            🚀 Publish to Showroom
                        </button>
                    </form>
                </div>

                <!-- Showcase Table -->
                <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Configured Showroom Items</h3>
                            <p class="text-[11px] text-slate-400">Total <?= count($publishedItems) ?> items tracked</p>
                        </div>
                        <span class="text-[11px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-100 px-2.5 py-1 rounded-full">Public Catalog</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50/80 text-slate-500 font-bold uppercase text-[10px] tracking-wider border-b border-slate-200/80">
                                    <th class="p-3.5">Product & Photo</th>
                                    <th class="p-3.5 text-right">Pricing Details</th>
                                    <th class="p-3.5 text-center">Available Stock</th>
                                    <th class="p-3.5 text-center">Store Visibility</th>
                                    <th class="p-3.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($publishedItems)): ?>
                                    <tr>
                                        <td colspan="5" class="p-8 text-center text-slate-400 font-semibold">No products published to showcase yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($publishedItems as $row): 
                                        $photoSrc = (strpos($row['photo'], 'http') === 0) ? $row['photo'] : '../' . $row['photo'];
                                        $finalPrice = $row['selling_price'] - ($row['selling_price'] * ($row['discount'] / 100));
                                        $hasActiveStock = ($row['live_stock'] > 0);
                                    ?>
                                        <tr class="hover:bg-slate-50/70 transition">
                                            <td class="p-3.5 flex items-center gap-3">
                                                <div class="relative group shrink-0">
                                                    <img src="<?= htmlspecialchars($photoSrc) ?>" alt="Product" class="w-12 h-12 rounded-xl object-cover border border-slate-200 shadow-2xs">
                                                </div>
                                                <div class="min-w-0">
                                                    <span class="text-[10px] font-black uppercase text-indigo-700 bg-indigo-50 border border-indigo-100/80 px-2 py-0.5 rounded-md">
                                                        <?= htmlspecialchars($row['company']) ?>
                                                    </span>
                                                    <p class="font-bold text-slate-900 text-xs mt-1 leading-snug truncate max-w-[180px]"><?= htmlspecialchars($row['name']) ?></p>
                                                    <p class="text-[11px] text-slate-400 font-mono"><?= htmlspecialchars($row['model']) ?></p>
                                                    <?php if (!empty($row['offer'])): ?>
                                                        <span class="inline-block text-[10px] text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-100/80 mt-1 truncate max-w-[180px]">
                                                            🎁 <?= htmlspecialchars($row['offer']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <td class="p-3.5 text-right whitespace-nowrap">
                                                <span class="font-black text-slate-900 text-xs block">₹<?= number_format($finalPrice, 2) ?></span>
                                                <?php if ($row['discount'] > 0): ?>
                                                    <span class="text-[10px] text-slate-400 line-through block">MRP: ₹<?= number_format($row['selling_price'], 2) ?></span>
                                                    <span class="text-[10px] font-bold text-emerald-600 block"><?= number_format($row['discount'], 1) ?>% OFF</span>
                                                <?php else: ?>
                                                    <span class="text-[10px] text-slate-400 block font-mono">Base MRP: ₹<?= number_format($row['selling_price'], 2) ?></span>
                                                <?php endif; ?>
                                            </td>

                                            <td class="p-3.5 text-center whitespace-nowrap font-mono">
                                                <span class="text-xs font-bold <?= $hasActiveStock ? 'text-blue-600 bg-blue-50 px-2.5 py-1 rounded-lg' : 'text-rose-600 bg-rose-50 px-2.5 py-1 rounded-lg' ?>">
                                                    <?= $row['live_stock'] ?> Units
                                                </span>
                                            </td>

                                            <td class="p-3.5 text-center whitespace-nowrap">
                                                <?php if ($hasActiveStock): ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                        Live on Store
                                                    </span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-slate-100 text-slate-500 border border-slate-200">
                                                        Hidden (0 Stock)
                                                    </span>
                                                <?php endif; ?>
                                            </td>

                                            <td class="p-3.5 text-right whitespace-nowrap space-x-1">
                                                <button type="button" 
                                                    onclick='openEditModal(<?= json_encode($row) ?>)'
                                                    class="px-2.5 py-1 text-[11px] font-bold text-indigo-600 hover:text-indigo-800 hover:bg-indigo-50 rounded-lg transition cursor-pointer">
                                                    ✏️ Edit / Photo
                                                </button>

                                                <a href="publish_product.php?delete_pub=<?= $row['id'] ?>" 
                                                   onclick="return confirm('Unpublish <?= htmlspecialchars(addslashes($row['name'])) ?> from live showroom? It will become available again in the dropdown.')" 
                                                   class="px-2.5 py-1 text-[11px] font-bold text-rose-600 hover:text-rose-800 hover:bg-rose-50 rounded-lg transition">
                                                    Unpublish
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

    <!-- Edit Showcase Modal -->
    <div id="editModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white max-w-lg w-full rounded-2xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col">
            <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-900" id="editModalTitle">Edit Product Showcase</h3>
                    <p class="text-[11px] text-slate-400" id="editModalSubtitle"></p>
                </div>
                <button type="button" onclick="closeEditModal()" class="text-slate-400 hover:text-slate-700 text-base font-bold cursor-pointer">✕</button>
            </div>

            <form method="POST" action="publish_product.php" enctype="multipart/form-data" class="p-5 space-y-3">
                <input type="hidden" name="action" value="update_published">
                <input type="hidden" name="pub_id" id="editPubId">

                <!-- Photo Preview & Upload -->
                <div class="flex items-center gap-4 p-3 bg-slate-50 border border-slate-200 rounded-xl">
                    <img id="editCurrentPhotoPreview" src="" alt="Current Photo" class="w-16 h-16 rounded-xl object-cover border border-slate-200 shadow-2xs shrink-0">
                    <div class="min-w-0 flex-1">
                        <label class="block text-[11px] font-bold uppercase text-slate-600 mb-1">Replace Product Photo</label>
                        <input type="file" name="edit_photo" accept="image/*" class="w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-[11px] file:font-bold file:bg-slate-900 file:text-white hover:file:bg-emerald-600 transition cursor-pointer">
                        <p class="text-[10px] text-slate-400 mt-1">Leave empty to keep current picture.</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Base MRP / Counter Price *</label>
                        <input type="number" step="0.01" id="editPriceInput" name="edit_selling_price" required class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-emerald-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Discount (%)</label>
                        <input type="number" step="0.1" id="editDiscountInput" name="edit_discount" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-emerald-500">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Offer / Promotion</label>
                    <input type="text" id="editOfferInput" name="edit_offer" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Specifications</label>
                    <textarea id="editSpecsInput" name="edit_specifications" rows="2" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-emerald-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition cursor-pointer">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const mrpInput = document.getElementById('mrpInput');
        const discountInput = document.getElementById('discountInput');
        const preview = document.getElementById('finalPricePreview');

        // Sidebar Toggle for Mobile View
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar') || document.querySelector('aside');
            if (sidebar) {
                sidebar.classList.toggle('hidden');
                sidebar.classList.toggle('block');
            }
        }

        function handleProductSelection(selectElement) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const counterPrice = parseFloat(selectedOption.getAttribute('data-counter-price')) || 0;
            mrpInput.value = counterPrice.toFixed(2);
            calculateFinalPrice();
        }

        function calculateFinalPrice() {
            const mrp = parseFloat(mrpInput.value) || 0;
            const discount = parseFloat(discountInput.value) || 0;
            const finalPrice = Math.max(0, mrp - (mrp * (discount / 100)));
            preview.textContent = '₹' + finalPrice.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        mrpInput.addEventListener('input', calculateFinalPrice);
        discountInput.addEventListener('input', calculateFinalPrice);

        function openEditModal(item) {
            document.getElementById('editPubId').value = item.id;
            document.getElementById('editModalTitle').textContent = `Edit: ${item.company} ${item.name}`;
            document.getElementById('editModalSubtitle').textContent = `Model: ${item.model}`;
            document.getElementById('editPriceInput').value = item.selling_price;
            document.getElementById('editDiscountInput').value = item.discount;
            document.getElementById('editOfferInput').value = item.offer || '';
            document.getElementById('editSpecsInput').value = item.specifications || '';

            const photoUrl = (item.photo.startsWith('http')) ? item.photo : '../' + item.photo;
            document.getElementById('editCurrentPhotoPreview').src = photoUrl;

            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
    </script>
</body>
</html>