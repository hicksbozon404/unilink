<?php
// webhook_airtel.php
header('Content-Type: application/json');

// CONFIG
$pdo = new PDO("mysql:host=localhost;dbname=unilink_db;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);
$CALLBACK_SECRET = 'YOUR_CALLBACK_SECRET_FROM_AIRTEL_DASHBOARD'; // ← SET THIS

// 1. Verify IP (from Airtel docs)
$allowedIps = ['196.201.216.0/24', '196.201.217.0/24']; // Airtel Zambia range
$clientIp = $_SERVER['REMOTE_ADDR'];
$allowed = false;
foreach ($allowedIps as $cidr) {
    list($subnet, $mask) = explode('/', $cidr);
    if ((ip2long($clientIp) & ~((1 << (32 - $mask)) - 1)) == ip2long($subnet)) {
        $allowed = true;
        break;
    }
}
if (!$allowed) {
    http_response_code(403);
    exit(json_encode(['error' => 'IP not allowed']));
}

// 2. Read raw POST
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// 3. Verify Signature
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
$hash = hash_hmac('sha256', $input, $CALLBACK_SECRET);
if (!hash_equals($hash, $signature)) {
    http_response_code(401);
    exit(json_encode(['error' => 'Invalid signature']));
}

// 4. Process Transaction
if ($data['transaction']['status'] ?? '' === 'SUCCESSFUL') {
    $ref = $data['transaction']['id'];
    $amount = $data['transaction']['amount'];
    $phone = $data['payer']['partyId'];

    $stmt = $pdo->prepare("UPDATE payments SET status='COMPLETED', amount=? WHERE reference=? AND provider='airtel'");
    $stmt->execute([$amount, $ref]);
} else {
    $ref = $data['transaction']['id'] ?? '';
    $stmt = $pdo->prepare("UPDATE payments SET status='FAILED' WHERE reference=?");
    $stmt->execute([$ref]);
}

// 5. Respond
echo json_encode(['status' => 'received']);
?>