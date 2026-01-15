<?php
/**
 * Auth Login API
 * Validates credentials and returns a one-time auth token for login
 *
 * POST /v1/auth-login
 * Body: email, password, remember (optional)
 * Returns: { success, token, remember_token, redirect }
 */

require_once __DIR__ . '/../../src/helpers.php';

set_auth_cors_headers();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

$db = connexus_db();

// Get credentials
$login = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$remember = !empty($_POST['remember']);

if (empty($login) || empty($password)) {
    json_error('Email and password required');
}

// Check for legacy admin login first
$user = null;
$legacyUser = getenv('OCOC_ADMIN_USER') ?: 'admin';
$legacyPass = getenv('OCOC_ADMIN_PASS') ?: 'changeme';

if ($login === $legacyUser && $password === $legacyPass) {
    // Legacy admin - find or create the system admin user
    $user = $db->selectCollection(COL_USERS)->findOne(['email' => 'admin@system.local']);
    if (!$user) {
        // Create system admin user
        $result = $db->selectCollection(COL_USERS)->insertOne([
            'email' => 'admin@system.local',
            'first_name' => 'System',
            'last_name' => 'Admin',
            'is_super_admin' => true,
            'created_at' => new MongoDB\BSON\UTCDateTime()
        ]);
        $user = $db->selectCollection(COL_USERS)->findOne(['_id' => $result->getInsertedId()]);
    }
}

// If not legacy admin, check regular users by email
if (!$user) {
    $email = strtolower($login);
    $user = $db->selectCollection(COL_USERS)->findOne([
        'email' => $email,
        'deleted' => ['$ne' => true]
    ]);
    if ($user && !password_verify($password, $user['password_hash'] ?? '')) {
        $user = null; // Invalid password
    }
}

if (!$user) {
    json_error('Invalid credentials');
}

// Generate one-time auth token
$token = bin2hex(random_bytes(32));
$expiry = new MongoDB\BSON\UTCDateTime((time() + 300) * 1000); // 5 minutes

// Store token
$db->selectCollection(COL_AUTH_TOKENS)->insertOne([
    'token' => $token,
    'user_id' => (string)$user['_id'],
    'expires_at' => $expiry,
    'used' => false,
    'created_at' => new MongoDB\BSON\UTCDateTime()
]);

// If remember me, generate a persistent remember token
$rememberToken = null;
if ($remember) {
    $rememberToken = bin2hex(random_bytes(32));
    $rememberExpiry = new MongoDB\BSON\UTCDateTime((time() + 30 * 24 * 60 * 60) * 1000); // 30 days

    $db->selectCollection(COL_REMEMBER_TOKENS)->insertOne([
        'token' => $rememberToken,
        'user_id' => (string)$user['_id'],
        'expires_at' => $rememberExpiry,
        'created_at' => new MongoDB\BSON\UTCDateTime()
    ]);
}

// Update last_login timestamp
$db->selectCollection(COL_USERS)->updateOne(
    ['_id' => $user['_id']],
    ['$set' => ['last_login' => new MongoDB\BSON\UTCDateTime()]]
);

// Track login event
$db->selectCollection(COL_LOGIN_EVENTS)->insertOne([
    'user_id' => (string)$user['_id'],
    'email' => $user['email'] ?? '',
    'source' => 'web_login',
    'timestamp' => new MongoDB\BSON\UTCDateTime(),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? null
]);

json_response([
    'success' => true,
    'token' => $token,
    'remember_token' => $rememberToken,
    'redirect' => 'https://my.orlandochamberofcommerce.com/auth.php?token=' . $token
]);
