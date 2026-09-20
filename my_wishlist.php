<?php
session_start();
require 'db.php';

if (!isset($_SESSION['customer_logged']) || !isset($_SESSION['customer_id'])) {
    header("Location: customer_auth.php?redirect=my_wishlist.php");
    exit;
}

$customerId   = (int)$_SESSION['customer_id'];
$customerName = $_SESSION['customer_name'] ?? 'Customer';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_all') {
    $clearStmt = $conn->prepare("DELETE FROM customer_wishlist WHERE customer_id = ?");
    $clearStmt->execute([$customerId]);
    header("Location: my_wishlist.php?cleared=1");
    exit;
}

if (isset($_GET['remove'])) {
    $removeId = (int)$_GET['remove'];
    $delStmt = $conn->prepare("DELETE FROM customer_wishlist WHERE customer_id = ? AND public_product_id = ?");
    $delStmt->execute([$customerId, $removeId]);
    header("Location: my_wishlist.php?removed=1");
    exit;
}

$stmt = $conn->prepare("
    SELECT 
        p_pub.id,
        p_pub.name,
        p_pub.model,
        p_pub.company,
        p_pub.selling_price AS price,
        p_pub.discount,
        p_pub.offer,
        p_pub.specifications,
        p_pub.photo,
        COALESCE(SUM(s.remaining_pieces), 0) AS live_stock
    FROM customer_wishlist cw
    INNER JOIN public_products p_pub ON cw.public_product_id = p_pub.id
    LEFT JOIN stock_intake s ON p_pub.product_id = s.product_id
    WHERE cw.customer_id = ?
    GROUP BY p_pub.id
    ORDER BY cw.id DESC
");
$stmt->execute([$customerId]);
$wishlistItems = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - SAI GANAPATHI HOME NEEDS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800 antialiased flex flex-col justify-between">

    <header class="bg-white/95 backdrop-blur-md border-b border-slate-200 sticky top-0 z-30 px-4 py-3">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <a href="index.php" class="text-xs font-bold text-slate-600 hover:text-slate-900 flex items-center gap-1">
                ← Back to Catalog
            </a>
            <div class="flex items-center gap-3">
                <span class="text-xs font-bold text-slate-700">👤 <?= htmlspecialchars($customerName) ?></span>
                <a href="customer_auth.php?logout=1" class="text-xs font-bold text-rose-600 hover:underline">Logout</a>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 py-6 w-full flex-1">
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-200">
            <div>
                <h1 class="text-xl font-black text-slate-900">Your Private Wishlist</h1>
                <p class="text-xs text-slate-500">Items linked to your customer account (<?= count($wishlistItems) ?> items)</p>
            </div>

            <?php if (!empty($wishlistItems)): ?>
                <form method="POST" action="my_wishlist.php" onsubmit="return confirm('Clear your entire wishlist?');">
                    <input type="hidden" name="action" value="clear_all">
                    <button type="submit" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 text-xs font-bold rounded-xl transition cursor-pointer">
                        Clear All
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (isset($_GET['cleared'])): ?>
            <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold rounded-xl">
                Wishlist successfully cleared.
            </div>
        <?php endif; ?>

        <?php if (empty($wishlistItems)): ?>
            <div class="text-center py-20 bg-white rounded-3xl border border-slate-200 shadow-xs p-8 max-w-md mx-auto">
                <span class="text-4xl block mb-2">🤍</span>
                <h3 class="text-sm font-black text-slate-900">Your wishlist is currently empty</h3>
                <p class="text-xs text-slate-400 mt-1">Browse the showroom catalog and save items with the heart icon.</p>
                <a href="index.php" class="mt-4 inline-block px-4 py-2 bg-slate-900 text-amber-300 font-bold text-xs rounded-xl">
                    Browse Storefront
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-5">
                <?php foreach ($wishlistItems as $item): 
                    $finalPrice = $item['price'] - ($item['price'] * ($item['discount'] / 100));
                    $photoSrc = (strpos($item['photo'], 'http') === 0) ? $item['photo'] : $item['photo'];
                    $waMsg = urlencode("Hello Sai Ganapathi Home Needs, I am interested in purchasing my saved wishlist product: {$item['company']} {$item['name']} ({$item['model']}) for ₹" . number_format($finalPrice, 0));
                ?>
                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden flex flex-col justify-between shadow-xs relative">
                        <a href="my_wishlist.php?remove=<?= $item['id'] ?>" 
                           title="Remove item" 
                           class="absolute top-2 right-2 w-7 h-7 rounded-full bg-white/90 border border-slate-200 text-slate-400 hover:text-rose-600 flex items-center justify-center text-xs font-bold z-10 transition">
                            ✕
                        </a>

                        <div>
                            <div class="w-full aspect-square bg-slate-50 p-2 overflow-hidden">
                                <img src="<?= htmlspecialchars($photoSrc) ?>" class="w-full h-full object-contain">
                            </div>

                            <div class="p-3">
                                <span class="text-[9px] font-black uppercase text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded">
                                    <?= htmlspecialchars($item['company']) ?>
                                </span>
                                <h4 class="text-xs font-bold text-slate-900 mt-1 line-clamp-2"><?= htmlspecialchars($item['name']) ?></h4>
                                <p class="text-[10px] font-mono text-slate-400"><?= htmlspecialchars($item['model']) ?></p>
                            </div>
                        </div>

                        <div class="p-3 pt-2 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
                            <span class="text-xs sm:text-sm font-black text-slate-900">₹<?= number_format($finalPrice, 0) ?></span>
                            <a href="https://wa.me/917893282348?text=<?= $waMsg ?>" target="_blank" class="px-2.5 py-1 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg text-[10px] font-bold">
                                Buy Now
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="bg-slate-950 text-slate-400 py-4 text-center text-xs border-t border-slate-800">
        SAI GANAPATHI HOME NEEDS • Narsipatnam
    </footer>

</body>
</html>