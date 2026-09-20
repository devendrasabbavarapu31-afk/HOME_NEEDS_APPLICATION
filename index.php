<?php
session_start();
require 'db.php';

// Authentication States
$isStaff    = isset($_SESSION['user_logged']) && $_SESSION['user_logged'] === true;
$userRole   = $_SESSION['role'] ?? '';
$isCustomer = isset($_SESSION['customer_logged']) && $_SESSION['customer_logged'] === true;
$customerId = $isCustomer ? (int)$_SESSION['customer_id'] : 0;

// Fetch Customer Wishlisted Product IDs securely
$wishlistIds = [];
if ($isCustomer) {
    $wStmt = $conn->prepare("SELECT public_product_id FROM customer_wishlist WHERE customer_id = ?");
    $wStmt->execute([$customerId]);
    $wishlistIds = $wStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

// Strict Rule: ONLY fetch products with ACTIVE remaining stock (live_stock > 0)
$stmt = $conn->query("
    SELECT 
        p_pub.id,
        p_pub.product_id,
        p_pub.name,
        p_pub.model,
        p_pub.company,
        p_pub.selling_price AS price,
        p_pub.discount,
        p_pub.offer,
        p_pub.specifications,
        p_pub.photo,
        COALESCE(SUM(s.remaining_pieces), 0) AS live_stock
    FROM public_products p_pub
    INNER JOIN stock_intake s ON p_pub.product_id = s.product_id
    WHERE p_pub.status = 'active'
    GROUP BY p_pub.id
    HAVING live_stock > 0
    ORDER BY p_pub.id DESC
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Extract unique brands for interactive filter chips
$brands = array_values(array_unique(array_filter(array_column($products, 'company'))));
sort($brands);

// Prepare JSON dataset for search suggestions and interactive spotlight cards
$productsJson = json_encode(array_map(function($p) use ($wishlistIds) {
    $finalPrice = $p['price'] - ($p['price'] * ($p['discount'] / 100));
    $photoSrc = (strpos($p['photo'], 'http') === 0) ? $p['photo'] : $p['photo'];
    return [
        'id'             => (int)$p['id'],
        'product_id'     => (int)$p['product_id'],
        'name'           => $p['name'],
        'model'          => $p['model'],
        'company'        => $p['company'],
        'price'          => (float)$p['price'],
        'discount'       => (float)$p['discount'],
        'final_price'    => (float)$finalPrice,
        'offer'          => $p['offer'] ?? '',
        'specifications' => $p['specifications'] ?? '',
        'photo'          => $photoSrc,
        'live_stock'     => (int)$p['live_stock'],
        'is_wishlist'    => in_array((int)$p['id'], $wishlistIds)
    ];
}, $products));
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SAI GANAPATHI | Premium Home Needs & Luxury Living</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,600;0,800;1,600&family=Cinzel:wght@700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; -webkit-tap-highlight-color: transparent; }
        .font-serif-luxury { font-family: 'Playfair Display', serif; }
        .font-cinzel { font-family: 'Cinzel', serif; }
        
        #intro-overlay {
            position: fixed; inset: 0; 
            background: radial-gradient(circle at 50% 35%, #1e110a 0%, #0c0806 60%, #050302 100%);
            display: flex; flex-direction: column; align-items: center; justify-content: center; 
            z-index: 99999;
            transition: opacity 0.8s cubic-bezier(0.16, 1, 0.3, 1), transform 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        #intro-overlay.fade-out { 
            opacity: 0; 
            transform: scale(1.04); 
            visibility: hidden; 
            pointer-events: none; 
        }

        .halo-spin { animation: haloRotate 28s linear infinite; }
        .halo-reverse { animation: haloRotateReverse 20s linear infinite; }
        @keyframes haloRotate { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        @keyframes haloRotateReverse { 0% { transform: rotate(360deg); } 100% { transform: rotate(0deg); } }

        .ganesha-draw path, .ganesha-draw line, .ganesha-draw circle {
            stroke-dasharray: 450; 
            stroke-dashoffset: 450; 
            animation: drawSacred 2.4s cubic-bezier(0.25, 1, 0.5, 1) forwards;
        }
        @keyframes drawSacred { to { stroke-dashoffset: 0; } }

        .gold-metallic-text {
            background: linear-gradient(135deg, #FFFDF0 0%, #FDE047 30%, #F59E0B 60%, #D97706 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .loader-bar { 
            width: 0%; 
            animation: loadSync 2.2s cubic-bezier(0.22, 1, 0.36, 1) forwards; 
        }
        @keyframes loadSync { 0% { width: 0%; } 100% { width: 100%; } }

        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        .glass-header {
            background: rgba(15, 23, 42, 0.92);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        /* Custom scrollbar for Chat Area */
        .chat-scroll::-webkit-scrollbar { width: 4px; }
        .chat-scroll::-webkit-scrollbar-track { background: transparent; }
        .chat-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
        .chat-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-[#F8FAFC] min-h-screen text-slate-800 antialiased selection:bg-amber-500 selection:text-slate-950 flex flex-col justify-between">

    <!-- 1. Royal Ganesha Opening Experience -->
    <div id="intro-overlay">
        <div class="absolute w-[500px] h-[500px] sm:w-[700px] sm:h-[700px] bg-gradient-to-tr from-amber-600/15 via-orange-600/15 to-yellow-500/10 rounded-full blur-[130px] pointer-events-none -top-16"></div>

        <div class="absolute top-5 left-5 right-5 flex items-center justify-between z-20">
            <div class="flex items-center gap-2 bg-white/5 border border-amber-400/25 px-4 py-1.5 rounded-full backdrop-blur-xl shadow-2xl">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span class="text-[10px] sm:text-[11px] font-black tracking-widest uppercase text-amber-300">PROP: GANESH</span>
                <span class="text-amber-400/40 text-xs">•</span>
                <a href="tel:7893282348" class="text-[10px] sm:text-[11px] text-amber-100 font-bold hover:text-white">7893282348</a>
            </div>
            <button onclick="dismissIntro()" class="px-4 py-1.5 rounded-full bg-amber-500/20 hover:bg-amber-500/30 border border-amber-400/40 text-[10px] sm:text-xs font-black text-amber-200 tracking-wider transition-all cursor-pointer">
                ENTER SHOWROOM ✕
            </button>
        </div>

        <div class="text-center px-4 flex flex-col items-center relative z-10 max-w-2xl">
            <div class="relative mb-6 flex items-center justify-center">
                <div class="absolute w-56 h-56 sm:w-72 sm:h-72 rounded-full border border-dashed border-amber-400/30 halo-spin pointer-events-none"></div>
                <div class="absolute w-64 h-64 sm:w-80 sm:h-80 rounded-full border border-dotted border-amber-500/20 halo-reverse pointer-events-none"></div>

                <div class="w-36 h-36 sm:w-44 sm:h-44 rounded-3xl bg-gradient-to-b from-[#1c120c] via-[#120b07] to-[#0a0604] border border-amber-400/70 p-5 shadow-[0_0_50px_rgba(245,158,11,0.35)] flex items-center justify-center backdrop-blur-3xl relative">
                    <svg viewBox="0 0 100 100" class="w-full h-full stroke-amber-300 fill-none ganesha-draw" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M40 22 L50 6 L60 22 Z" fill="#b45309" fill-opacity="0.4" stroke="#fde047" stroke-width="2.5"/>
                        <line x1="50" y1="6" x2="50" y2="24" stroke="#fef08a" stroke-width="2"/>
                        <circle cx="50" cy="11" r="1.8" fill="#dc2626" stroke="none"/>
                        <path d="M34 28 C10 28, 6 52, 26 60" stroke="#fbbf24" stroke-width="3"/>
                        <path d="M66 28 C90 28, 94 52, 74 60" stroke="#fbbf24" stroke-width="3"/>
                        <path d="M50 24 C34 24, 34 44, 43 54 C52 64, 54 78, 40 82 C28 85, 22 74, 31 66" stroke="#fde047" stroke-width="3.6"/>
                        <circle cx="31" cy="66" r="3.8" fill="#f59e0b" stroke="#fef08a" stroke-width="1.8"/>
                        <path d="M48 30 Q50 38 52 30" stroke="#dc2626" stroke-width="3"/>
                        <circle cx="50" cy="38" r="2.2" fill="#ef4444" stroke="none"/>
                    </svg>
                    <div class="absolute -bottom-2 -right-2 w-8 h-8 rounded-xl bg-gradient-to-tr from-amber-500 to-amber-600 border border-yellow-200 flex items-center justify-center shadow-lg">
                        <span class="font-cinzel text-[10px] font-black text-slate-950">SG</span>
                    </div>
                </div>
            </div>

            <p class="text-[11px] font-black uppercase tracking-[0.3em] text-amber-300 drop-shadow mb-1.5">
                ॥ శ్రీ గణేశాయ నమః • శుభారంభం ॥
            </p>
            <h1 class="text-3xl sm:text-5xl font-black font-cinzel gold-metallic-text tracking-wide">
                SAI GANAPATHI
            </h1>
            <h2 class="text-xs sm:text-sm font-black tracking-[0.3em] text-orange-200/90 uppercase mt-1">
                HOME NEEDS & FURNITURE
            </h2>
            <p class="text-[10px] text-amber-200/60 tracking-widest uppercase mt-2 font-medium">
                Premium Home Appliances & Solid Wood Living • Main Road, Narsipatnam
            </p>

            <div class="w-60 mt-6 flex flex-col items-center gap-2">
                <div class="w-full h-1 bg-white/10 rounded-full overflow-hidden p-0.5">
                    <div class="h-full bg-gradient-to-r from-amber-400 via-yellow-300 to-amber-500 rounded-full loader-bar"></div>
                </div>
                <span class="text-[9px] font-bold uppercase tracking-widest text-amber-400/80">Connecting Live Inventory...</span>
            </div>
        </div>
    </div>

    <!-- 2. Modern Midnight & Amber Navbar -->
    <header class="glass-header sticky top-0 z-40 border-b border-slate-800 shadow-xl transition-all">
        <div class="max-w-7xl mx-auto px-4 py-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3 cursor-pointer group" onclick="resetSearch()">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-amber-400 to-amber-600 p-0.5 shadow-lg shadow-amber-500/20 flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                        <div class="w-full h-full bg-[#0F172A] rounded-[14px] flex items-center justify-center">
                            <span class="text-sm font-black font-cinzel text-amber-400">SG</span>
                        </div>
                    </div>
                    <div>
                        <h1 class="text-sm sm:text-base font-black tracking-tight text-white flex items-center gap-2">
                            <span>SAI GANAPATHI</span>
                            <span class="hidden md:inline-block text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                Showroom
                            </span>
                        </h1>
                        <p class="text-[10px] sm:text-[11px] text-slate-400 font-medium">Main Road, Narsipatnam • Direct Verified Stock</p>
                    </div>
                </div>

                <!-- Mobile Header Actions -->
                <div class="flex items-center gap-2 sm:hidden">
                    <button onclick="handleWishlistClick()" class="p-2 rounded-xl bg-slate-800 text-rose-400 border border-slate-700 text-xs font-black flex items-center gap-1.5 shadow-sm">
                        <span>❤️</span>
                        <span id="mobileWishCount" class="text-[10px] font-black font-mono"><?= count($wishlistIds) ?></span>
                    </button>
                    <a href="login.php" class="px-3 py-1.5 rounded-xl bg-amber-500 text-slate-950 font-black text-[11px] shadow-sm flex items-center gap-1">
                        <span>Staff</span>
                    </a>
                </div>
            </div>

            <!-- Search Field with Live In-Stock Suggestions -->
            <div class="flex items-center gap-3 w-full sm:w-auto relative">
                <div class="relative w-full sm:w-80 md:w-96" id="searchContainer">
                    <div class="flex items-center relative">
                        <input 
                            type="text" 
                            id="searchInput" 
                            autocomplete="off"
                            placeholder="Search smart LED, washing machine, fridge..." 
                            class="w-full pl-9 pr-20 py-2 bg-slate-900/90 border border-slate-700/80 rounded-2xl text-xs font-semibold text-slate-100 placeholder:text-slate-400 focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-400/20 transition shadow-inner"
                        >
                        <span class="absolute left-3 top-2.5 text-slate-400 text-xs">🔍</span>
                        
                        <button 
                            type="button" 
                            id="searchBtn"
                            onclick="executeFullSearch()"
                            class="absolute right-1.5 top-1.5 bottom-1.5 px-3 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 rounded-xl text-[10px] font-black uppercase tracking-wider transition cursor-pointer shadow-md flex items-center gap-1"
                        >
                            <span>Search</span>
                        </button>
                    </div>

                    <div id="searchSuggestions" class="hidden absolute top-full left-0 right-0 mt-2 bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden z-50 max-h-80 overflow-y-auto"></div>
                </div>

                <div class="hidden sm:flex items-center gap-2.5 shrink-0">
                    <button onclick="handleWishlistClick()" class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700/80 border border-slate-700 text-rose-300 text-xs font-extrabold transition cursor-pointer shadow-sm">
                        <span>❤️ Wishlist</span>
                        <span id="desktopWishCount" class="bg-rose-500 text-white text-[10px] px-2 py-0.2 rounded-full font-mono font-black"><?= count($wishlistIds) ?></span>
                    </button>

                    <?php if ($isCustomer): ?>
                        <div class="flex items-center gap-2 bg-slate-800/90 border border-slate-700 px-3 py-1.5 rounded-xl text-xs">
                            <span class="font-bold text-slate-200">👤 <?= htmlspecialchars($_SESSION['customer_name'] ?? 'Customer') ?></span>
                            <a href="customer_auth.php?logout=1" class="text-rose-400 font-bold hover:underline ml-1">Logout</a>
                        </div>
                    <?php else: ?>
                        <a href="customer_auth.php" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 font-bold text-xs transition">
                            Customer Login
                        </a>
                    <?php endif; ?>

                    <a href="login.php" class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-950 font-black text-xs shadow-md shadow-amber-500/20 transition">
                        <span>⚡</span>
                        <span><?= $isStaff ? 'Workspace (' . strtoupper($userRole) . ')' : 'Staff Portal' ?></span>
                    </a>
                </div>
            </div>

        </div>
    </header>

    <!-- 3. Showroom Hotline Banner -->
    <section class="bg-gradient-to-r from-[#0F172A] via-[#1E293B] to-[#0F172A] text-slate-300 py-2.5 px-4 border-b border-amber-500/30">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2 text-xs">
            <span class="font-semibold text-slate-200 flex items-center gap-2 truncate">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                <span>Direct Showroom Hours: <strong>09:00 AM – 09:00 PM</strong> (All 7 Days Open)</span>
                <span class="hidden md:inline text-slate-500">•</span>
                <span class="hidden md:inline text-amber-400">Authorized Dealer Warranties on IFB, Samsung, LG & Godrej</span>
            </span>
            <div class="flex items-center gap-3">
                <span class="text-[11px] text-slate-400">Assistance / WhatsApp:</span>
                <a href="tel:7893282348" class="text-amber-400 font-mono font-black hover:text-amber-300 transition flex items-center gap-1">
                    <span>📞 +91 7893282348</span>
                </a>
            </div>
        </div>
    </section>

    <!-- Brand Filter Chips -->
    <?php if (!empty($brands)): ?>
        <div class="bg-white/80 backdrop-blur-md border-b border-slate-200 sticky top-[69px] sm:top-[65px] z-30 shadow-xs">
            <div class="max-w-7xl mx-auto px-4 py-2.5 flex items-center gap-2 overflow-x-auto no-scrollbar">
                <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 shrink-0">Filter Brands:</span>
                <button class="brand-chip px-3.5 py-1 rounded-full text-xs font-bold bg-slate-900 text-amber-300 shadow-xs whitespace-nowrap transition cursor-pointer" data-brand="all">
                    All Categories (<?= count($products) ?>)
                </button>
                <?php foreach ($brands as $b): ?>
                    <button class="brand-chip px-3.5 py-1 rounded-full text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 whitespace-nowrap transition cursor-pointer border border-slate-200/80" data-brand="<?= htmlspecialchars(strtolower($b)) ?>">
                        <?= htmlspecialchars($b) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div id="filterIndicator" class="hidden max-w-7xl mx-auto px-4 pt-4">
        <div class="bg-amber-50 border border-amber-300/80 text-amber-950 px-4 py-2 rounded-2xl text-xs font-bold flex items-center justify-between shadow-xs">
            <span id="filterIndicatorText"></span>
            <button onclick="resetSearch()" class="text-[11px] bg-white border border-amber-300 px-2.5 py-1 rounded-xl text-amber-900 font-bold hover:bg-amber-100 transition cursor-pointer">
                Clear Filters ✕
            </button>
        </div>
    </div>

    <!-- Main Catalog Grid -->
    <main class="max-w-7xl mx-auto px-3 sm:px-4 py-6 w-full">
        <div id="spotlightSection" class="hidden mb-8 animate__animated animate__fadeIn"></div>

        <?php if (empty($products)): ?>
            <div class="text-center py-20 bg-white rounded-3xl border border-slate-200 shadow-sm p-8 max-w-md mx-auto">
                <span class="text-5xl block mb-3">📦</span>
                <h3 class="text-base font-black text-slate-900">Live Inventory Refresh in Progress</h3>
                <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">Appliances and teak furnishings are being checked against showroom stock.</p>
                <a href="tel:7893282348" class="mt-4 inline-block px-5 py-2.5 bg-slate-900 hover:bg-amber-500 hover:text-slate-950 text-amber-300 font-black text-xs rounded-xl transition shadow-md">
                    Call Counter: 7893282348
                </a>
            </div>
        <?php else: ?>
            <div id="productGrid" class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-5">
                <?php foreach ($products as $item): 
                    $finalPrice = $item['price'] - ($item['price'] * ($item['discount'] / 100));
                    $photoSrc = (strpos($item['photo'], 'http') === 0) ? $item['photo'] : $item['photo'];
                    $isWish = in_array((int)$item['id'], $wishlistIds);
                ?>
                    <div class="product-card bg-white rounded-2xl border border-slate-200/90 shadow-[0_2px_10px_rgba(0,0,0,0.03)] hover:shadow-[0_12px_28px_rgba(15,23,42,0.08)] hover:border-amber-400/80 transition-all duration-300 flex flex-col justify-between overflow-hidden cursor-pointer group relative"
                         data-id="<?= $item['id'] ?>"
                         data-brand="<?= htmlspecialchars(strtolower($item['company'])) ?>"
                         onclick="selectProduct(<?= $item['id'] ?>)">
                        
                        <div>
                            <!-- Product Image Canvas -->
                            <div class="relative w-full aspect-square bg-gradient-to-b from-slate-50 to-slate-100/50 overflow-hidden p-3 flex items-center justify-center border-b border-slate-100">
                                <img src="<?= htmlspecialchars($photoSrc) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300">
                                
                                <div class="absolute top-2.5 left-2.5 flex flex-col gap-1.5 items-start">
                                    <?php if ($item['discount'] > 0): ?>
                                        <span class="bg-rose-600 text-white text-[9px] font-black px-2 py-0.5 rounded-lg shadow-sm tracking-wider uppercase">
                                            Save <?= number_format($item['discount'], 0) ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="absolute bottom-2.5 right-2.5">
                                    <span class="bg-emerald-600/90 backdrop-blur-md text-white text-[9px] font-black px-2 py-0.5 rounded-md shadow-xs flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-200 animate-pulse"></span>
                                        <span><?= $item['live_stock'] ?> in stock</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Floating Action Buttons -->
                            <div class="absolute top-2.5 right-2.5 flex flex-col gap-1.5 z-20">
                                <button onclick="toggleWishlist(<?= $item['id'] ?>, this, event)" 
                                        title="<?= $isWish ? 'Remove from wishlist' : 'Add to wishlist' ?>" 
                                        class="wishlist-btn w-8 h-8 rounded-full bg-white/90 backdrop-blur-md border border-slate-200/80 shadow-md flex items-center justify-center text-sm transition-transform active:scale-90 hover:bg-white cursor-pointer <?= $isWish ? 'text-rose-600 font-black' : 'text-slate-400' ?>">
                                    <?= $isWish ? '❤️' : '🤍' ?>
                                </button>

                                <button onclick="openShareModal(<?= $item['id'] ?>, event)" 
                                        title="Share Item" 
                                        class="w-8 h-8 rounded-full bg-white/90 backdrop-blur-md border border-slate-200/80 shadow-md flex items-center justify-center text-slate-700 text-xs transition-transform active:scale-90 hover:bg-white cursor-pointer">
                                    🔗
                                </button>
                            </div>

                            <!-- Item Information -->
                            <div class="p-3 sm:p-4">
                                <div class="flex items-center justify-between gap-1 mb-1.5">
                                    <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-wider text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-md">
                                        <?= htmlspecialchars($item['company']) ?>
                                    </span>
                                    <span class="text-[9px] sm:text-[10px] font-mono font-semibold text-slate-400 truncate max-w-[90px]">
                                        <?= htmlspecialchars($item['model']) ?>
                                    </span>
                                </div>

                                <h4 class="text-xs sm:text-sm font-bold text-slate-900 leading-snug line-clamp-2 group-hover:text-amber-600 transition">
                                    <?= htmlspecialchars($item['name']) ?>
                                </h4>

                                <?php if (!empty($item['offer'])): ?>
                                    <div class="mt-2 bg-gradient-to-r from-amber-50 to-yellow-50 border border-amber-200 px-2 py-1 rounded-lg flex items-center gap-1.5 text-amber-900">
                                        <span class="text-[11px]">🎁</span>
                                        <p class="text-[9px] font-black leading-tight truncate">
                                            <?= htmlspecialchars($item['offer']) ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Card Footer & Pricing -->
                        <div class="p-3 sm:p-4 pt-2.5 border-t border-slate-100 flex items-center justify-between gap-2 bg-slate-50/60">
                            <div>
                                <?php if ($item['discount'] > 0): ?>
                                    <span class="text-[10px] text-slate-400 line-through block leading-tight font-mono">
                                        ₹<?= number_format($item['price'], 0) ?>
                                    </span>
                                <?php endif; ?>
                                <span class="text-xs sm:text-base font-black text-slate-950 block leading-tight font-mono">
                                    ₹<?= number_format($finalPrice, 0) ?>
                                </span>
                            </div>

                            <span class="text-[10px] sm:text-xs font-black text-amber-600 group-hover:text-amber-700 flex items-center gap-0.5">
                                <span>Details</span>
                                <span class="group-hover:translate-x-0.5 transition-transform">→</span>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div id="noResults" class="hidden text-center py-20 bg-white rounded-3xl border border-slate-200 mt-6 shadow-xs">
            <span class="text-4xl block mb-2">🔍</span>
            <p class="text-slate-800 font-bold text-sm">No matching showroom models found.</p>
            <p class="text-xs text-slate-400 mt-1">Try another brand or appliance type.</p>
        </div>
    </main>

    <!-- Share Dialog Modal -->
    <div id="shareModal" class="hidden fixed inset-0 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-sm w-full p-5 relative">
            <button onclick="closeShareModal()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 text-lg font-bold cursor-pointer">&times;</button>
            
            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 mb-1">Share Appliance / Furniture</h3>
            <p id="shareItemTitle" class="text-xs font-bold text-slate-600 line-clamp-1 mb-4"></p>

            <div class="space-y-2.5">
                <a id="shareWaBtn" href="#" target="_blank" class="w-full py-2.5 bg-[#25D366] hover:bg-[#1EBE5D] text-white rounded-xl text-xs font-black flex items-center justify-center gap-2 transition shadow-sm">
                    <span>💬 Share on WhatsApp</span>
                </a>

                <button onclick="copyProductLink()" class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl text-xs font-bold flex items-center justify-center gap-2 transition cursor-pointer">
                    <span id="copyBtnText">📋 Copy Link</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Floating Chatbot Widget Button -->
    <div class="fixed bottom-5 right-5 z-50">
        <button id="chatbotToggleBtn" onclick="toggleChatbot()" class="w-14 h-14 rounded-full bg-gradient-to-tr from-slate-950 via-slate-900 to-indigo-950 text-amber-300 border-2 border-amber-400 shadow-2xl flex items-center justify-center text-2xl hover:scale-105 active:scale-95 transition-all cursor-pointer">
            🤖
        </button>
    </div>

    <!-- Chatbot Popup Window -->
    <div id="chatbotModal" class="hidden fixed bottom-22 right-5 w-80 sm:w-96 bg-white rounded-3xl border border-slate-200 shadow-2xl z-50 flex flex-col overflow-hidden transition-all duration-300">
        <!-- Clean Header with Actions -->
        <div class="bg-slate-950 px-4 py-3 border-b-2 border-amber-400 flex items-center justify-between text-white">
            <div class="flex items-center gap-2.5">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <div>
                    <h4 class="text-xs font-black text-amber-300 uppercase tracking-wider">Sai Ganapathi AI</h4>
                    <p class="text-[9px] text-slate-400">Inventory & Showroom Desk</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="clearChatMessages()" title="Clear Chat" class="px-2 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-amber-300 text-[10px] font-bold border border-slate-700 transition cursor-pointer">
                    Clear ⌫
                </button>
                <button onclick="toggleChatbot()" class="text-slate-400 hover:text-white text-base font-bold cursor-pointer leading-none">&times;</button>
            </div>
        </div>

        <!-- Clean Chat Messages Container -->
        <div id="chatMessages" class="chat-scroll p-4 h-72 overflow-y-auto space-y-3 text-xs bg-slate-50/60">
            <div class="bg-white p-3 rounded-2xl rounded-tl-none border border-slate-200/80 shadow-2xs max-w-[85%] text-slate-700 leading-relaxed">
                Namaste! 🙏 Welcome to Sai Ganapathi Home Needs. Ask me about in-stock washing machines, refrigerators, televisions, air conditioners, furniture pricing, or showroom directions in Narsipatnam.
            </div>
        </div>

        <!-- Clean Quick Prompts (Ratings button completely removed) -->
        <div class="px-3 py-2 bg-slate-100/80 border-t border-slate-200/70 flex gap-2 overflow-x-auto text-[10px] no-scrollbar">
            <button onclick="sendQuickPrompt('Where is your showroom located?')" class="bg-white border border-slate-200 hover:border-amber-400 px-3 py-1 rounded-full text-slate-700 font-bold whitespace-nowrap shadow-2xs transition cursor-pointer">
                📍 Store Location
            </button>
            <button onclick="sendQuickPrompt('What washing machines or refrigerators are in stock?')" class="bg-white border border-slate-200 hover:border-amber-400 px-3 py-1 rounded-full text-slate-700 font-bold whitespace-nowrap shadow-2xs transition cursor-pointer">
                📦 In-Stock Deals
            </button>
            <button onclick="sendQuickPrompt('What are your store timings?')" class="bg-white border border-slate-200 hover:border-amber-400 px-3 py-1 rounded-full text-slate-700 font-bold whitespace-nowrap shadow-2xs transition cursor-pointer">
                🕒 Hours
            </button>
        </div>

        <!-- Clean Input & Submit Box -->
        <form id="chatForm" onsubmit="handleChatSubmit(event)" class="p-3 bg-white border-t border-slate-200 flex items-center gap-2">
            <input type="text" id="chatInput" placeholder="Ask about price, specs, stock..." required class="flex-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:border-amber-500 focus:bg-white transition">
            <button type="submit" id="chatSendBtn" class="px-4 py-2 bg-slate-900 hover:bg-amber-500 hover:text-slate-950 text-amber-300 font-black text-xs rounded-xl transition shadow-xs cursor-pointer disabled:opacity-50">
                Send
            </button>
        </form>
    </div>

    <!-- 4. Deep Indigo & Gold Showroom Footer -->
    <footer class="bg-[#0B0F19] text-slate-400 py-8 text-xs border-t-2 border-amber-400 mt-12">
        <div class="max-w-7xl mx-auto px-4 flex flex-col md:flex-row items-center justify-between gap-6 text-center md:text-left">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-amber-500 to-amber-600 p-0.5 shadow-lg shadow-amber-500/20 flex items-center justify-center shrink-0">
                    <div class="w-full h-full bg-slate-950 rounded-[14px] flex items-center justify-center">
                        <span class="text-xs font-black font-cinzel text-amber-400">SG</span>
                    </div>
                </div>
                <div>
                    <span class="inline-block px-2 py-0.5 rounded text-[8px] font-black uppercase bg-amber-400/10 text-amber-300 border border-amber-400/30">
                        PROP: GANESH
                    </span>
                    <p class="font-black text-slate-100 text-xs tracking-wider mt-0.5">SAI GANAPATHI HOME NEEDS & FURNITURE</p>
                    <p class="text-[10px] text-slate-400">Main Road, Narsipatnam, Anakapalli District, Andhra Pradesh</p>
                </div>
            </div>
            
            <div class="flex flex-col sm:flex-row items-center gap-3 sm:gap-6">
                <a href="tel:7893282348" class="text-amber-400 font-bold text-xs hover:underline flex items-center gap-1.5">
                    <span>📞 Phone / WhatsApp: +91 7893282348</span>
                </a>
                <span class="hidden sm:inline text-slate-700">•</span>
                <span class="text-[11px] text-slate-500">Authorized Warranty & Genuine Showroom Goods</span>
            </div>
        </div>
    </footer>

    <!-- Client Scripts -->
    <script>
        const catalogProducts = <?= $productsJson ?>;
        const isCustomerLoggedIn = <?= $isCustomer ? 'true' : 'false' ?>;
        let activeShareUrl = '';

        function dismissIntro() {
            const overlay = document.getElementById('intro-overlay');
            if (overlay && !overlay.classList.contains('fade-out')) {
                overlay.classList.add('fade-out');
                setTimeout(() => overlay.remove(), 800);
            }
        }
        window.addEventListener('DOMContentLoaded', () => setTimeout(dismissIntro, 2200));

        // Chatbot Visibility
        function toggleChatbot() {
            const modal = document.getElementById('chatbotModal');
            modal.classList.toggle('hidden');
            if (!modal.classList.contains('hidden')) {
                document.getElementById('chatInput').focus();
            }
        }

        // Clear Chat Conversation
        function clearChatMessages() {
            const chatBox = document.getElementById('chatMessages');
            chatBox.innerHTML = `
                <div class="bg-white p-3 rounded-2xl rounded-tl-none border border-slate-200/80 shadow-2xs max-w-[85%] text-slate-700 leading-relaxed">
                    Chat cleared. How else may I assist you with our showroom models or pricing?
                </div>
            `;
        }

        // Send Predefined Quick Prompt
        function sendQuickPrompt(promptText) {
            const input = document.getElementById('chatInput');
            input.value = promptText;
            document.getElementById('chatForm').dispatchEvent(new Event('submit'));
        }

        // Asynchronous Chatbot Submissions
        async function handleChatSubmit(e) {
            e.preventDefault();
            const input = document.getElementById('chatInput');
            const msg = input.value.trim();
            if (!msg) return;

            const chatBox = document.getElementById('chatMessages');
            const sendBtn = document.getElementById('chatSendBtn');

            // Append User Message
            chatBox.innerHTML += `
                <div class="flex justify-end">
                    <div class="bg-slate-900 text-amber-300 p-2.5 rounded-2xl rounded-tr-none shadow-xs max-w-[85%] text-xs leading-relaxed font-semibold">
                        ${escapeHtml(msg)}
                    </div>
                </div>
            `;
            input.value = '';
            sendBtn.disabled = true;
            chatBox.scrollTop = chatBox.scrollHeight;

            // Loading Indicator
            const tempId = 'loading-' + Date.now();
            chatBox.innerHTML += `
                <div id="${tempId}" class="bg-white p-2.5 rounded-2xl rounded-tl-none border border-slate-200 shadow-2xs max-w-[85%] text-slate-400 italic text-[11px] flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-ping"></span>
                    <span>Checking showroom database...</span>
                </div>
            `;
            chatBox.scrollTop = chatBox.scrollHeight;

            try {
                const res = await fetch('chatbot_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: msg })
                });
                const data = await res.json();
                
                const loader = document.getElementById(tempId);
                if (loader) loader.remove();
                
                chatBox.innerHTML += `
                    <div class="bg-white p-3 rounded-2xl rounded-tl-none border border-slate-200/90 shadow-2xs max-w-[85%] text-slate-800 leading-relaxed">
                        ${data.reply ? data.reply.replace(/\n/g, '<br>') : 'I could not process your request right now.'}
                    </div>
                `;
            } catch (err) {
                const loader = document.getElementById(tempId);
                if (loader) loader.remove();
                chatBox.innerHTML += `
                    <div class="bg-rose-50 text-rose-700 p-2.5 rounded-2xl rounded-tl-none border border-rose-200 max-w-[85%] text-[11px]">
                        Unable to connect to assistant. Please call showroom at +91 7893282348.
                    </div>
                `;
            } finally {
                sendBtn.disabled = false;
                chatBox.scrollTop = chatBox.scrollHeight;
            }
        }

        // Wishlist Navigation Guard
        function handleWishlistClick() {
            if (isCustomerLoggedIn) {
                window.location.href = 'my_wishlist.php';
            } else {
                if (confirm("Customer login required to view private wishlists. Proceed to login?")) {
                    window.location.href = 'customer_auth.php?redirect=my_wishlist.php';
                }
            }
        }

        // Wishlist Toggle
        function toggleWishlist(productId, btn, e) {
            if (e) e.stopPropagation();

            const formData = new FormData();
            formData.append('product_id', productId);

            fetch('wishlist_action.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'auth_required') {
                    if (confirm("Login required to save items! Go to customer login?")) {
                        window.location.href = 'customer_auth.php?redirect=index.php';
                    }
                    return;
                }

                if (data.status === 'success') {
                    const prod = catalogProducts.find(p => p.id === productId);
                    if (prod) prod.is_wishlist = (data.action === 'added');

                    document.querySelectorAll(`.wishlist-btn`).forEach(b => {
                        const card = b.closest('.product-card');
                        if (card && parseInt(card.getAttribute('data-id')) === productId) {
                            b.innerHTML = (data.action === 'added') ? '❤️' : '🤍';
                            b.classList.toggle('text-rose-600', data.action === 'added');
                            b.classList.toggle('text-slate-400', data.action === 'removed');
                        }
                    });

                    const spotlightWishBtn = document.getElementById('spotlightWishBtn');
                    if (spotlightWishBtn) {
                        spotlightWishBtn.innerHTML = (data.action === 'added') ? '❤️ In Wishlist' : '🤍 Wishlist';
                        spotlightWishBtn.classList.toggle('bg-rose-50', data.action === 'added');
                        spotlightWishBtn.classList.toggle('text-rose-700', data.action === 'added');
                        spotlightWishBtn.classList.toggle('border-rose-200', data.action === 'added');
                    }

                    const mobileCount = document.getElementById('mobileWishCount');
                    const desktopCount = document.getElementById('desktopWishCount');
                    if (mobileCount) mobileCount.textContent = data.total_wishlist;
                    if (desktopCount) desktopCount.textContent = data.total_wishlist;
                }
            })
            .catch(() => alert("Network error updating wishlist."));
        }

        // Share Dialog Logic
        function openShareModal(productId, e) {
            if (e) e.stopPropagation();

            const item = catalogProducts.find(p => p.id === productId);
            if (!item) return;

            const baseUrl = window.location.origin + window.location.pathname;
            activeShareUrl = `${baseUrl}?item_id=${item.id}`;

            document.getElementById('shareItemTitle').textContent = `${item.company} ${item.name} (${item.model})`;
            const waShareText = encodeURIComponent(`Check out this appliance at Sai Ganapathi Home Needs: ${item.company} ${item.name} (${item.model}) for ₹${item.final_price.toLocaleString('en-IN')}: ${activeShareUrl}`);
            document.getElementById('shareWaBtn').href = `https://wa.me/?text=${waShareText}`;

            document.getElementById('copyBtnText').textContent = "📋 Copy Link";
            document.getElementById('shareModal').classList.remove('hidden');
        }

        function closeShareModal() {
            document.getElementById('shareModal').classList.add('hidden');
        }

        function copyProductLink() {
            navigator.clipboard.writeText(activeShareUrl).then(() => {
                document.getElementById('copyBtnText').textContent = "✅ Link Copied!";
                setTimeout(closeShareModal, 1200);
            });
        }

        // Live Suggestions & Search Bar Controls
        const searchInput     = document.getElementById('searchInput');
        const suggestionsBox  = document.getElementById('searchSuggestions');
        const searchContainer = document.getElementById('searchContainer');
        const cards           = document.querySelectorAll('.product-card');
        const noResults       = document.getElementById('noResults');
        const chips           = document.querySelectorAll('.brand-chip');

        let activeBrand = 'all';
        let activeSearchQuery = '';

        searchInput.addEventListener('input', function () {
            const val = this.value.trim().toLowerCase();

            if (val.length < 1) {
                suggestionsBox.classList.add('hidden');
                suggestionsBox.innerHTML = '';
                return;
            }

            const matches = catalogProducts.filter(p => {
                return p.name.toLowerCase().includes(val) || p.model.toLowerCase().includes(val) || p.company.toLowerCase().includes(val);
            });

            if (matches.length === 0) {
                suggestionsBox.innerHTML = `
                    <div class="p-4 text-center text-xs text-slate-400 font-medium">
                        No showroom items matching "<strong>${escapeHtml(this.value)}</strong>"
                    </div>
                `;
                suggestionsBox.classList.remove('hidden');
                return;
            }

            suggestionsBox.innerHTML = `
                <div class="p-2.5 border-b border-slate-100 bg-slate-50 flex items-center justify-between text-[10px] font-black uppercase tracking-wider text-slate-400">
                    <span>Verified Inventory</span>
                    <span>${matches.length} Results</span>
                </div>
                <div class="divide-y divide-slate-100">
                    ${matches.slice(0, 6).map(item => `
                        <div class="p-3 hover:bg-amber-50/70 transition flex items-center justify-between gap-3 cursor-pointer"
                             onclick="selectSuggestion(${item.id}, '${escapeHtml(item.name.replace(/'/g, "\\'"))}')">
                            <div class="flex items-center gap-3 min-w-0">
                                <img src="${escapeHtml(item.photo)}" class="w-10 h-10 rounded-xl object-contain bg-slate-50 border border-slate-200 shrink-0">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[9px] font-black uppercase text-amber-700 bg-amber-50 border border-amber-200/70 px-1.5 rounded">${escapeHtml(item.company)}</span>
                                        <span class="text-[9px] font-mono text-slate-400 truncate">${escapeHtml(item.model)}</span>
                                    </div>
                                    <p class="text-xs font-bold text-slate-900 truncate mt-0.5">${escapeHtml(item.name)}</p>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-xs font-black text-slate-900 block font-mono">₹${item.final_price.toLocaleString('en-IN')}</span>
                                <span class="text-[9px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded-full border border-emerald-100">${item.live_stock} in stock</span>
                            </div>
                        </div>
                    `).join('')}
                </div>
                <div class="p-2.5 bg-slate-50 border-t border-slate-100 text-center">
                    <button type="button" onclick="executeFullSearch()" class="text-xs font-bold text-amber-600 hover:text-amber-700 transition cursor-pointer">
                        View all ${matches.length} matches &rarr;
                    </button>
                </div>
            `;
            suggestionsBox.classList.remove('hidden');
        });

        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                executeFullSearch();
            }
        });

        function executeFullSearch() {
            suggestionsBox.classList.add('hidden');
            activeSearchQuery = searchInput.value.trim().toLowerCase();
            
            const filterIndicator = document.getElementById('filterIndicator');
            const filterText      = document.getElementById('filterIndicatorText');

            if (activeSearchQuery !== '') {
                filterText.textContent = `Search results for "${searchInput.value.trim()}"`;
                filterIndicator.classList.remove('hidden');
            } else {
                filterIndicator.classList.add('hidden');
            }

            applyCatalogFilters();
            document.getElementById('productGrid').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function selectSuggestion(productId, productName) {
            suggestionsBox.classList.add('hidden');
            searchInput.value = productName;
            activeSearchQuery = productName.toLowerCase();
            
            const filterIndicator = document.getElementById('filterIndicator');
            const filterText      = document.getElementById('filterIndicatorText');
            filterText.textContent = `Search results for "${productName}"`;
            filterIndicator.classList.remove('hidden');

            applyCatalogFilters();
            selectProduct(productId);
        }

        function resetSearch() {
            searchInput.value = '';
            activeSearchQuery = '';
            suggestionsBox.classList.add('hidden');
            document.getElementById('filterIndicator').classList.add('hidden');
            closeSpotlight();
            applyCatalogFilters();
        }

        document.addEventListener('click', function (e) {
            if (!searchContainer.contains(e.target)) {
                suggestionsBox.classList.add('hidden');
            }
        });

        function applyCatalogFilters() {
            let visibleCount = 0;

            cards.forEach(card => {
                const id = parseInt(card.getAttribute('data-id'));
                const prod = catalogProducts.find(p => p.id === id);

                const matchesQuery = (activeSearchQuery === '') || 
                                     prod.name.toLowerCase().includes(activeSearchQuery) || 
                                     prod.model.toLowerCase().includes(activeSearchQuery) || 
                                     prod.company.toLowerCase().includes(activeSearchQuery);

                const matchesBrand = (activeBrand === 'all' || prod.company.toLowerCase() === activeBrand);

                if (matchesQuery && matchesBrand) {
                    card.classList.remove('hidden');
                    visibleCount++;
                } else {
                    card.classList.add('hidden');
                }
            });

            if (visibleCount === 0 && cards.length > 0) {
                noResults.classList.remove('hidden');
            } else {
                noResults.classList.add('hidden');
            }
        }

        chips.forEach(chip => {
            chip.addEventListener('click', function () {
                chips.forEach(c => {
                    c.classList.remove('bg-slate-900', 'text-amber-300');
                    c.classList.add('bg-slate-100', 'text-slate-700');
                });
                this.classList.remove('bg-slate-100', 'text-slate-700');
                this.classList.add('bg-slate-900', 'text-amber-300');

                activeBrand = this.getAttribute('data-brand');
                applyCatalogFilters();
            });
        });

        // Spotlight Preview
        function selectProduct(productId) {
            const selected = catalogProducts.find(p => p.id === productId);
            if (!selected) return;

            const spotlightSection = document.getElementById('spotlightSection');
            const related = catalogProducts.filter(p => p.company.toLowerCase() === selected.company.toLowerCase() && p.id !== selected.id);
            const waMsg = encodeURIComponent(`Hello Sai Ganapathi Home Needs, I am inquiring about ${selected.company} ${selected.name} (Model: ${selected.model}) listed for ₹${selected.final_price.toLocaleString('en-IN')}. Please confirm stock availability.`);

            let relatedHtml = '';
            if (related.length > 0) {
                relatedHtml = `
                    <div class="mt-8 pt-6 border-t border-slate-200">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h4 class="text-xs sm:text-sm font-black text-slate-900 uppercase tracking-wider">
                                    More from ${escapeHtml(selected.company)}
                                </h4>
                                <p class="text-[10px] text-slate-500">Related in-stock showroom units</p>
                            </div>
                            <span class="text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200 px-2.5 py-0.5 rounded-full">
                                ${related.length} Related
                            </span>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4">
                            ${related.map(r => `
                                <div class="bg-white rounded-2xl border border-slate-200/80 p-3 hover:border-amber-400 transition cursor-pointer flex flex-col justify-between shadow-2xs hover:shadow-md"
                                     onclick="selectProduct(${r.id})">
                                    <div>
                                        <div class="w-full aspect-square bg-slate-50 rounded-xl overflow-hidden mb-2 p-2 flex items-center justify-center">
                                            <img src="${escapeHtml(r.photo)}" alt="${escapeHtml(r.name)}" class="w-full h-full object-contain">
                                        </div>
                                        <span class="text-[9px] font-mono text-slate-400 block">${escapeHtml(r.model)}</span>
                                        <h5 class="text-[11px] font-bold text-slate-900 leading-tight line-clamp-2 mt-0.5">${escapeHtml(r.name)}</h5>
                                    </div>
                                    <div class="mt-2.5 pt-2 border-t border-slate-100 flex items-center justify-between">
                                        <span class="text-xs font-black text-slate-950 font-mono">₹${r.final_price.toLocaleString('en-IN')}</span>
                                        <span class="text-[10px] font-bold text-amber-600">Select →</span>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }

            spotlightSection.innerHTML = `
                <div class="bg-white rounded-3xl border border-amber-400/80 p-4 sm:p-7 shadow-2xl relative">
                    <button onclick="closeSpotlight()" class="absolute top-4 right-4 w-9 h-9 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs flex items-center justify-center transition cursor-pointer z-10">
                        ✕
                    </button>

                    <div class="flex items-center gap-2 text-emerald-700 text-[10px] font-black uppercase tracking-wider mb-3">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Showroom Spotlight Selection
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                        <div class="md:col-span-5 relative">
                            <div class="w-full h-52 sm:h-72 bg-gradient-to-b from-slate-50 to-slate-100/70 rounded-2xl overflow-hidden border border-slate-200 p-4 flex items-center justify-center">
                                <img src="${escapeHtml(selected.photo)}" alt="${escapeHtml(selected.name)}" class="w-full h-full object-contain">
                            </div>
                            ${selected.discount > 0 ? `
                                <span class="absolute top-3 left-3 bg-rose-600 text-white text-[10px] font-black px-2.5 py-0.5 rounded-md shadow-sm uppercase tracking-wider">
                                    ${Math.round(selected.discount)}% OFF
                                </span>
                            ` : ''}
                        </div>

                        <div class="md:col-span-7 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-[10px] font-black uppercase text-amber-800 bg-amber-50 border border-amber-300 px-2 py-0.5 rounded-md">
                                        ${escapeHtml(selected.company)}
                                    </span>
                                    <span class="text-xs font-mono font-bold text-slate-500">
                                        ${escapeHtml(selected.model)}
                                    </span>
                                    <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full ml-auto">
                                        ● ${selected.live_stock} Units In Store
                                    </span>
                                </div>

                                <h3 class="text-lg sm:text-2xl font-black text-slate-900 leading-snug">
                                    ${escapeHtml(selected.name)}
                                </h3>

                                ${selected.specifications ? `
                                    <div class="mt-3 p-3 bg-slate-50 border border-slate-200/70 rounded-2xl text-xs text-slate-600 leading-relaxed font-medium">
                                        ${escapeHtml(selected.specifications)}
                                    </div>
                                ` : ''}

                                ${selected.offer ? `
                                    <div class="mt-2.5 bg-gradient-to-r from-amber-50 to-yellow-50 border border-amber-300 p-2.5 rounded-xl flex items-center gap-2 text-amber-900 text-xs font-bold">
                                        <span>🎁</span>
                                        <span>${escapeHtml(selected.offer)}</span>
                                    </div>
                                ` : ''}
                            </div>

                            <div class="mt-5 pt-4 border-t border-slate-100 flex items-center justify-between gap-3 flex-wrap">
                                <div>
                                    ${selected.discount > 0 ? `
                                        <span class="text-xs text-slate-400 line-through block font-mono">₹${selected.price.toLocaleString('en-IN')}</span>
                                    ` : ''}
                                    <span class="text-2xl sm:text-3xl font-black text-slate-950 font-mono">
                                        ₹${selected.final_price.toLocaleString('en-IN')}
                                    </span>
                                </div>

                                <div class="flex items-center gap-2 flex-wrap">
                                    <button id="spotlightWishBtn" onclick="toggleWishlist(${selected.id}, this, event)" class="px-3.5 py-2.5 rounded-xl text-xs font-bold border transition flex items-center gap-1.5 cursor-pointer ${selected.is_wishlist ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-slate-100 text-slate-700 border-slate-200 hover:bg-slate-200'}">
                                        ${selected.is_wishlist ? '❤️ In Wishlist' : '🤍 Wishlist'}
                                    </button>

                                    <button onclick="openShareModal(${selected.id}, event)" class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                                        <span>🔗 Share</span>
                                    </button>

                                    <a href="https://wa.me/917893282348?text=${waMsg}" target="_blank" class="px-4 py-2.5 bg-[#25D366] hover:bg-[#1EBE5D] text-white rounded-xl text-xs font-black transition flex items-center gap-1.5 shadow-sm">
                                        <span>WhatsApp Inquiry</span>
                                    </a>

                                    <a href="tel:7893282348" class="px-4 py-2.5 bg-slate-950 hover:bg-amber-500 hover:text-slate-950 text-amber-300 rounded-xl text-xs font-black transition shadow-md">
                                        Call Store
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    ${relatedHtml}
                </div>
            `;

            spotlightSection.classList.remove('hidden');
            spotlightSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function closeSpotlight() {
            const spotlightSection = document.getElementById('spotlightSection');
            spotlightSection.classList.add('hidden');
            spotlightSection.innerHTML = '';
        }

        const urlParams = new URLSearchParams(window.location.search);
        const sharedItemId = parseInt(urlParams.get('item_id'));
        if (sharedItemId) {
            window.addEventListener('DOMContentLoaded', () => setTimeout(() => selectProduct(sharedItemId), 600));
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>
</html>