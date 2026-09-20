<?php
session_start();
require 'db.php';

$error = '';
$redirect = trim($_GET['redirect'] ?? 'index.php');

// Helper function to validate and clean mobile numbers
function sanitizePhone($phone) {
    // Strip everything except numbers
    $clean = preg_replace('/[^0-9]/', '', $phone);
    // If country code +91 was included, extract the trailing 10 digits
    if (strlen($clean) > 10) {
        $clean = substr($clean, -10);
    }
    return $clean;
}

// 1. Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $phone    = sanitizePhone($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $redir    = trim($_POST['redirect'] ?? 'index.php');

    if (strlen($phone) !== 10) {
        $error = "Please enter a valid 10-digit mobile number.";
    } elseif (empty($password)) {
        $error = "Please enter your password.";
    } else {
        $stmt = $conn->prepare("SELECT id, name, phone, password FROM customers WHERE phone = ?");
        $stmt->execute([$phone]);
        $customer = $stmt->fetch();

        if ($customer && password_verify($password, $customer['password'])) {
            $_SESSION['customer_logged'] = true;
            $_SESSION['customer_id']     = (int)$customer['id'];
            $_SESSION['customer_name']   = $customer['name'];
            $_SESSION['customer_phone']  = $customer['phone'];
            
            // Prevent open redirect vulnerabilities
            $safeRedir = (strpos($redir, 'http://') === 0 || strpos($redir, 'https://') === 0) ? 'index.php' : $redir;
            header("Location: " . $safeRedir);
            exit;
        } else {
            $error = "Invalid mobile number or password.";
        }
    }
}

// 2. Handle Registration with strict duplicate and length validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $name     = trim($_POST['name'] ?? '');
    $phone    = sanitizePhone($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $redir    = trim($_POST['redirect'] ?? 'index.php');

    if (empty($name) || empty($phone) || empty($password)) {
        $error = "Please fill in all registration fields.";
    } elseif (strlen($phone) !== 10) {
        $error = "Mobile number must be exactly 10 digits.";
    } elseif (strlen($password) < 4) {
        $error = "Password must be at least 4 characters long.";
    } else {
        try {
            // Check if phone number already exists
            $check = $conn->prepare("SELECT id FROM customers WHERE phone = ?");
            $check->execute([$phone]);
            if ($check->fetch()) {
                $error = "This mobile number is already registered. Please Sign In instead.";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO customers (name, phone, password) VALUES (?, ?, ?)");
                $stmt->execute([$name, $phone, $hashed]);

                $_SESSION['customer_logged'] = true;
                $_SESSION['customer_id']     = (int)$conn->lastInsertId();
                $_SESSION['customer_name']   = $name;
                $_SESSION['customer_phone']  = $phone;

                $safeRedir = (strpos($redir, 'http://') === 0 || strpos($redir, 'https://') === 0) ? 'index.php' : $redir;
                header("Location: " . $safeRedir);
                exit;
            }
        } catch (PDOException $e) {
            $error = "Registration error: This mobile number is already registered.";
        }
    }
}

// 3. Logout Handler
if (isset($_GET['logout'])) {
    unset($_SESSION['customer_logged'], $_SESSION['customer_id'], $_SESSION['customer_name'], $_SESSION['customer_phone']);
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Account - SAI GANAPATHI HOME NEEDS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-white rounded-3xl border border-slate-200 shadow-xl p-6 sm:p-8">
        
        <div class="text-center mb-6">
            <span class="inline-block p-3 bg-rose-50 text-rose-600 rounded-2xl text-2xl mb-2">❤️</span>
            <h2 class="text-xl font-black text-slate-900">Customer Access</h2>
            <p class="text-xs text-slate-500 mt-1">Sign in to save and manage your private wishlist</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold rounded-xl text-center">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="flex border-b border-slate-200 mb-5">
            <button onclick="switchTab('login')" id="tabLogin" class="w-1/2 pb-2 text-xs font-black uppercase tracking-wider text-amber-600 border-b-2 border-amber-500 cursor-pointer">
                Sign In
            </button>
            <button onclick="switchTab('register')" id="tabRegister" class="w-1/2 pb-2 text-xs font-black uppercase tracking-wider text-slate-400 cursor-pointer">
                Register
            </button>
        </div>

        <!-- Login Form -->
        <form id="loginForm" method="POST" action="customer_auth.php" class="space-y-3">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

            <div>
                <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-1">Mobile Number (10 Digits)</label>
                <input type="tel" name="phone" required maxlength="10" placeholder="e.g. 7893282348" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-amber-500">
            </div>
            <div>
                <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-1">Password</label>
                <input type="password" name="password" required placeholder="••••••••" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-amber-500">
            </div>
            <button type="submit" class="w-full py-2.5 bg-slate-900 hover:bg-amber-500 hover:text-slate-950 text-white text-xs font-black uppercase tracking-wider rounded-xl transition cursor-pointer">
                Sign In
            </button>
        </form>

        <!-- Register Form -->
        <form id="registerForm" method="POST" action="customer_auth.php" class="hidden space-y-3">
            <input type="hidden" name="action" value="register">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

            <div>
                <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-1">Full Name</label>
                <input type="text" name="name" required placeholder="Full Name" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-amber-500">
            </div>
            <div>
                <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-1">Mobile Number (Unique)</label>
                <input type="tel" name="phone" required maxlength="10" placeholder="e.g. 7893282348" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-amber-500">
            </div>
            <div>
                <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-1">Create Password (Min 4 chars)</label>
                <input type="password" name="password" required minlength="4" placeholder="••••••••" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-amber-500">
            </div>
            <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black uppercase tracking-wider rounded-xl transition cursor-pointer">
                Create Account
            </button>
        </form>

        <div class="mt-5 text-center">
            <a href="index.php" class="text-xs font-bold text-slate-400 hover:text-slate-600">← Return to Storefront</a>
        </div>
    </div>

    <script>
        function switchTab(type) {
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            const tabLogin = document.getElementById('tabLogin');
            const tabRegister = document.getElementById('tabRegister');

            if (type === 'login') {
                loginForm.classList.remove('hidden');
                registerForm.classList.add('hidden');
                tabLogin.className = "w-1/2 pb-2 text-xs font-black uppercase tracking-wider text-amber-600 border-b-2 border-amber-500 cursor-pointer";
                tabRegister.className = "w-1/2 pb-2 text-xs font-black uppercase tracking-wider text-slate-400 cursor-pointer";
            } else {
                loginForm.classList.add('hidden');
                registerForm.classList.remove('hidden');
                tabRegister.className = "w-1/2 pb-2 text-xs font-black uppercase tracking-wider text-amber-600 border-b-2 border-amber-500 cursor-pointer";
                tabLogin.className = "w-1/2 pb-2 text-xs font-black uppercase tracking-wider text-slate-400 cursor-pointer";
            }
        }
    </script>
</body>
</html>