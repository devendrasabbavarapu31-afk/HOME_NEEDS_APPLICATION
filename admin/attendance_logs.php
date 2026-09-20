<?php
session_start();

if (!isset($_SESSION['user_logged']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../db.php';
$currentPage = 'attendance_logs';
$success = '';
$error = '';

// Handle Admin Concession Toggle Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_concession') {
    $attId  = (int)($_POST['attendance_id'] ?? 0);
    $setTo  = (int)($_POST['set_concession'] ?? 0);
    $reason = trim($_POST['concession_reason'] ?? '');

    if ($attId > 0) {
        try {
            $stmt = $conn->prepare("UPDATE staff_attendance SET is_concession = ?, concession_reason = ? WHERE id = ?");
            $stmt->execute([$setTo, $setTo ? $reason : null, $attId]);
            $success = $setTo ? "Concession granted successfully (Orange Status)." : "Concession revoked (Red Status).";
        } catch (PDOException $e) {
            $error = "Failed to update concession: " . $e->getMessage();
        }
    }
}

// Filter Controls
$selectedDate = $_GET['log_date'] ?? date('Y-m-d');
$selectedStaff = (int)($_GET['staff_id'] ?? 0);

// Fetch Staff for Filter Dropdown
$allStaff = $conn->query("SELECT id, name, staff_code FROM staff_members ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Base Query for Attendance Logs
$sql = "
    SELECT 
        sa.*, 
        sm.name AS staff_name, 
        sm.staff_code, 
        sm.designation,
        sm.phone
    FROM staff_attendance sa
    JOIN staff_members sm ON sm.id = sa.staff_id
    WHERE DATE(sa.punch_time) = :log_date
";

$params = [':log_date' => $selectedDate];

if ($selectedStaff > 0) {
    $sql .= " AND sa.staff_id = :staff_id";
    $params[':staff_id'] = $selectedStaff;
}

$sql .= " ORDER BY sa.punch_time DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$attendanceLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Attendance Logs - SAI GANAPATHI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800 antialiased flex">

    <?php include 'sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <!-- Header -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
            <div>
                <h2 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">Daily Attendance Logs</h2>
                <p class="text-xs text-slate-500 font-medium">Valid Windows: 09:00 - 10:00 AM | 08:00 - 09:00 PM</p>
            </div>
            
            <!-- Filters -->
            <form method="GET" class="flex items-center gap-2">
                <input type="date" name="log_date" value="<?= htmlspecialchars($selectedDate) ?>" class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-amber-500">
                
                <select name="staff_id" class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-amber-500">
                    <option value="0">All Staff</option>
                    <?php foreach ($allStaff as $staff): ?>
                        <option value="<?= $staff['id'] ?>" <?= $selectedStaff === (int)$staff['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($staff['name']) ?> (<?= htmlspecialchars($staff['staff_code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-amber-300 rounded-xl text-xs font-bold transition">
                    Filter
                </button>
            </form>
        </header>

        <!-- Main Body -->
        <main class="p-6 max-w-7xl w-full mx-auto space-y-6">

            <?php if (!empty($success)): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-bold shadow-xs">
                    <?= $success ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-2xl text-xs font-bold shadow-xs">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Table Card -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">
                            Punches on <?= date('d M Y', strtotime($selectedDate)) ?>
                        </h3>
                        <p class="text-[11px] text-slate-400">Showing <?= count($attendanceLogs) ?> logged punches</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 text-slate-500 font-bold uppercase text-[10px] border-b border-slate-200">
                                <th class="p-3.5">Staff</th>
                                <th class="p-3.5">Punch Type</th>
                                <th class="p-3.5">Exact Time</th>
                                <th class="p-3.5">Timing Status</th>
                                <th class="p-3.5">Location Verified</th>
                                <th class="p-3.5 text-center">Concession Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($attendanceLogs)): ?>
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-400 font-semibold">
                                        No attendance logs found for this date.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendanceLogs as $log): 
                                    $timeStr      = date('H:i:s', strtotime($log['punch_time']));
                                    $isMorning    = ($timeStr >= '09:00:00' && $timeStr <= '10:00:00');
                                    $isEvening    = ($timeStr >= '20:00:00' && $timeStr <= '21:00:00');
                                    $isOnTime     = ($isMorning || $isEvening);
                                    $isConcession = (bool)($log['is_concession'] ?? false);
                                ?>
                                    <tr class="hover:bg-slate-50">
                                        <!-- Staff Details -->
                                        <td class="p-3.5">
                                            <span class="font-bold text-slate-900 block"><?= htmlspecialchars($log['staff_name']) ?></span>
                                            <span class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($log['staff_code']) ?> | <?= htmlspecialchars($log['designation']) ?></span>
                                        </td>

                                        <!-- Punch Type -->
                                        <td class="p-3.5">
                                            <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase <?= $log['punch_type'] === 'in' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-slate-100 text-slate-700 border border-slate-200' ?>">
                                                PUNCH <?= strtoupper($log['punch_type']) ?>
                                            </span>
                                        </td>

                                        <!-- Exact Punch Time -->
                                        <td class="p-3.5 font-mono text-slate-800 font-bold">
                                            <?= date('h:i:s A', strtotime($log['punch_time'])) ?>
                                        </td>

                                        <!-- Color-coded Badge -->
                                        <td class="p-3.5">
                                            <?php if ($isConcession): ?>
                                                <div>
                                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-50 text-amber-700 border border-amber-300">
                                                        ● Concession Granted
                                                    </span>
                                                    <?php if (!empty($log['concession_reason'])): ?>
                                                        <span class="block text-[10px] text-amber-600 mt-0.5 italic">
                                                            Note: <?= htmlspecialchars($log['concession_reason']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php elseif ($isOnTime): ?>
                                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-50 text-emerald-700 border border-emerald-300">
                                                    ● On Time
                                                </span>
                                            <?php else: ?>
                                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-rose-50 text-rose-700 border border-rose-300">
                                                    ● Late / Restricted
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Location Check -->
                                        <td class="p-3.5">
                                            <?php if ($log['location_verified']): ?>
                                                <span class="text-emerald-600 font-bold text-[11px]">✓ Verified</span>
                                            <?php else: ?>
                                                <span class="text-rose-500 font-bold text-[11px]">✗ Outside Store</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Concession Action Form -->
                                        <td class="p-3.5 text-center">
                                            <?php if (!$isOnTime): ?>
                                                <form method="POST" action="" class="flex items-center justify-center gap-1.5">
                                                    <input type="hidden" name="action" value="toggle_concession">
                                                    <input type="hidden" name="attendance_id" value="<?= $log['id'] ?>">

                                                    <?php if ($isConcession): ?>
                                                        <input type="hidden" name="set_concession" value="0">
                                                        <button type="submit" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[10px] rounded-lg border border-slate-300 transition">
                                                            Revoke
                                                        </button>
                                                    <?php else: ?>
                                                        <input type="hidden" name="set_concession" value="1">
                                                        <input type="text" name="concession_reason" placeholder="Reason (e.g., Traffic)" class="px-2 py-0.5 border border-slate-200 rounded text-[10px] w-28 focus:outline-none focus:border-amber-500">
                                                        <button type="submit" class="px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white font-bold text-[10px] rounded-lg transition shadow-xs">
                                                            Grant
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-[11px] text-slate-300 italic">N/A (On Time)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</body>
</html>