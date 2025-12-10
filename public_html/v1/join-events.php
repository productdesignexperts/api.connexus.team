<?php
/**
 * Join Event Calendar Endpoint
 *
 * POST /v1/join-events
 *
 * Handles event calendar signup:
 * - If email exists: Enable event_reminder notifications (email + sms)
 * - If email not found: Create unpaid member with random password
 */

require_once __DIR__ . '/../../src/helpers.php';

set_cors_headers();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

// Get JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    // Fall back to form data
    $input = $_POST;
}

// Required fields
$email = strtolower(trim($input['email'] ?? ''));
$firstName = trim($input['first_name'] ?? $input['first-name'] ?? '');
$lastName = trim($input['last_name'] ?? $input['last-name'] ?? '');

// Optional fields
$phone = trim($input['phone'] ?? '');
$company = trim($input['company'] ?? '');

// Validate required fields
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Valid email address is required', 400);
}
if (empty($firstName)) {
    json_error('First name is required', 400);
}
if (empty($lastName)) {
    json_error('Last name is required', 400);
}

$db = connexus_db();
$usersCollection = $db->selectCollection(COL_USERS);

// Check if user already exists
$existingUser = $usersCollection->findOne(['email' => $email]);

if ($existingUser) {
    // User exists - update notification preferences to enable event_reminder for email and sms
    $currentPrefs = $existingUser['notification_preferences'] ?? [];
    $currentPrefs['event_reminder'] = [
        'email' => true,
        'sms' => true
    ];

    $usersCollection->updateOne(
        ['_id' => $existingUser['_id']],
        ['$set' => ['notification_preferences' => $currentPrefs]]
    );

    json_response([
        'success' => true,
        'message' => 'You are now subscribed to event reminders!',
        'existing_user' => true
    ]);
} else {
    // New user - create unpaid member
    $randomPassword = bin2hex(random_bytes(16));

    $newUser = [
        'email' => $email,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => $phone,
        'company' => $company,
        'company_name' => $company,
        'password_hash' => password_hash($randomPassword, PASSWORD_DEFAULT),
        'role' => 'member',
        'paid' => false,
        'signed_up_by' => 'join_event_form',
        'notification_preferences' => [
            'event_reminder' => [
                'email' => true,
                'sms' => true
            ]
        ],
        'created_at' => new MongoDB\BSON\UTCDateTime(),
        'deleted' => false
    ];

    try {
        $usersCollection->insertOne($newUser);

        json_response([
            'success' => true,
            'message' => 'Thank you for signing up! You will receive event reminders.',
            'existing_user' => false
        ]);
    } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
        // Duplicate key error (race condition - email was just added)
        if ($e->getCode() === 11000) {
            json_response([
                'success' => true,
                'message' => 'You are already signed up for event reminders!',
                'existing_user' => true
            ]);
        }
        throw $e;
    }
}
