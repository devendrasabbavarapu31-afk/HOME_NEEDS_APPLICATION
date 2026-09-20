<?php
session_start();
require '../db.php';

define('TEST_MODE', true);
define('STORE_LAT', 17.669000);
define('STORE_LON', 82.612000);
define('GEOFENCE_RADIUS_METERS', 150);

// 1. Handle Automated Punch Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) &&$_POST['action'] === 'record_punch') {
    header('Content-Type: application/json');

    $staffId = (int)($_POST['staff_id'] ?? 0);
    $userLat = (float)($_POST['latitude'] ?? 0);
    $userLon = (float)($_POST['longitude'] ?? 0);

    if ($staffId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid staff identity.']);
        exit;
    }

    if (!TEST_MODE) {
        $earthRadius = 6371000;
        $dLat = deg2rad($userLat - STORE_LAT);$dLon = deg2rad($userLon - STORE_LON);$a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad(STORE_LAT)) * cos(deg2rad($userLat)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));$distance = $earthRadius * $c;

        if ($distance > GEOFENCE_RADIUS_METERS) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Access Denied: ' . round($distance) . 'm away from showroom.'
            ]);
            exit;
        }
    }

    // 2. Prevent Double Punches within 2 Minutes
    $checkStmt =$conn->prepare("
        SELECT punch_time FROM staff_attendance 
        WHERE staff_id = ? AND punch_time >= NOW() - INTERVAL 2 MINUTE 
        ORDER BY id DESC LIMIT 1
    ");
    $checkStmt->execute([$staffId]);
    if ($checkStmt->fetch()) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Punch already logged! Please wait before scanning again.'
        ]);
        exit;
    }

    // 3. Automated First Punch = IN, Second Punch = OUT
    $todayStmt =$conn->prepare("
        SELECT punch_type FROM staff_attendance 
        WHERE staff_id = ? AND DATE(punch_time) = CURRENT_DATE() 
        ORDER BY id DESC LIMIT 1
    ");
    $todayStmt->execute([$staffId]);
    $lastTodayPunch =$todayStmt->fetch(PDO::FETCH_ASSOC);

    if (empty($lastTodayPunch)) {$punchType = 'in';
    } elseif ($lastTodayPunch['punch_type'] === 'out') {$punchType = 'in';
    } else {
        $punchType = 'out';
    }

    try {
        $stmt =$conn->prepare("
            INSERT INTO staff_attendance (staff_id, punch_type, latitude, longitude, location_verified)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([$staffId,$punchType, $userLat,$userLon]);

        $directionText = ($punchType === 'in') ? 'Check-IN (Morning)' : 'Check-OUT (Evening)';

        echo json_encode([
            'status' => 'success',
            'punch_type' => $punchType,
            'message' => "{$directionText} marked successfully at " . date('h:i A') . "!"
        ]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
    }
    exit;
}

// Fetch enrolled staff
$staffQuery =$conn->query("
    SELECT id, staff_code, name, designation, face_descriptor 
    FROM staff_members 
    WHERE is_active = 1 AND face_descriptor IS NOT NULL AND face_descriptor != ''
")->fetchAll(PDO::FETCH_ASSOC);

$enrolledStaff = [];
foreach ($staffQuery as$sq) {
    $descriptorArray = json_decode($sq['face_descriptor'], true);
    if (is_array($descriptorArray) && count($descriptorArray) > 0) {$enrolledStaff[] = [
            'id' => (int)$sq['id'],
            'code' => $sq['staff_code'],
            'name' => $sq['name'],
            'descriptor' => $descriptorArray
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instant Attendance Kiosk - SAI GANAPATHI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/dist/face-api.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-950 min-h-screen text-slate-100 flex flex-col items-center justify-center p-4">

    <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl p-6 space-y-4 shadow-2xl relative">
        
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <div>
                <h1 class="text-sm font-black text-white uppercase tracking-wider">Attendance Kiosk</h1>
                <p class="text-[10px] text-amber-400 font-bold tracking-widest uppercase">Sai Ganapathi Home Needs</p>
            </div>
            <div id="geoBadge" class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-800 text-slate-400">
                <?= TEST_MODE ? 'Test GPS Active' : 'Verifying GPS...' ?>
            </div>
        </div>

        <div class="relative w-full aspect-square bg-black rounded-2xl overflow-hidden border border-slate-800 flex items-center justify-center">
            <video id="videoFeed" autoplay muted playsinline class="w-full h-full object-cover"></video>
            <canvas id="overlayCanvas" class="absolute inset-0 pointer-events-none w-full h-full"></canvas>
            
            <div id="targetBox" class="absolute inset-10 border-2 border-dashed border-amber-400/40 rounded-2xl pointer-events-none transition-all duration-300"></div>
            
            <div id="scanStatus" class="absolute bottom-3 px-3.5 py-1 bg-slate-950/90 backdrop-blur-md rounded-full text-[10px] font-bold text-slate-300 shadow-md">
                Looking for enrolled faces...
            </div>
        </div>

        <div class="p-2.5 rounded-xl bg-slate-950 border border-slate-800/80 flex items-center justify-between text-xs font-bold">
            <span class="text-slate-400 text-[11px]">System Mode:</span>
            <span class="text-emerald-400 text-[11px] font-mono">Instant Face Detection</span>
        </div>

        <div id="resultBox" class="hidden p-3 rounded-xl text-center text-xs font-bold transition"></div>
        
        <div class="flex items-center justify-between text-[11px] text-slate-500 pt-1">
            <span>Enrolled Staff: <strong class="text-slate-300"><?= count($enrolledStaff) ?></strong></span>
            <a href="../admin/staff_manage.php" class="text-amber-400 hover:underline font-semibold">Staff Registry &rarr;</a>
        </div>
    </div>

    <script>
        const enrolledData = <?= json_encode($enrolledStaff) ?>;
        const isTestMode = <?= TEST_MODE ? 'true' : 'false' ?>;

        const video = document.getElementById('videoFeed');
        const overlay = document.getElementById('overlayCanvas');
        const scanStatus = document.getElementById('scanStatus');
        const geoBadge = document.getElementById('geoBadge');
        const resultBox = document.getElementById('resultBox');
        const targetBox = document.getElementById('targetBox');

        let userLocation = isTestMode ? { lat: 17.6690, lon: 82.6120 } : null;
        let isProcessing = false;
        let faceMatcher = null;

        function initGeolocation() {
            if (isTestMode) {
                geoBadge.textContent = 'Test GPS Approved';
                geoBadge.className = 'px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-950 text-emerald-400 border border-emerald-800';
                return;
            }

            if (navigator.geolocation) {
                navigator.geolocation.watchPosition(
                    (pos) => {
                        userLocation = { lat: pos.coords.latitude, lon: pos.coords.longitude };
                        geoBadge.textContent = 'Location Verified';
                        geoBadge.className = 'px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-950 text-emerald-400 border border-emerald-800';
                    },
                    (err) => {
                        geoBadge.textContent = 'GPS Denied';
                        geoBadge.className = 'px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-950 text-rose-400 border border-rose-800';
                    },
                    { enableHighAccuracy: true }
                );
            }
        }

        async function initAI() {
            const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.12/model/';

            try {
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                    faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                    faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
                ]);

                if (enrolledData.length > 0) {
                    const labeledDescriptors = enrolledData.map(st => {
                        return new faceapi.LabeledFaceDescriptors(
                            `${st.id}:::${st.name}`, 
                            [new Float32Array(st.descriptor)]
                        );
                    });
                    faceMatcher = new faceapi.FaceMatcher(labeledDescriptors, 0.60);
                }

                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
                    audio: false
                });
                video.srcObject = stream;
                video.onloadedmetadata = () => {
                    video.play();
                    scanStatus.textContent = 'Ready: Look into camera to punch';
                    setInterval(scanLoop, 300);
                };

            } catch (err) {
                console.error('AI Initializer Error:', err);
                scanStatus.textContent = 'Error loading face models';
            }
        }

        async function scanLoop() {
            if (isProcessing) return;
            if (!faceMatcher) return;
            if (!userLocation) return;
            if (video.paused) return;
            if (video.ended) return;

            const displaySize = { width: video.videoWidth || 640, height: video.videoHeight || 480 };
            overlay.width = displaySize.width;
            overlay.height = displaySize.height;

            const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.35 }))
                .withFaceLandmarks()
                .withFaceDescriptor();

            const ctx = overlay.getContext('2d');
            ctx.clearRect(0, 0, overlay.width, overlay.height);

            if (!detection) {
                targetBox.style.borderColor = '#f59e0b';
                scanStatus.textContent = 'Align face in the box...';
                return;
            }

            const match = faceMatcher.findBestMatch(detection.descriptor);
            if (match.label !== 'unknown') {
                const parts = match.label.split(':::');
                const staffId = parts[0];
                const staffName = parts[1];

                targetBox.style.borderColor = '#10b981';
                scanStatus.textContent = `Identified: ${staffName}! Punching...`;

                triggerAttendancePunch(staffId, staffName);
            } else {
                targetBox.style.borderColor = '#ef4444';
                scanStatus.textContent = 'Unrecognized staff member';
            }
        }

        async function triggerAttendancePunch(staffId, staffName) {
            isProcessing = true;

            const formData = new FormData();
            formData.append('action', 'record_punch');
            formData.append('staff_id', staffId);
            formData.append('latitude', userLocation.lat);
            formData.append('longitude', userLocation.lon);

            try {
                const res = await fetch('attendance.php', { method: 'POST', body: formData });
                const data = await res.json();

                resultBox.classList.remove('hidden');
                if (data.status === 'success') {
                    resultBox.className = 'p-3 rounded-xl text-center text-xs font-bold bg-emerald-950 text-emerald-300 border border-emerald-800';
                    resultBox.innerHTML = `&check; <strong>${staffName}</strong>: ${data.message}`;
                } else {
                    resultBox.className = 'p-3 rounded-xl text-center text-xs font-bold bg-rose-950 text-rose-300 border border-rose-800';
                    resultBox.textContent = data.message;
                }

                setTimeout(() => {
                    resultBox.classList.add('hidden');
                    isProcessing = false;
                    targetBox.style.borderColor = '#f59e0b';
                    scanStatus.textContent = 'Ready for next staff';
                }, 4000);

            } catch (err) {
                console.error(err);
                isProcessing = false;
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            initGeolocation();
            initAI();
        });
    </script>
</body>
</html>