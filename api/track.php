<?php
/**
 * Analytics Tracking Endpoint
 * POST /api/track.php
 * Body: { event_type, entity_type, entity_id, page }
 */
require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . APP_URL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$eventType  = preg_replace('/[^a-z_]/', '', $input['event_type'] ?? '');
$entityType = preg_replace('/[^a-z_]/', '', $input['entity_type'] ?? '');
$entityId   = (int)($input['entity_id'] ?? 0);
$page       = substr($input['page'] ?? '', 0, 255);
$referrer   = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500);
$userAgent  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

if (!$eventType) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing event_type']);
    exit;
}

// Hash IP for privacy (GDPR)
$ip     = $_SERVER['REMOTE_ADDR'] ?? '';
$ipHash = hash('sha256', $ip . date('Y-m-d')); // daily rotation

// Simple geolocation via IP (free, no API key needed for basic country)
$country = null;
$city    = null;

try {
    $db = getDB();
    $db->prepare("INSERT INTO analytics_events (event_type, entity_type, entity_id, page, referrer, ip_hash, country, city, user_agent) VALUES (?,?,?,?,?,?,?,?,?)")
       ->execute([$eventType, $entityType ?: null, $entityId ?: null, $page ?: null, $referrer ?: null, $ipHash, $country, $city, $userAgent]);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error']);
}
