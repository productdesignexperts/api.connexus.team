<?php
/**
 * Verify PIN API
 * Validates PIN code and allows password reset
 *
 * POST /v1/auth-verify-pin
 * Body: email, pin, new_password, confirm_password
 * Returns: { success, token, redirect } on password reset
 *          { success, verified } on PIN verification only
 */

require_once __DIR__ . '/../../src/helpers.php';

set_auth_cors_headers();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$db = connexus_db();

$email = strtolower(trim($_POST['email'] ?? ''));
$pin = trim($_POST['pin'] ?? '');
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (empty($email) || empty($pin)) {
    json_error('Email and PIN are required.');
}

// Find valid reset request
$reset = $db->selectCollection(COL_PASSWORD_RESETS)->findOne([
    'email' => $email,
    'pin' => $pin,
    'used' => false,
    'expires_at' => ['$gt' => new MongoDB\BSON\UTCDateTime()]
]);

if (!$reset) {
    json_error('Invalid or expired PIN code. Please request a new one.');
}

// If no password provided, just verify the PIN
if (empty($newPassword)) {
    json_response([
        'success' => true,
        'verified' => true,
        'message' => 'PIN verified. Please enter your new password.'
    ]);
}

// Validate password
if (strlen($newPassword) < 6) {
    json_error('Password must be at least 6 characters.');
}

if ($newPassword !== $confirmPassword) {
    json_error('Passwords do not match.');
}

// Find user
$user = $db->selectCollection(COL_USERS)->findOne([
    '_id' => new MongoDB\BSON\ObjectId($reset['user_id'])
]);

if (!$user) {
    json_error('User not found.');
}

// Update password
$db->selectCollection(COL_USERS)->updateOne(
    ['_id' => $user['_id']],
    ['$set' => [
        'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        'password_updated_at' => new MongoDB\BSON\UTCDateTime()
    ]]
);

// Mark reset as used
$db->selectCollection(COL_PASSWORD_RESETS)->updateOne(
    ['_id' => $reset['_id']],
    ['$set' => ['used' => true]]
);

// Generate auth token to auto-login
$token = bin2hex(random_bytes(32));
$expiry = new MongoDB\BSON\UTCDateTime((time() + 300) * 1000); // 5 minutes

$db->selectCollection(COL_AUTH_TOKENS)->insertOne([
    'token' => $token,
    'user_id' => (string)$user['_id'],
    'expires_at' => $expiry,
    'used' => false,
    'created_at' => new MongoDB\BSON\UTCDateTime(),
    'from_password_reset' => true
]);

// Track password reset event
$db->selectCollection(COL_LOGIN_EVENTS)->insertOne([
    'user_id' => (string)$user['_id'],
    'email' => $user['email'] ?? '',
    'source' => 'password_reset',
    'timestamp' => new MongoDB\BSON\UTCDateTime(),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null
]);

json_response([
    'success' => true,
    'message' => 'Password updated successfully.',
    'token' => $token,
    'redirect' => 'https://my.orlandochamberofcommerce.com/auth.php?token=' . $token
]);
