<?php
/**
 * Stripe Webhook Handler
 * URL: /api/stripe-webhook.php
 * Register in Stripe Dashboard → Webhooks
 * Events: checkout.session.completed, customer.subscription.deleted
 */
require_once __DIR__ . '/../config/app.php';

$stripeConf = __DIR__ . '/../config/stripe.php';
if (!file_exists($stripeConf)) {
    http_response_code(500);
    exit('Stripe not configured');
}
include $stripeConf;

$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$secret    = defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : '';

// Verify Stripe signature
function verifyStripeSignature($payload, $sigHeader, $secret) {
    $parts = explode(',', $sigHeader);
    $timestamp = null; $signatures = [];
    foreach ($parts as $part) {
        [$k, $v] = explode('=', $part, 2);
        if ($k === 't') $timestamp = $v;
        if ($k === 'v1') $signatures[] = $v;
    }
    if (!$timestamp) return false;
    $signed = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signed, $secret);
    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) return true;
    }
    return false;
}

if ($secret && !verifyStripeSignature($payload, $sigHeader, $secret)) {
    http_response_code(400);
    exit('Invalid signature');
}

$event = json_decode($payload, true);
if (!$event) { http_response_code(400); exit('Invalid payload'); }

$db = getDB();

switch ($event['type']) {
    case 'checkout.session.completed':
        $session = $event['data']['object'];
        $sessionId = $session['id'];
        $metadata  = $session['metadata'] ?? [];
        $businessId = (int)($metadata['business_id'] ?? 0);
        $planId     = (int)($metadata['plan_id'] ?? 0);
        $userId     = (int)($metadata['user_id'] ?? 0);
        $amount     = ($session['amount_total'] ?? 0) / 100; // cents to euros

        if ($businessId && $planId) {
            // Update business subscription
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
            $db->prepare("UPDATE businesses SET subscription_plan_id=?, subscription_expires_at=?, status='active' WHERE id=?")
               ->execute([$planId, $expiresAt, $businessId]);

            // Record payment
            $db->prepare("INSERT INTO stripe_payments (user_id,business_id,plan_id,stripe_session_id,amount,status,period_start,period_end) VALUES (?,?,?,?,?,'paid',CURDATE(),DATE_ADD(CURDATE(),INTERVAL 30 DAY))")
               ->execute([$userId, $businessId, $planId, $sessionId, $amount]);
        }
        break;

    case 'customer.subscription.deleted':
        // Subscription cancelled - you could suspend the business or let expire naturally
        $sub = $event['data']['object'];
        $sessionId = $sub['id'];
        // Update payment status
        $db->prepare("UPDATE stripe_payments SET status='refunded' WHERE stripe_session_id=?")->execute([$sessionId]);
        break;

    case 'payment_intent.payment_failed':
        $pi = $event['data']['object'];
        $db->prepare("UPDATE stripe_payments SET status='failed' WHERE stripe_payment_intent=?")->execute([$pi['id']]);
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);
