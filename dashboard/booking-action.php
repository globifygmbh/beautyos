<?php
require_once __DIR__ . '/../config/app.php';
requireBusiness();

$db = getDB();
$user = currentUser();

$bookingId = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

// Verify business owns this booking
$stmt = $db->prepare("
    SELECT bk.* FROM bookings bk
    JOIN businesses b ON bk.business_id = b.id
    WHERE bk.id = ? AND b.user_id = ?
");
$stmt->execute([$bookingId, $user['id']]);
$booking = $stmt->fetch();

if (!$booking) {
    setFlash('error', 'Buchung nicht gefunden.');
    header('Location: /dashboard/index.php');
    exit;
}

$validActions = [
    'confirm' => 'confirmed',
    'cancel' => 'cancelled',
    'complete' => 'completed',
    'noshow' => 'no_show',
];

if (isset($validActions[$action])) {
    $db->prepare("UPDATE bookings SET status = ? WHERE id = ?")
       ->execute([$validActions[$action], $bookingId]);
    $messages = [
        'confirm' => 'Buchung bestätigt!',
        'cancel' => 'Buchung storniert.',
        'complete' => 'Buchung abgeschlossen.',
        'noshow' => 'Als nicht erschienen markiert.',
    ];
    setFlash('success', $messages[$action]);
}

header('Location: /dashboard/index.php?tab=bookings');
exit;
