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
    $newStatus = $validActions[$action];
    $db->prepare("UPDATE bookings SET status = ? WHERE id = ?")
       ->execute([$newStatus, $bookingId]);
    $messages = [
        'confirm' => 'Buchung bestätigt!',
        'cancel' => 'Buchung storniert.',
        'complete' => 'Buchung abgeschlossen.',
        'noshow' => 'Als nicht erschienen markiert.',
    ];
    setFlash('success', $messages[$action]);

    // Send status update email to customer
    if (in_array($newStatus, ['confirmed', 'cancelled', 'completed'])) {
        try {
            require_once __DIR__ . '/../includes/Mailer.php';
            // Fetch customer, business, service
            $stmt = $db->prepare("
                SELECT bk.*, u.email as cust_email, u.first_name as cust_fn, u.last_name as cust_ln,
                       b.name as biz_name, b.slug as biz_slug,
                       s.name as svc_name
                FROM bookings bk
                JOIN users u ON bk.user_id = u.id
                JOIN businesses b ON bk.business_id = b.id
                LEFT JOIN services s ON bk.service_id = s.id
                WHERE bk.id = ?
            ");
            $stmt->execute([$bookingId]);
            $row = $stmt->fetch();
            if ($row) {
                $custUser = ['email' => $row['cust_email'], 'first_name' => $row['cust_fn']];
                $bizArr   = ['name' => $row['biz_name'], 'slug' => $row['biz_slug']];
                Mailer::sendBookingStatusUpdate($booking, $bizArr, $custUser, $newStatus);
                (new Mailer())->processQueue(5);
            }
        } catch (Throwable $e) { error_log('[Mail] ' . $e->getMessage()); }
    }
}

header('Location: /dashboard/index.php?tab=bookings');
exit;
