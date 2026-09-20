<?php
session_start();

if (!isset($_SESSION['user_logged']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

require '../db.php';
$currentPage = 'staff';
$success = '';
$error = '';

// 1. Handle Staff Enrollment / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_staff') {
    $code        = trim($_POST['staff_code'] ?? '');
    $name        = trim($_POST['name'] ?? '');
    $designation = trim($_POST['designation'] ?? 'Store Staff');
    $phone       = trim($_POST['phone'] ?? '');
    $descriptor  = trim($_POST['face_descriptor'] ?? '');

    if (empty($code) || empty($name) || empty($phone)) {
        $error = "Staff Code, Full Name, and Phone are required.";
    } elseif (empty($descriptor) || $descriptor === '[]') {
        $error = "Face not captured. Please look at the camera and click 'Capture Face ID'.";
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO staff_members (staff_code, name, designation, phone, face_descriptor) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    name = VALUES(name), 
                    designation = VALUES(designation), 
                    phone = VALUES(phone), 
                    face_descriptor = VALUES(face_descriptor)
            ");
            $stmt->execute([$code, $name, $designation, $phone, $descriptor]);
            $success = "Staff profile & Biometric Vector for <strong>" . htmlspecialchars($name) . "</strong> saved successfully!";
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// 2. Handle Drop / Delete Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_staff') {
    $staffId = (int)($_POST['staff_id'] ?? 0);
    if ($staffId > 0) {
        try {
            $delStmt = $conn->prepare("DELETE FROM staff_members WHERE id = ?");
            $delStmt->execute([$staffId]);
            $success = "Staff member removed successfully!";
        } catch (PDOException $e) {
            $error = "Failed to remove staff: " . $e->getMessage();
        }
    }
}

// 3. Handle Admin Concession Toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_concession') {
    $attId = (int)($_POST['attendance_id'] ?? 0);
    $setTo = (int)($_POST['set_concession'] ?? 0);

    if ($attId > 0) {
        try {
            $updStmt = $conn->prepare("UPDATE staff_attendance SET is_concession = ? WHERE id = ?");
            $updStmt->execute([$setTo, $attId]);
            $success = $setTo ? "Concession granted successfully (Orange Status)." : "Concession revoked (Red Status).";
        } catch (PDOException $e) {
            $error = "Failed to update concession: " . $e->getMessage();
        }
    }
}

// Fetch Staff with Latest Attendance Info
$staffList = $conn->query("
    SELECT sm.*, 
        sa.id AS last_att_id,
        sa.punch_time AS last_punch,
        sa.punch_type AS last_status,
        sa.is_concession
    FROM staff_members sm 
    LEFT JOIN staff_attendance sa ON sa.id = (
        SELECT id FROM staff_attendance 
        WHERE staff_id = sm.id 
        ORDER BY id DESC LIMIT 1
    )
    ORDER BY sm.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Master & Attendance Control - SAI GANAPATHI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/dist/face-api.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800 antialiased flex">

    <?php include 'sidebar.php'; ?>

    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 px-6 py-4 flex items-center justify-between shadow-xs">
            <div>
                <h2 class="text-base sm:text-lg font-black text-slate-900 tracking-tight">Staff Master & Attendance Control</h2>
                <p class="text-xs text-slate-500 font-medium">Valid Slots: Morning 09:00 - 10:00 AM | Evening 08:00 - 09:00 PM</p>
            </div>
            <a href="../counter/attendance.php" target="_blank" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5">
                <span>📸</span>
                <span>Open Attendance Kiosk ↗</span>
            </a>
        </header>

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

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- Face Enrollment & Registration -->
                <div class="lg:col-span-5 bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-4 sticky top-24">
                    <div class="pb-3 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Enroll Face & Profile</h3>
                        <span id="aiStatus" class="text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-md">Loading AI Models...</span>
                    </div>

                    <div class="relative w-full aspect-video bg-slate-950 rounded-2xl overflow-hidden border border-slate-800 flex items-center justify-center">
                        <video id="videoFeed" autoplay muted playsinline class="w-full h-full object-cover"></video>
                        <canvas id="overlayCanvas" class="absolute inset-0 pointer-events-none w-full h-full"></canvas>
                        <div id="camPlaceholder" class="absolute text-slate-500 text-xs font-bold">Camera Offline</div>
                    </div>

                    <div class="flex gap-2">
                        <button type="button" id="btnStartCam" onclick="startCamera()" class="w-1/2 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition cursor-pointer">
                            Start Cam
                        </button>
                        <button type="button" id="btnCapture" onclick="captureFaceDescriptor()" disabled class="w-1/2 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white font-bold text-xs rounded-xl shadow-xs transition cursor-pointer">
                            Capture Face ID
                        </button>
                    </div>

                    <form method="POST" action="staff_manage.php" class="space-y-3 pt-2">
                        <input type="hidden" name="action" value="save_staff">
                        <input type="hidden" id="faceDescriptorInput" name="face_descriptor" value="">

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-0.5">Staff Code *</label>
                                <input type="text" name="staff_code" required placeholder="SG-01" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono font-bold focus:outline-none focus:border-amber-500">
                            </div>
                            <div>
                                <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-0.5">Designation</label>
                                <input type="text" name="designation" placeholder="Sales Executive" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-amber-500">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-0.5">Full Name *</label>
                            <input type="text" name="name" required placeholder="K. Ramesh" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-amber-500">
                        </div>

                        <div>
                            <label class="block text-[10px] font-extrabold uppercase text-slate-500 mb-0.5">Phone Number *</label>
                            <input type="tel" name="phone" required placeholder="9876543210" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold focus:outline-none focus:border-amber-500">
                        </div>

                        <div id="vectorBadge" class="p-2.5 rounded-xl border text-center text-xs font-mono font-bold bg-slate-50 text-slate-400 border-slate-200">
                            No biometric face scanned
                        </div>

                        <button type="submit" id="btnSubmit" disabled class="w-full py-2.5 bg-slate-900 disabled:opacity-40 text-amber-300 font-bold text-xs uppercase tracking-wider rounded-xl shadow-xs transition cursor-pointer">
                            Save Staff Biometric Profile
                        </button>
                    </form>
                </div>

                <!-- Registered Staff Directory Table -->
                <div class="lg:col-span-7 bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-black uppercase tracking-wider text-slate-900">Staff Attendance & Directory</h3>
                            <p class="text-[11px] text-slate-400">Total <?= count($staffList) ?> members</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500 font-bold uppercase text-[10px] border-b border-slate-200">
                                    <th class="p-3.5">Staff Member</th>
                                    <th class="p-3.5">Designation</th>
                                    <th class="p-3.5 text-center">Biometric</th>
                                    <th class="p-3.5">Attendance Status</th>
                                    <th class="p-3.5 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php if (empty($staffList)): ?>
                                    <tr><td colspan="5" class="p-8 text-center text-slate-400 font-semibold">No staff registered yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($staffList as $s): ?>
                                        <tr class="hover:bg-slate-50">
                                            <td class="p-3.5">
                                                <span class="font-bold text-slate-900 block"><?= htmlspecialchars($s['name']) ?></span>
                                                <span class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($s['staff_code']) ?> | <?= htmlspecialchars($s['phone']) ?></span>
                                            </td>
                                            <td class="p-3.5 text-slate-600 font-medium">
                                                <?= htmlspecialchars($s['designation']) ?>
                                            </td>
                                            <td class="p-3.5 text-center">
                                                <?php if (!empty($s['face_descriptor'])): ?>
                                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                        Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-rose-50 text-rose-700 border border-rose-200">
                                                        Unenrolled
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-3.5 font-mono">
                                                <?php if (!empty($s['last_punch'])): 
                                                    $timeStr      = date('H:i:s', strtotime($s['last_punch']));
                                                    $isMorning    = ($timeStr >= '09:00:00' && $timeStr <= '10:00:00');
                                                    $isEvening    = ($timeStr >= '20:00:00' && $timeStr <= '21:00:00');
                                                    $isOnTime     = ($isMorning || $isEvening);
                                                    $isConcession = (bool)($s['is_concession'] ?? false);
                                                ?>
                                                    <div class="flex flex-col gap-1">
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="text-[11px] font-bold text-slate-800"><?= strtoupper($s['last_status']) ?></span>

                                                            <?php if ($isConcession): ?>
                                                                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-amber-50 text-amber-700 border border-amber-300">
                                                                    ● Concession
                                                                </span>
                                                            <?php elseif ($isOnTime): ?>
                                                                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-emerald-50 text-emerald-700 border border-emerald-300">
                                                                    ● On Time
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-rose-50 text-rose-700 border border-rose-300">
                                                                    ● Late / Restricted
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>

                                                        <span class="text-[10px] text-slate-400"><?= date('d M, h:i A', strtotime($s['last_punch'])) ?></span>

                                                        <?php if (!$isOnTime): ?>
                                                            <form method="POST" action="staff_manage.php" class="mt-0.5">
                                                                <input type="hidden" name="action" value="toggle_concession">
                                                                <input type="hidden" name="attendance_id" value="<?= $s['last_att_id'] ?>">
                                                                <input type="hidden" name="set_concession" value="<?= $isConcession ? 0 : 1 ?>">
                                                                <button type="submit" class="text-[10px] font-bold underline cursor-pointer <?= $isConcession ? 'text-slate-400 hover:text-slate-600' : 'text-amber-600 hover:text-amber-800' ?>">
                                                                    <?= $isConcession ? 'Revoke Concession' : 'Grant Concession' ?>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-[10px] text-slate-400 italic">No punches</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-3.5 text-center">
                                                <form method="POST" action="staff_manage.php" onsubmit="return confirm('Are you sure you want to drop <?= htmlspecialchars(addslashes($s['name'])) ?>? This deletes all their biometric data.');">
                                                    <input type="hidden" name="action" value="delete_staff">
                                                    <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                                                    <button type="submit" class="px-2.5 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 rounded-lg text-xs font-bold transition">
                                                        Drop
                                                    </button>
                                                </form>
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
        const video = document.getElementById('videoFeed');
        const overlay = document.getElementById('overlayCanvas');
        const aiStatus = document.getElementById('aiStatus');
        const btnCapture = document.getElementById('btnCapture');
        const btnSubmit = document.getElementById('btnSubmit');
        const vectorBadge = document.getElementById('vectorBadge');
        const descriptorInput = document.getElementById('faceDescriptorInput');

        let modelsLoaded = false;
        let isCameraActive = false;

        async function loadModels() {
            aiStatus.textContent = 'Loading AI Models...';
            aiStatus.className = 'text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 px-2 py-0.5 rounded-md';

            const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/model/';

            try {
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                    faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                    faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
                ]);

                modelsLoaded = true;
                aiStatus.textContent = 'AI Ready';
                aiStatus.className = 'text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-md';

                if (isCameraActive) {
                    btnCapture.disabled = false;
                }
            } catch (err) {
                console.error('Model Load Error:', err);
                aiStatus.textContent = 'Model Load Failed';
                aiStatus.className = 'text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200 px-2 py-0.5 rounded-md';
                alert('Could not download AI face models from CDN. Check your internet connection.');
            }
        }

        async function startCamera() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
                    audio: false
                });
                video.srcObject = stream;
                video.onloadedmetadata = () => {
                    video.play();
                    isCameraActive = true;
                    document.getElementById('camPlaceholder').classList.add('hidden');
                    if (modelsLoaded) {
                        btnCapture.disabled = false;
                    }
                };
            } catch (e) {
                console.error('Camera Access Error:', e);
                alert('Camera permission denied or camera not found! Please allow camera access.');
            }
        }

        async function captureFaceDescriptor() {
            if (!modelsLoaded) {
                alert('AI models are still downloading. Please wait for "AI Ready".');
                return;
            }

            if (!isCameraActive || video.paused || video.ended || video.readyState < 2) {
                alert('Please click "Start Cam" and wait for the camera stream to initialize.');
                return;
            }

            btnCapture.textContent = 'Scanning Face...';
            btnCapture.disabled = true;

            try {
                const displaySize = { width: video.videoWidth || 640, height: video.videoHeight || 480 };
                overlay.width = displaySize.width;
                overlay.height = displaySize.height;

                const detectorOptions = new faceapi.TinyFaceDetectorOptions({
                    inputSize: 320,
                    scoreThreshold: 0.25
                });

                const detection = await faceapi
                    .detectSingleFace(video, detectorOptions)
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                const ctx = overlay.getContext('2d');
                ctx.clearRect(0, 0, overlay.width, overlay.height);

                if (!detection) {
                    alert('No clear face detected! Look directly into camera with adequate light.');
                    btnCapture.textContent = 'Capture Face ID';
                    btnCapture.disabled = false;
                    return;
                }

                const resizedDetection = faceapi.resizeResults(detection, displaySize);
                faceapi.draw.drawDetections(overlay, resizedDetection);
                faceapi.draw.drawFaceLandmarks(overlay, resizedDetection);

                const descriptorArray = Array.from(detection.descriptor);
                descriptorInput.value = JSON.stringify(descriptorArray);

                vectorBadge.className = 'p-2.5 rounded-xl border text-center text-xs font-mono font-bold bg-emerald-50 text-emerald-700 border-emerald-200';
                vectorBadge.textContent = '✓ Biometric Vector Locked (' + descriptorArray.length + ' floats)';

                btnSubmit.disabled = false;
                btnCapture.textContent = 'Re-capture Face ID';
                btnCapture.disabled = false;
            } catch (err) {
                console.error('Capture Exception:', err);
                alert('Scanning error: ' + err.message);
                btnCapture.textContent = 'Capture Face ID';
                btnCapture.disabled = false;
            }
        }

        window.addEventListener('DOMContentLoaded', loadModels);
    </script>
</body>
</html>