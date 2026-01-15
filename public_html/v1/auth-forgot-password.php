<?php
/**
 * Forgot Password API
 * Sends PIN code via SMS for password reset
 *
 * POST /v1/auth-forgot-password
 * Body: email
 * Returns: { success, phone_last4, message }
 */

require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/sms_helper.php';

set_auth_cors_headers();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$db = connexus_db();

$email = strtolower(trim($_POST['email'] ?? ''));

if (empty($email)) {
    json_error('Please enter your email address.');
}

// Find user by email
$user = $db->selectCollection(COL_USERS)->findOne([
    'email' => $email,
    'deleted' => ['$ne' => true]
]);

if (!$user) {
    json_error('No account found with that email address.');
}

if (empty($user['phone'])) {
    json_error('No phone number on file for this account. Please contact support.');
}

// Clean phone number
$phone = preg_replace('/\D+/', '', (string)$user['phone']);

if (strlen($phone) !== 10) {
    json_error('Invalid phone number on file. Please contact support.');
}

// Generate 6-digit PIN
$pin = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

// Store PIN in database (expires in 15 minutes)
$expiresAt = new MongoDB\BSON\UTCDateTime((time() + 900) * 1000);

// Delete any existing reset requests for this user
$db->selectCollection(COL_PASSWORD_RESETS)->deleteMany([
    'user_id' => (string)$user['_id']
]);

// Insert new reset request
$db->selectCollection(COL_PASSWORD_RESETS)->insertOne([
    'user_id' => (string)$user['_id'],
    'email' => $email,
    'pin' => $pin,
    'phone_last4' => substr($phone, -4),
    'created_at' => new MongoDB\BSON\UTCDateTime(),
    'expires_at' => $expiresAt,
    'used' => false
]);

// Send SMS with PIN
$message = "Your Orlando Chamber password reset code is: {$pin}\n\nThis code expires in 15 minutes.";
$result = send_sms($phone, $message, 'OCOC Password Reset', false, 'password_reset');

if ($result['success']) {
    json_response([
        'success' => true,
        'phone_last4' => substr($phone, -4),
        'email' => $email,
        'message' => 'PIN code sent to your phone ending in ' . substr($phone, -4)
    ]);
} else {
    json_error('Failed to send PIN code. Please try again or contact support.');
}
