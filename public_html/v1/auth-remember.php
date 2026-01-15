<?php
/**
 * Auth Remember Token Check API
 * Validates remember token and returns a new one-time auth token
 *
 * POST /v1/auth-remember
 * Body: remember_token
 * Returns: { success, token, redirect }
 */

require_once __DIR__ . '/../../src/helpers.php';

set_auth_cors_headers();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$db = connexus_db();

$rememberToken = $_POST['remember_token'] ?? '';

if (empty($rememberToken)) {
    json_error('No remember token provided');
}

// Find valid remember token
$remember = $db->selectCollection(COL_REMEMBER_TOKENS)->findOne([
    'token' => $rememberToken,
    'expires_at' => ['$gt' => new MongoDB\BSON\UTCDateTime()]
]);

if (!$remember) {
    json_error('Invalid or expired remember token');
}

// Verify user exists
$user = $db->selectCollection(COL_USERS)->findOne([
    '_id' => new MongoDB\BSON\ObjectId($remember['user_id']),
    'deleted' => ['$ne' => true]
]);

if (!$user) {
    // Delete invalid remember token
    $db->selectCollection(COL_REMEMBER_TOKENS)->deleteOne(['_id' => $remember['_id']]);
    json_error('User not found');
}

// Generate new one-time auth token
$token = bin2hex(random_bytes(32));
$expiry = new MongoDB\BSON\UTCDateTime((time() + 300) * 1000); // 5 minutes

$db->selectCollection(COL_AUTH_TOKENS)->insertOne([
    'token' => $token,
    'user_id' => (string)$user['_id'],
    'expires_at' => $expiry,
    'used' => false,
    'created_at' => new MongoDB\BSON\UTCDateTime(),
    'from_remember' => true
]);

// Update last_login
$db->selectCollection(COL_USERS)->updateOne(
    ['_id' => $user['_id']],
    ['$set' => ['last_login' => new MongoDB\BSON\UTCDateTime()]]
);

// Track login event
$db->selectCollection(COL_LOGIN_EVENTS)->insertOne([
    'user_id' => (string)$user['_id'],
    'email' => $user['email'] ?? '',
    'source' => 'remember_token',
    'timestamp' => new MongoDB\BSON\UTCDateTime(),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null
]);

json_response([
    'success' => true,
    'token' => $token,
    'redirect' => 'https://my.orlandochamberofcommerce.com/auth.php?token=' . $token
]);
