<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);

// 1. Database Connection
$conn = null;
if (file_exists('db.php')) {
    require_once 'db.php';
} elseif (file_exists('../db.php')) {
    require_once '../db.php';
} else {
    try {
        $conn = new PDO("mysql:host=127.0.0.1;dbname=ifb;charset=utf8mb4", "root", "", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (Exception $e) {
        echo json_encode(['reply' => 'Database connection failed.']);
        exit;
    }
}

// 2. Read Request Payload FIRST
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
$userMessage = trim($input['message'] ?? $_POST['message'] ?? '');

if (empty($userMessage)) {
    echo json_encode(['reply' => 'Please type a question about appliances, models, pricing, or showroom details.']);
    exit;
}

$lowerMsg = strtolower($userMessage);

// -------------------------------------------------------------
// RESTRICTIONS & SECURITY GUARDS
// -------------------------------------------------------------

// Guard 1: Rate Limiting (Max 15 requests per 60 seconds per IP)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$now = time();
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateKey = "rate_limit_" . md5($ip);

if (!isset($_SESSION[$rateKey])) {
    $_SESSION[$rateKey] = ['count' => 1, 'first_req' => $now];
} else {
    if (($now - $_SESSION[$rateKey]['first_req']) < 60) {
        $_SESSION[$rateKey]['count']++;
        if ($_SESSION[$rateKey]['count'] > 15) {
            echo json_encode(['reply' => '⚠️ Too many requests. Please wait a moment before sending another message.']);
            exit;
        }
    } else {
        $_SESSION[$rateKey] = ['count' => 1, 'first_req' => $now];
    }
}

// Guard 2: Message Length Cap
if (mb_strlen($userMessage) > 150) {
    echo json_encode(['reply' => '⚠️ Message is too long. Please keep your question under 150 characters.']);
    exit;
}

// Guard 3: Prompt Injection & Exploit Defense
$injectionKeywords = [
    'ignore previous', 'system prompt', 'you are now', 'drop table', 
    'select * from', '<script', 'eval(', 'union select', 'base64', 'exec('
];

foreach ($injectionKeywords as $badPattern) {
    if (stripos($userMessage, $badPattern) !== false) {
        echo json_encode(['reply' => '⚠️ Invalid query format. Please ask only about appliances and showroom services.']);
        exit;
    }
}

// Guard 4: Out-of-Scope Negative Keyword Screening
$bannedCategories = [
    'shoe', 'shoes', 'dress', 'clothes', 'mobile', 'iphone', 'laptop', 
    'medicine', 'grocery', 'crypto', 'gold', 'car', 'bike', 'flight'
];

foreach ($bannedCategories as $bannedWord) {
    if (preg_match('/\b' . preg_quote($bannedWord, '/') . '\b/i', $userMessage)) {
        echo json_encode([
            'reply' => "⚠️ Sai Ganapathi Home Needs specializes exclusively in <strong>home appliances and electronics</strong> (washing machines, refrigerators, ACs, microwaves, televisions).<br><br>We do not carry items like <em>" . htmlspecialchars($bannedWord) . "</em>."
        ]);
        exit;
    }
}

// -------------------------------------------------------------
// FAST INTENT CHECKS (Store Info)
// -------------------------------------------------------------

if (strpos($lowerMsg, 'where') !== false || strpos($lowerMsg, 'location') !== false || strpos($lowerMsg, 'address') !== false) {
    echo json_encode([
        'reply' => "📍 <strong>Showroom Location:</strong><br>"
                 . "Sai Ganapathi Home Needs & Furniture<br>"
                 . "Main Road, Narsipatnam, Anakapalli District, Andhra Pradesh.<br>"
                 . "📞 <strong>Contact / WhatsApp:</strong> +91 7893282348<br>"
                 . "🕒 <strong>Store Timings:</strong> 09:00 AM to 09:00 PM (Open all 7 days)"
    ]);
    exit;
}

if (strpos($lowerMsg, 'timing') !== false || strpos($lowerMsg, 'hour') !== false || strpos($lowerMsg, 'open') !== false) {
    echo json_encode([
        'reply' => "🕒 <strong>Showroom Hours:</strong><br>"
                 . "We are open every day (Monday to Sunday) from <strong>09:00 AM to 09:00 PM</strong>."
    ]);
    exit;
}

// -------------------------------------------------------------
// KEYWORD TOKENIZATION & CLEANING
// -------------------------------------------------------------

$cleaned = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $lowerMsg);
$rawWords = array_filter(explode(' ', $cleaned));

$stopWords = [
    'what', 'which', 'where', 'show', 'tell', 'give', 'list', 'about', 'some', 'with', 
    'from', 'cost', 'price', 'prices', 'have', 'your', 'item', 'items',
    'does', 'need', 'want', 'available', 'stock', 'product', 'products', 'good', 'best',
    'please', 'there', 'they', 'this', 'that', 'these', 'those', 'sell', 'like'
];

$searchTokens = [];
foreach ($rawWords as $w) {
    $w = trim($w);
    if ((strlen($w) >= 3 || in_array($w, ['lg', 'mi', 'tv', 'ac'])) && !in_array($w, $stopWords)) {
        $searchTokens[] = $w;
    }
}

if (empty($searchTokens)) {
    echo json_encode([
        'reply' => "Hello! How can I help you today?<br><br>"
                 . "💡 <strong>You can ask about:</strong><br>"
                 . "• Products: <em>IFB washing machine, Samsung refrigerator, 1.5 ton AC</em><br>"
                 . "• Showroom location, directions, and hours."
    ]);
    exit;
}

// -------------------------------------------------------------
// DATABASE QUERY & INVENTORY LOOKUP
// -------------------------------------------------------------

$sql = "
    SELECT 
        p.id,
        p.company,
        p.name,
        p.model,
        p.selling_price,
        p.discount,
        p.offer,
        p.specifications,
        COALESCE((
            SELECT SUM(si.remaining_pieces) 
            FROM stock_intake si 
            WHERE si.product_id = p.product_id
        ), 0) AS current_stock
    FROM public_products p
    WHERE p.status = 'active'
";

$params = [];
$tokenConditions = [];

foreach ($searchTokens as $idx => $token) {
    $p1 = ":t1_{$idx}";
    $p2 = ":t2_{$idx}";
    $p3 = ":t3_{$idx}";
    $p4 = ":t4_{$idx}";

    $tokenConditions[] = "(p.name LIKE {$p1} OR p.company LIKE {$p2} OR p.model LIKE {$p3} OR p.specifications LIKE {$p4})";
    
    $val = "%{$token}%";
    $params[$p1] = $val;
    $params[$p2] = $val;
    $params[$p3] = $val;
    $params[$p4] = $val;
}

$sql .= " AND (" . implode(" AND ", $tokenConditions) . ")";
$sql .= " ORDER BY current_stock DESC, p.selling_price ASC LIMIT 5";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fallback: If strict AND search yields nothing and multiple words were entered, check brand/name match
    if (empty($products) && count($searchTokens) > 1) {
        $orConditions = [];
        $orParams = [];
        foreach ($searchTokens as $idx => $token) {
            $p1 = ":ot1_{$idx}";
            $p2 = ":ot2_{$idx}";
            $orConditions[] = "(p.name LIKE {$p1} OR p.company LIKE {$p2})";
            $val = "%{$token}%";
            $orParams[$p1] = $val;
            $orParams[$p2] = $val;
        }

        $softSql = "
            SELECT 
                p.id, p.company, p.name, p.model, p.selling_price, p.discount, p.offer, p.specifications,
                COALESCE((
                    SELECT SUM(si.remaining_pieces) 
                    FROM stock_intake si 
                    WHERE si.product_id = p.product_id
                ), 0) AS current_stock
            FROM public_products p
            WHERE p.status = 'active' AND (" . implode(" OR ", $orConditions) . ")
            ORDER BY current_stock DESC, p.selling_price ASC LIMIT 3
        ";
        $softStmt = $conn->prepare($softSql);
        $softStmt->execute($orParams);
        $products = $softStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -------------------------------------------------------------
    // FORMAT RESPONSE
    // -------------------------------------------------------------
    if (!empty($products)) {
        $reply = "Here are the matching models from our live showroom inventory:<br><br>";

        foreach ($products as $i => $item) {
            $num = $i + 1;
            $mrp = (float)$item['selling_price'];
            $discount = (float)$item['discount'];
            $finalPrice = $mrp - ($mrp * ($discount / 100.0));
            $stockCount = (int)$item['current_stock'];

            $stockBadge = ($stockCount > 0)
                ? "<span style='color: #059669; font-weight: bold;'>In Stock ({$stockCount} available)</span>"
                : "<span style='color: #dc2626; font-weight: bold;'>Out of Stock</span>";

            $reply .= "<strong>{$num}. " . htmlspecialchars($item['company'] . ' ' . $item['name']) . "</strong><br>";
            $reply .= "• Model: <code>" . htmlspecialchars($item['model']) . "</code><br>";
            $reply .= "• Deal Price: <strong>₹" . number_format($finalPrice, 2) . "</strong> ";
            
            if ($discount > 0) {
                $reply .= "<strike style='color: #64748b;'>₹" . number_format($mrp, 2) . "</strike> ({$discount}% Off)";
            }
            
            $reply .= "<br>• Status: {$stockBadge}<br>";
            
            if (!empty($item['offer'])) {
                $reply .= "• Offer: " . htmlspecialchars($item['offer']) . "<br>";
            }
            $reply .= "<br>";
        }

        $reply .= "Visit our Narsipatnam showroom or call <strong>+91 7893282348</strong> for instant booking.";
        echo json_encode(['reply' => $reply]);
        exit;
    }

    // Explicit refusal for nonexistent items (No random fallback)
    echo json_encode([
        'reply' => "Sorry, we do not have records or active showroom stock for <em>\"" . htmlspecialchars($userMessage) . "\"</em>.<br><br>"
                 . "Please contact our showroom counter directly at <strong>+91 7893282348</strong> to check upcoming dealer shipments."
    ]);

} catch (Exception $e) {
    echo json_encode(['reply' => 'Error querying catalog: ' . $e->getMessage()]);
}