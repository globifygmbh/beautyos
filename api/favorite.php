<?php
require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht eingeloggt']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$businessId = (int)($data['business_id'] ?? 0);

if (!$businessId) {
    http_response_code(400);
    echo json_encode(['error' => 'Business ID fehlt']);
    exit;
}

$db = getDB();
$userId = $_SESSION['user_id'];

// Toggle favorite
$exists = $db->prepare("SELECT 1 FROM favorites WHERE user_id = ? AND business_id = ?");
$exists->execute([$userId, $businessId]);

if ($exists->fetch()) {
    $db->prepare("DELETE FROM favorites WHERE user_id = ? AND business_id = ?")->execute([$userId, $businessId]);
    echo json_encode(['status' => 'removed']);
} else {
    $db->prepare("INSERT INTO favorites (user_id, business_id) VALUES (?, ?)")->execute([$userId, $businessId]);
    echo json_encode(['status' => 'added']);
}
