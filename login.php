<?php
session_start();
require 'db.php';

// Handle logout parameter if passed
if (isset($_GET['action']) && $_GET['action'] === 'reset') {
    session_unset();
    session_destroy();
    session_start();
}

// Redirect if already logged in properly
if (!empty($_SESSION['user_logged']) && !empty($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/admin_dashboard.php");
        exit;
    } elseif ($_SESSION['role'] === 'counter') {
        header("Location: counter/index.php");
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        try {
            // Case-insensitive / whitespace-trimmed user lookup
            $stmt = $conn->prepare("SELECT id, username, password, role FROM logins WHERE LOWER(TRIM(username)) = LOWER(?) LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $isValid = false;

            if ($user) {
                $storedPassword = trim($user['password']);

                // Method 1: Standard Password Verify (Bcrypt/Argon2)
                if (password_verify($password, $storedPassword)) {
                    $isValid = true;
                }
                // Method 2: Plain text match (auto-updates database to secure hash)
                elseif ($password === $storedPassword) {
                    $isValid = true;
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $upd = $conn->prepare("UPDATE logins SET password = ? WHERE id = ?");
                    $upd->execute([$newHash, $user['id']]);
                }
            }

            if ($user && $isValid) {
                // Clear old session variables before assigning new ones
                session_regenerate_id(true);

                $role = strtolower(trim($user['role']));

                $_SESSION['user_logged']    = true;
                $_SESSION['user_id']        = $user['id'];
                $_SESSION['username']       = $user['username'];
                $_SESSION['user_name']      = $user['username'];
                $_SESSION['role']           = $role;

                if ($role === 'admin') {
                    $_SESSION['admin_logged'] = true;
                    $_SESSION['admin_name']   = $user['username'];
                    header("Location: admin/admin_dashboard.php");
                    exit;
                } elseif ($role === 'counter') {
                    $_SESSION['counter_logged'] = true;
                    header("Location: counter/index.php");
                    exit;
                } else {
                    $error = "Access denied: Unrecognized role '{$role}'.";
                }
            } else {
                $error = "Invalid username or password. Please verify your credentials.";
            }
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Showroom Staff & Admin Login - SAI GANAPATHI HOME NEEDS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
</head>
<body class="bg-gradient-to-br from-slate-950 via-indigo-950 to-slate-900 min-h-screen flex items-center justify-center p-4 antialiased text-slate-800">

    <div class="max-w-md w-full bg-white/95 backdrop-blur-md rounded-3xl shadow-2xl shadow-black/60 p-8 border-t-4 border-amber-400 relative overflow-hidden animate__animated animate__fadeInDown">
        
        <div class="absolute -top-12 -right-12 w-32 h-32 bg-amber-400/20 rounded-full blur-2xl pointer-events-none"></div>

        <div class="text-center mb-6 relative">
            <div class="flex items-center justify-center gap-2 mb-3">
                <div class="w-12 h-12 rounded-2xl border-2 border-amber-400 bg-slate-950 p-1.5 shadow-md flex items-center justify-center">
                    <svg viewBox="0 0 100 100" class="w-full h-full stroke-amber-400 fill-none" stroke-width="4" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M42 20 L50 8 L58 20 Z" fill="#f59e0b" fill-opacity="0.4"/>
                        <path d="M36 28 C18 28, 14 46, 32 54" />
                        <path d="M64 28 C82 28, 86 46, 68 54" />
                        <path d="M50 24 C38 24, 38 42, 45 52 C52 62, 54 74, 42 78 C34 81, 28 72, 34 66" />
                        <line x1="60" y1="52" x2="68" y2="52" />
                        <circle cx="50" cy="36" r="2.5" fill="#ef4444" stroke="none"/>
                        <circle cx="34" cy="65" r="3" fill="#f59e0b" stroke="#fbbf24"/>
                    </svg>
                </div>
                <div class="w-12 h-12 rounded-2xl border-2 border-amber-400 bg-slate-900 p-1 shadow-md flex flex-col items-center justify-center">
                    <span class="text-base font-black tracking-tighter bg-clip-text text-transparent bg-gradient-to-tr from-amber-300 to-orange-400 font-serif leading-none">SG</span>
                </div>
            </div>

            <span class="inline-block px-2.5 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-gradient-to-r from-amber-500 to-orange-500 text-white shadow-sm mb-1">
                PROP: GANESH
            </span>
            <h2 class="text-xl font-black text-slate-900 tracking-tight leading-tight">SAI GANAPATHI HOME NEEDS</h2>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Narsipatnam • Unified Access Portal</p>
        </div>

        <div class="grid grid-cols-2 gap-2 mb-6 bg-slate-100 p-2 rounded-xl text-center">
            <div class="bg-white py-1.5 px-2 rounded-lg border border-slate-200 shadow-xs">
                <span class="text-[10px] font-extrabold uppercase tracking-wide text-indigo-700 block">👑 Admin</span>
                <span class="text-[9px] text-slate-500 block">Full Store Control</span>
            </div>
            <div class="bg-white py-1.5 px-2 rounded-lg border border-slate-200 shadow-xs">
                <span class="text-[10px] font-extrabold uppercase tracking-wide text-emerald-700 block">🧾 Counter</span>
                <span class="text-[9px] text-slate-500 block">Billing Desk Terminal</span>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-700 text-xs font-semibold p-3 rounded-xl mb-4 flex items-center gap-2 animate__animated animate__shakeX">
                <span>⚠️</span>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-4">
            <div>
                <label for="username" class="block text-xs font-bold uppercase text-slate-600 mb-1">Username</label>
                <div class="relative">
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required 
                        autocomplete="username"
                        placeholder="Enter username (e.g. 1001)" 
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        class="w-full pl-3 pr-10 py-2.5 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-amber-500 focus:bg-white focus:ring-2 focus:ring-amber-500/20 transition font-medium"
                    >
                    <span class="absolute right-3 top-2.5 text-slate-400 text-sm">👤</span>
                </div>
            </div>

            <div>
                <label for="password" class="block text-xs font-bold uppercase text-slate-600 mb-1">Password</label>
                <div class="relative">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="w-full pl-3 pr-10 py-2.5 bg-slate-50 border-2 border-slate-200 rounded-xl text-sm focus:outline-none focus:border-amber-500 focus:bg-white focus:ring-2 focus:ring-amber-500/20 transition font-medium"
                    >
                    <span class="absolute right-3 top-2.5 text-slate-400 text-sm">🔒</span>
                </div>
            </div>

            <button 
                type="submit" 
                class="w-full py-3 bg-gradient-to-r from-amber-500 via-orange-500 to-amber-600 hover:from-amber-600 hover:to-orange-700 text-white font-black text-sm uppercase tracking-wider rounded-xl shadow-lg shadow-orange-500/30 active:scale-[0.98] transition cursor-pointer mt-2"
            >
                Authorize & Login
            </button>
        </form>

        <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
            <a href="login.php?action=reset" class="text-rose-500 hover:text-rose-700 font-semibold text-[11px]">
                Clear Session
            </a>
            <span class="text-[11px] text-slate-400">Helpline: 7893282348</span>
        </div>

    </div>

</body>
</html>