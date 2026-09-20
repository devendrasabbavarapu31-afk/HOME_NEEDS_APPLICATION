<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_logged']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Live counts for navigation badges
$navTotalProducts = 0;
try {
    $navTotalProducts = $conn->query("SELECT COUNT(*) FROM products")->fetchColumn() ?: 0;
} catch (Exception $e) { $navTotalProducts = 0; }

$navTotalIntake = 0;
try {
    $navTotalIntake = $conn->query("SELECT COUNT(*) FROM stock_intake")->fetchColumn() ?: 0;
} catch (Exception $e) { $navTotalIntake = 0; }

$navTotalVendors = 0;
try {
    $navTotalVendors = $conn->query("SELECT COUNT(*) FROM vendors")->fetchColumn() ?: 0;
} catch (Exception $e) { $navTotalVendors = 0; }

$navPublicCount = 0;
try {
    $navPublicCount = $conn->query("SELECT COUNT(*) FROM public_products WHERE status = 'active'")->fetchColumn() ?: 0;
} catch (Exception $e) { $navPublicCount = 0; }

$navTotalStaff = 0;
try {
    $navTotalStaff = $conn->query("SELECT COUNT(*) FROM staff_members WHERE is_active = 1")->fetchColumn() ?: 0;
} catch (Exception $e) { $navTotalStaff = 0; }

$navTodayAttendance = 0;
try {
    $navTodayAttendance = $conn->query("SELECT COUNT(DISTINCT staff_id) FROM staff_attendance WHERE DATE(punch_time) = CURRENT_DATE()")->fetchColumn() ?: 0;
} catch (Exception $e) { $navTodayAttendance = 0; }

$currentPage = $currentPage ?? 'dashboard';
?>

<!-- Mobile backdrop -->
<div id="sidebarBackdrop" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-40 hidden lg:hidden transition-opacity"></div>

<!-- Sidebar Container -->
<aside id="mainSidebar" class="w-72 bg-white/95 backdrop-blur-xl border-r border-slate-200/80 shadow-md lg:shadow-none shrink-0 h-screen sticky top-0 z-50 transition-all duration-300 ease-in-out flex flex-col justify-between overflow-hidden">
    
    <div class="p-5 flex flex-col h-full justify-between overflow-y-auto w-72">
        <div>
            <!-- Showroom Brand Header -->
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-amber-500 via-orange-500 to-amber-600 p-0.5 shadow-md shadow-orange-500/20 flex items-center justify-center shrink-0">
                        <div class="w-full h-full bg-slate-950 rounded-[14px] flex items-center justify-center">
                            <span class="text-sm font-black tracking-tight text-amber-400 font-serif">SG</span>
                        </div>
                    </div>
                    <div class="overflow-hidden">
                        <h1 class="text-xs font-black text-slate-900 tracking-wider uppercase truncate">SAI GANAPATHI</h1>
                        <p class="text-[10px] font-extrabold tracking-widest text-amber-600 uppercase flex items-center gap-1 mt-0.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Admin Suite
                        </p>
                    </div>
                </div>
                <button type="button" onclick="toggleSidebar()" class="lg:hidden p-1.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 text-sm font-bold">
                    ✕
                </button>
            </div>

            <!-- Logged-in Staff Badge -->
            <div class="my-3.5 p-2.5 bg-slate-50/80 rounded-2xl border border-slate-200/60 flex items-center gap-3 shadow-2xs">
                <div class="w-8 h-8 rounded-xl bg-slate-900 text-amber-300 flex items-center justify-center text-xs font-black shadow-xs">
                    <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="overflow-hidden">
                    <span class="text-xs font-extrabold text-slate-800 block truncate leading-tight"><?= htmlspecialchars($_SESSION['username'] ?? 'Administrator') ?></span>
                    <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider block mt-0.5">● Master Admin</span>
                </div>
            </div>

            <!-- Operations Section -->
            <div class="px-2 pt-1 pb-1 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                Operations & Financials
            </div>

            <nav class="space-y-1 text-xs font-bold">
                <!-- 1. Executive Dashboard -->
                <a href="admin_dashboard.php" 
                   class="flex items-center justify-between px-3 py-2 rounded-xl transition group <?= ($currentPage === 'dashboard') ? 'bg-slate-950 text-amber-300 shadow-md shadow-slate-950/15' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80' ?>">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center text-sm <?= ($currentPage === 'dashboard') ? 'bg-white/10' : 'bg-slate-100 text-slate-700' ?>">📊</span>
                        <span class="font-extrabold">Executive Ledger</span>
                    </span>
                    <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-md tracking-wider <?= ($currentPage === 'dashboard') ? 'bg-amber-400 text-slate-950' : 'bg-emerald-50 text-emerald-700 border border-emerald-200/60' ?>">
                        Profits
                    </span>
                </a>

                <!-- 2. Product Registry -->
                <a href="register_product.php" 
                   class="flex items-center justify-between px-3 py-2 rounded-xl transition group <?= ($currentPage === 'registry') ? 'bg-slate-950 text-amber-300 shadow-md shadow-slate-950/15' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80' ?>">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center text-sm <?= ($currentPage === 'registry') ? 'bg-white/10' : 'bg-slate-100 text-slate-700' ?>">📋</span>
                        <span class="font-extrabold">Product Registry</span>
                    </span>
                    <span class="text-[11px] px-2 py-0.5 rounded-full font-mono font-extrabold <?= ($currentPage === 'registry') ? 'bg-amber-400 text-slate-950' : 'bg-slate-100 text-slate-600' ?>">
                        <?= $navTotalProducts ?>
                    </span>
                </a>

                <!-- 3. Vendors -->
                <a href="vendors.php" 
                   class="flex items-center justify-between px-3 py-2 rounded-xl transition group <?= ($currentPage === 'vendors') ? 'bg-slate-950 text-amber-300 shadow-md shadow-slate-950/15' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80' ?>">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center text-sm <?= ($currentPage === 'vendors') ? 'bg-white/10' : 'bg-slate-100 text-slate-700' ?>">🏢</span>
                        <span class="font-extrabold">Vendors & Dues</span>
                    </span>
                    <span class="text-[11px] px-2 py-0.5 rounded-full font-mono font-extrabold <?= ($currentPage === 'vendors') ? 'bg-amber-400 text-slate-950' : 'bg-amber-50 text-amber-800 border border-amber-200/60' ?>">
                        <?= $navTotalVendors ?>
                    </span>
                </a>

                <!-- 4. Stock Intake -->
                <a href="stock_intake.php" 
                   class="flex items-center justify-between px-3 py-2 rounded-xl transition group <?= ($currentPage === 'intake') ? 'bg-slate-950 text-amber-300 shadow-md shadow-slate-950/15' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80' ?>">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center text-sm <?= ($currentPage === 'intake') ? 'bg-white/10' : 'bg-slate-100 text-slate-700' ?>">📥</span>
                        <span class="font-extrabold">Stock Intake & Pricing</span>
                    </span>
                    <span class="text-[11px] px-2 py-0.5 rounded-full font-mono font-extrabold <?= ($currentPage === 'intake') ? 'bg-amber-400 text-slate-950' : 'bg-blue-50 text-blue-700 border border-blue-200/60' ?>">
                        <?= $navTotalIntake ?>
                    </span>
                </a>

                <!-- 5. Publish Showcase -->
                <a href="publish_product.php" 
                   class="flex items-center justify-between px-3 py-2 rounded-xl transition group <?= ($currentPage === 'publish') ? 'bg-slate-950 text-amber-300 shadow-md shadow-slate-950/15' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80' ?>">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center text-sm <?= ($currentPage === 'publish') ? 'bg-white/10' : 'bg-slate-100 text-slate-700' ?>">🚀</span>
                        <span class="font-extrabold">Showroom Showcase</span>
                    </span>
                    <span class="text-[11px] px-2 py-0.5 rounded-full font-mono font-extrabold <?= ($currentPage === 'publish') ? 'bg-amber-400 text-slate-950' : 'bg-emerald-50 text-emerald-700 border border-emerald-200/60' ?>">
                        <?= $navPublicCount ?>
                    </span>
                </a>

                <!-- 6. Expenses & Payroll -->
                <a href="expenses.php" 
                   class="flex items-center justify-between px-3 py-2 rounded-xl transition group <?= ($currentPage === 'expenses') ? 'bg-slate-950 text-amber-300 shadow-md shadow-slate-950/15' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80' ?>">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center text-sm <?= ($currentPage === 'expenses') ? 'bg-white/10' : 'bg-slate-100 text-slate-700' ?>">💸</span>
                        <span class="font-extrabold">Expense & Payroll</span>
                    </span>
                    <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-md tracking-wider bg-rose-50 text-rose-700 border border-rose-200/60">
                        Overhead
                    </span>
                </a>

                <!-- Staff & Attendance Section -->
                <div class="px-2 pt-3 pb-1 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                    Staff & Biometrics
                </div>

                <!-- 7. Staff Management -->
                <a href="staff_manage.php" 
                   class="flex items-center justify-between px-3 py-2 rounded-xl transition group <?= ($currentPage === 'staff') ? 'bg-slate-950 text-amber-300 shadow-md shadow-slate-950/15' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80' ?>">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center text-sm <?= ($currentPage === 'staff') ? 'bg-white/10' : 'bg-slate-100 text-slate-700' ?>">👥</span>
                        <span class="font-extrabold">Staff Directory</span>
                    </span>
                    <span class="text-[11px] px-2 py-0.5 rounded-full font-mono font-extrabold <?= ($currentPage === 'staff') ? 'bg-amber-400 text-slate-950' : 'bg-slate-100 text-slate-600' ?>">
                        <?= $navTotalStaff ?>
                    </span>
                </a>

                <!-- 8. Attendance Logs -->
                <a href="attendance_logs.php" 
                   class="flex items-center justify-between px-3 py-2 rounded-xl transition group <?= ($currentPage === 'attendance_logs') ? 'bg-slate-950 text-amber-300 shadow-md shadow-slate-950/15' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80' ?>">
                    <span class="flex items-center gap-2.5">
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center text-sm <?= ($currentPage === 'attendance_logs') ? 'bg-white/10' : 'bg-slate-100 text-slate-700' ?>">⏱️</span>
                        <span class="font-extrabold">Attendance Logs</span>
                    </span>
                    <span class="text-[11px] px-2 py-0.5 rounded-full font-mono font-extrabold <?= ($currentPage === 'attendance_logs') ? 'bg-amber-400 text-slate-950' : 'bg-emerald-50 text-emerald-700 border border-emerald-200/60' ?>">
                        <?= $navTodayAttendance ?>
                    </span>
                </a>
            </nav>
        </div>

        <!-- Sign Out -->
        <div class="pt-3 border-t border-slate-100 space-y-1.5">
            <a href="../logout.php" class="flex items-center justify-center gap-2 w-full py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-extrabold rounded-xl border border-rose-200/80 transition">
                <span></span>
                <span>Sign Out</span>
            </a>
            <p class="text-[9px] text-slate-400 text-center font-mono">Sai Ganapathi Home Needs</p>
        </div>
    </div>
</aside>